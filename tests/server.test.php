<?php
// #[Server] methods, as the browser sees them: stubs from unscripted/ui-server.

use Unscripted\Ui\{Component, ServerCall};
use function Unscripted\Ui\blade;

$unstripped = <<<'BLADE'
<?php
use Unscripted\Ui\{Component, Server};
new class extends Component {
    #[Server]
    public static function secret(): array { return ['from the database']; }
};
?>
<p>hi</p>
BLADE;
fails('unstripped #[Server] method', fn() => blade($unstripped, 'secret.blade.php')->mount('#app'),
    'secret.blade.php came to the browser with its #[Server] method secret as written');

// What unscripted/ui-server sends in its place.
$stub = <<<'BLADE'
<?php
use Unscripted\Ui\{Component, Server, State, Watch};
new class extends Component {
    #[State] public string $query = '';
    #[Server, Watch('query')] public static function rows(string $query = 'x'): \Unscripted\Ui\ServerCall { return new \Unscripted\Ui\ServerCall('list', 'rows', func_get_args()); }
    public function wrong(): Generator { yield 42; }
};
?>
<p>{{ $query }}</p>
BLADE;
$component = blade($stub, 'list.blade.php');
$call = $component::rows('milk');
check('a stub returns a ServerCall', $call instanceof ServerCall, true);
check('naming the component and method', [$call->component, $call->method, $call->args], ['list', 'rows', ['milk']]);
check('defaults are left to the server', $component::rows()->args, []);
check('not loading before anything runs', $component->loading(), false);
fails('yielding something else', fn() => Closure::bind(fn() => $this->call('wrong'), $component, Component::class)(), "wrong yielded something that isn't a server call");
check('not loading after a flow fails', $component->loading('wrong'), false);

// Rendered on the server first: prerender() runs the loaders through the server, mount() adopts
// the state it sent, and the loaders don't run again.
$list = <<<'BLADE'
<?php
use Unscripted\Ui\{Component, Server, State, Watch};
new class extends Component {
    #[State] public string $query = '';
    #[State] public private(set) array $rows = [];
    #[Server, Watch('query')] public static function rows(string $query): \Unscripted\Ui\ServerCall { return new \Unscripted\Ui\ServerCall('list', 'rows', func_get_args()); }
};
?>
<p>{{ count($rows) }}</p>
BLADE;
$rendered = blade($list, 'list.blade.php')->prerender(fn(string $method, array $args) => ["$method(" . implode(',', $args) . ')']);
check('prerender: the HTML, after the loaders', trim($rendered['html']), '<p>1</p>');
check('prerender: the state, loaders filled', $rendered['state'], ['query' => '', 'rows' => ['rows()']]);
$adopting = blade($list, 'list.blade.php');
Closure::bind(function () {
    $this->adopt(['query' => 'mi', 'rows' => ['milk'], 'gone' => 1]);
    $this->load(); // would send a request (and fail, with no DOM here) if a loader ran
}, $adopting, Component::class)();
check('adopt: the state, private(set) too', [$adopting->query, $adopting->rows], ['mi', ['milk']]);
check('adopt: the loaders count as run', $adopting->loading(), false);
check('adopt: renders from it', html($adopting), '<p>1</p>');

// Validation: a 422's messages fill the error bag; templates read it with $errors and @error.
use Unscripted\Ui\{ErrorBag, ServerError};
$bag = new ErrorBag(['title' => ['The title field is required.', 'Too short.']]);
check('errors: has, first, get', [$bag->has('title'), $bag->has('other'), $bag->first('title'), $bag->get('other')], [true, false, 'The title field is required.', []]);
$bag->add('body', 'Needed.');
check('errors: add, all, any', [$bag->all(), $bag->any()], [['The title field is required.', 'Too short.', 'Needed.'], true]);
$bag->clear('title');
check('errors: clear one', $bag->all(), ['Needed.']);
$bag->clear();
check('errors: clear all', $bag->any(), false);

$form = blade(<<<'BLADE'
<?php
use Unscripted\Ui\{Component, State};
new class extends Component {
    #[State] public string $draft = '';
    public bool $saved = false;
};
?>
<input u-model="draft">@error('title')<p>{{ $message }}</p>@enderror<i>{{ count($errors->all()) }}</i>
BLADE, 'form.blade.php');
check('@error: nothing to show', html($form), '<input u-model="draft"><i>0</i>');
$form->errors()->add('title', 'Bad <title>');
check('@error: the first message, escaped', html($form), '<input u-model="draft"><p>Bad &lt;title&gt;</p><i>1</i>');

/** Starts flow `$name` on `$component` as run() would, without sending, and lands a reply. */
function reply(Component $component, string $name, Generator $flow, ?array $results, ?ServerError $error, bool $loader = false): void
{
    Closure::bind(function () use ($name, $flow, $results, $error, $loader) {
        $this->flows[$name] = [1, $flow, $loader];
        $this->land($name, $loader, $flow->current(), $results, $error);
    }, $component, Component::class)();
}
$invalid = new ServerError('The title field is required.', 422, ['title' => ['The title field is required.']]);
$after = [];
reply($form, 'add', (function () use (&$after) {
    yield new ServerCall('form', 'create', []);
    $after[] = 'went on';
})(), null, $invalid);
check('a 422 the action doesn\'t catch: in the bag, not thrown', [$form->errors()->first('title'), $after, $form->loading('add')], ['The title field is required.', [], false]);
reply($form, 'add', (function () use (&$after) {
    try {
        yield new ServerCall('form', 'create', []);
    } catch (ServerError $e) {
        $after[] = "caught {$e->status}";
    }
})(), null, $invalid);
check('a 422 the action catches: in the bag too', [$form->errors()->has('title'), $after], [true, ['caught 422']]);
reply($form, 'add', (function () {
    yield new ServerCall('form', 'create', []);
})(), [['id' => 1]], null);
check('a success clears the bag', $form->errors()->any(), false);
$form->errors()->add('title', 'kept');
fails('another failure is thrown, and leaves the bag', fn() => reply($form, 'add', (function () {
    yield new ServerCall('form', 'create', []);
})(), null, new ServerError('denied', 403)), 'denied');
check('...as it was', $form->errors()->first('title'), 'kept');
fails('a loader\'s 422 is thrown, not put in the bag', fn() => reply($form, 'saved', (function () {
    yield new ServerCall('form', 'saved', []);
})(), null, $invalid, loader: true), 'The title field is required.');
check('...which is left alone', $form->errors()->first('title'), 'kept');

done();
