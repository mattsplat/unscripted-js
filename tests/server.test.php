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

done();
