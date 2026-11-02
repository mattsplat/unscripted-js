<?php
// Batch 1 features that run without a DOM: u-on modifiers, u-model values and hooks, #[Computed].

use Unscripted\Ui\Component;
use function Unscripted\Ui\blade;

/** A stand-in DOM event. */
function event(array $props = []): object
{
    return new class($props) {
        public array $calls = [];
        public mixed $target = null;
        public ?string $key = null;
        public bool $ctrlKey = false;
        public bool $shiftKey = false;
        public bool $altKey = false;
        public bool $metaKey = false;
        public function __construct(array $props) { foreach ($props as $k => $v) { $this->$k = $v; } }
        public function preventDefault(): void { $this->calls[] = 'prevent'; }
        public function stopPropagation(): void { $this->calls[] = 'stop'; }
    };
}
/** A stand-in element with attributes. */
function element(array $attributes, array $props = []): object
{
    return new class($attributes, $props) {
        public mixed $type = 'text';
        public mixed $value = '';
        public mixed $checked = false;
        public function __construct(public array $attributes, array $props) { foreach ($props as $k => $v) { $this->$k = $v; } }
        public function getAttribute(string $name): ?string { return $this->attributes[$name] ?? null; }
        public function isSameNode(object $other): bool { return $other === $this; }
    };
}

$c = blade(<<<'BLADE'
<?php
use Unscripted\Ui\{Component, Computed, State};
new class extends Component {
    #[State] public int $count = 0;
    #[State] public array $form = ['age' => 0, 'tags' => []];
    #[State] public string $name = '';
    public array $log = [];
    public int $computedRuns = 0;
    public function add(int $by = 1): void { $this->count += $by; }
    #[Computed] public function double(): int { $this->computedRuns++; return $this->count * 2; }
    protected function updatingName(mixed $value): void { $this->log[] = "updating name to $value"; }
    protected function updatedName(mixed $value): void { $this->log[] = "updated name: $value"; }
    protected function updated(string $path, mixed $value): void { $this->log[] = "updated $path"; }
};
?>
<p>{{ $this->double }} {{ $this->double }}</p>
BLADE, 'features.blade.php');
$in = fn(Closure $f) => Closure::bind($f, $c, Component::class)();

// u-on modifiers
$el = element(['u-on:keydown.enter' => 'add(2)']);
$e = event(['key' => 'a', 'target' => $el]);
check('.enter: another key does nothing', [$in(fn() => $this->fire('u-on:keydown.enter', $el, $e)), $c->count], [false, 0]);
$e = event(['key' => 'Enter', 'target' => $el]);
check('.enter: Enter runs it', [$in(fn() => $this->fire('u-on:keydown.enter', $el, $e)), $c->count], [true, 2]);
$el = element(['u-on:submit.prevent.stop' => 'add']);
$e = event(['target' => $el]);
$in(fn() => $this->fire('u-on:submit.prevent.stop', $el, $e));
check('.prevent.stop', [$e->calls, $c->count], [['prevent', 'stop'], 3]);
$el = element(['u-on:click.self' => 'add']);
check('.self: from a child, no', $in(fn() => $this->fire('u-on:click.self', $el, event(['target' => element([])]))), false);
check('.self: from itself, yes', $in(fn() => $this->fire('u-on:click.self', $el, event(['target' => $el]))), true);
$el = element(['u-on:click.once' => 'add(10)']);
$in(fn() => $this->fire('u-on:click.once', $el, event(['target' => $el])));
$in(fn() => $this->fire('u-on:click.once', $el, event(['target' => $el])));
check('.once', $c->count, 14);
$el = element(['u-on:keydown.ctrl.s' => 'add']);
check('.ctrl.s without ctrl', $in(fn() => $this->fire('u-on:keydown.ctrl.s', $el, event(['key' => 's', 'target' => $el]))), false);
check('.ctrl.s with it', $in(fn() => $this->fire('u-on:keydown.ctrl.s', $el, event(['key' => 's', 'ctrlKey' => true, 'target' => $el]))), true);
fails('an unknown modifier', fn() => $in(fn() => $this->fire('u-on:click.sometimes', element(['u-on:click.sometimes' => 'add']), event())), "doesn't know the modifier .sometimes");

// u-model values
check('.number', $in(fn() => $this->valueOf(element([], ['value' => '4.5']), ['number'])), 4.5);
check('.number, not a number', $in(fn() => $this->valueOf(element([], ['value' => 'x']), ['number'])), 'x');
check('.boolean', $in(fn() => [$this->valueOf(element([], ['value' => 'true']), ['boolean']), $this->valueOf(element([], ['value' => '0']), ['boolean'])]), [true, false]);
check('a checkbox', $in(fn() => $this->valueOf(element([], ['type' => 'checkbox', 'checked' => true]), [])), true);
$in(fn() => $this->write('form.age', 42));
check('a path, typed by what was there', $c->form['age'], 42);
$in(fn() => $this->write('form.tags', ['a', 'b']));
check('an array (a multiple select)', $c->form['tags'], ['a', 'b']);
$c->errors()->add('name', 'The name field is required.');
$in(fn() => $this->write('name', 'Ada'));
check('hooks: updatingName, updatedName, updated (for every path)', $c->log, ['updated form.age', 'updated form.tags', 'updating name to Ada', 'updated name: Ada', 'updated name']);
check('an edit clears its errors', $c->errors()->has('name'), false);

// #[Computed]
$c->count = 5;
$render = fn() => trim($in(fn() => $this->html()));
check('computed: read as a property, once per render', [$render(), $c->computedRuns], ['<p>10 10</p>', 1]);
$c->count = 6;
check('computed: fresh next render', [$render(), $c->computedRuns], ['<p>12 12</p>', 2]);
fails('an undefined property', fn() => $c->nope, 'Undefined property');

// Promise: then() chains, as the host's can't.
use Unscripted\Ui\Promise;
$seen = [];
Promise::resolve(1)
    ->then(fn($v) => $v + 1)
    ->then(fn($v) => Promise::resolve($v * 10))
    ->then(function ($v) use (&$seen) { $seen[] = $v; throw new RuntimeException('bad'); })
    ->then(fn() => $seen[] = 'skipped')
    ->catch(function ($e) use (&$seen) { $seen[] = $e->getMessage(); return 'recovered'; })
    ->then(function ($v) use (&$seen) { $seen[] = $v; });
check('promise: values, promises, a throw caught, recovery', $seen, [20, 'bad', 'recovered']);
$late = new ReflectionClass(Promise::class)->newInstance();
$got = null;
$late->then(function ($v) use (&$got) { $got = $v; });
Closure::bind(fn() => $this->settle('fulfilled', 'later'), $late, Promise::class)();
check('promise: then() before it settles', $got, 'later');

// Validation in the browser: Laravel's common rules and messages.
use Unscripted\Ui\Rules;
check('rules: required', [Rules::check('  ', 'required', 'title'), Rules::check('x', 'required', 'title')], ['The title field is required.', null]);
check('rules: empty and not required passes', Rules::check('', 'max:3|email', 'title'), null);
check('rules: max, by kind', [Rules::check('abcd', 'max:3', 't'), Rules::check('9', 'numeric|max:5', 'n'), Rules::check([1, 2], ['max:1'], 'tags')],
    ['The t field must not be greater than 3 characters.', 'The n field must not be greater than 5.', 'The tags field must not have more than 1 items.']);
check('rules: min, between', [Rules::check('ab', 'min:3', 't'), Rules::check(7, 'integer|between:1,5', 'n')], ['The t field must be at least 3 characters.', 'The n field must be between 1 and 5.']);
check('rules: email, url, in, integer', [Rules::check('a@', 'email', 'e'), Rules::check('nope', 'url', 'u'), Rules::check('c', 'in:a,b', 'k'), Rules::check('1.5', 'integer', 'i')],
    ['The e field must be a valid email address.', 'The u field must be a valid URL.', 'The selected k is invalid.', 'The i field must be an integer.']);
check('rules: unknown ones pass', Rules::check('x', 'unique:todos|date', 't'), null);

$v = blade(<<<'BLADE'
<?php
use Unscripted\Ui\{Component, Server, State, Validate};
new class extends Component {
    #[State] public string $draft = '';
    #[State] public array $form = ['title' => '', 'email' => 'a@b.c'];
    #[Server] public static function create(#[Validate('required|max:5', as: 'draft')] string $todo_title): \Unscripted\Ui\ServerCall { return new \Unscripted\Ui\ServerCall('v', 'create', func_get_args()); }
    #[Server] public static function save(#[Validate(['title' => 'required', 'email' => 'email'], as: 'form')] array $form): \Unscripted\Ui\ServerCall { return new \Unscripted\Ui\ServerCall('v', 'save', func_get_args()); }
};
?>
BLADE, 'v.blade.php');
$vin = fn(Closure $f) => Closure::bind($f, $v, Component::class)();
check('validate(): all fields', [$vin(fn() => $this->validate()), $v->errors()->first('draft'), $v->errors()->first('form.title'), $v->errors()->has('form.email')],
    [false, 'The todo title field is required.', 'The title field is required.', false]);
$vin(fn() => $this->write('draft', 'too long'));
check('an edit checks the field: still wrong, a new message', $v->errors()->first('draft'), 'The todo title field must not be greater than 5 characters.');
$vin(fn() => $this->write('draft', 'ok'));
check('an edit checks the field: fixed', $v->errors()->has('draft'), false);
$vin(fn() => $this->write('form.email', 'nope'));
check('a form field', $v->errors()->first('form.email'), 'The email field must be a valid email address.');
check('validate(only)', [$vin(fn() => $this->validate(['draft'])), $v->errors()->has('form.title')], [true, true]);

done();
