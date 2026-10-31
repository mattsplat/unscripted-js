# @unscripted/ui

Reactive components for [Unscripted](https://github.com/mattsplat/unscripted) PHP. A component
is a class: its `#[State]` properties are the data, its public methods are actions, and its
template is a Blade file. After every action it renders again, so the page always shows the
state. PHP 8.4.

**`greeter.blade.php`**: the class in a PHP block at the top, the template under it.

```blade
<?php
use Unscripted\Ui\{Component, State};

new class extends Component {
    #[State] public string $name = '';
    #[State] public int $waves = 0;

    public function wave(int $times = 1): void
    {
        $this->waves += $times;
    }
};
?>

<input u-model="name" placeholder="Your name">
<p>Hello, {{ $name ?: 'stranger' }}! Waved {{ $waves }} times.</p>
<button u-on:click="wave">Wave</button>
<button u-on:click="wave(10)">Wave ten times</button>
```

**The page:**

```html
<div id="app"></div>
<script type="text/blade" src="greeter.blade.php" data-mount="#app"></script>
<script type="text/php" src="https://cdn.jsdelivr.net/npm/@unscripted/ui/ui.php"></script>
<script src="https://cdn.jsdelivr.net/npm/@unscripted/runtime/auto.js"
        data-php="https://cdn.jsdelivr.net/npm/@unscripted/php84/php_pack.wasm"></script>
```

`ui.php` mounts each `<script type="text/blade">` on its `data-mount` element. The Blade can
also be inline in the script tag instead of in a `src` file.

**Serve `.blade.php` files from a static server** (a CDN, `npm run serve`). A server set up to
run `.php` files (Apache with mod_php, nginx with PHP-FPM, `php -S`) would run them instead of
sending them to the browser.

## Blade

The template's variables are the component's public properties, and `$this` works too,
private methods included. Supported:

| | |
|---|---|
| Echo | `{{ $x }}` (escaped), `{!! $html !!}` (not), `{{-- comment --}}`, `@{{ shown as written }}` |
| Conditions | `@if` / `@elseif` / `@else` / `@endif`, `@unless`, `@isset`, `@empty(...)` |
| Loops | `@foreach`, `@forelse` / `@empty` / `@endforelse`, `@for`, `@while`, `@break`, `@continue` (with or without a condition) |
| Attributes | `@class(['done' => $t['done'], 'item'])`, `@checked(...)`, `@selected(...)`, `@disabled(...)`, `@readonly(...)`, `@required(...)` |
| Other | `@php ... @endphp`, `@php(...)`, `@json(...)` (safe inside `<script>`), `@verbatim`, `@@` for a literal `@` |

Other `@words` are left as written, so emails, CSS `@media` and the like are safe. Not yet:
layouts, `@include`, components (`<x-...>`), `@props`.

Errors name the file and line: `Division by zero in todo-app.blade.php:58`.

## Without Blade

A named class with a `render()` method that prints its HTML, in PHP's own template syntax,
mounted from the page's own PHP:

```php
<?php
use Unscripted\Ui\{Component, State};
use function Unscripted\Ui\e;

final class Greeter extends Component
{
    #[State] public string $name = '';

    protected function render(): void { ?>
        <input u-model="name" placeholder="Your name">
        <p>Hello, <?= e($this->name ?: 'stranger') ?>!</p>
    <?php }
}

new Greeter()->mount('#app');
```

Here `<?= ?>` doesn't escape: use `<?= e($value) ?>` for anything a user typed.

## In the HTML

| Attribute | Does |
|---|---|
| `u-on:click="save"` | Calls `$this->save()` on click. Any event type works: `u-on:keydown`, `u-on:submit`... |
| `u-on:click="remove(3)"` | With arguments, written as JSON literals: `show("done")`, `move(1, -1)` |
| `u-on:submit.prevent="add"` | Calls `$event->preventDefault()` first |
| `u-model="draft"` | Two-way: the input shows `$this->draft`, and typing writes it back. Checkboxes bind to bools, radios to their value |
| `u-model="todos.3.done"` | A path into an array property |
| `u-key="{{ $todo['id'] }}"` | Which item this is, so a re-render keeps each item's element when items are added, removed or reordered (an `id` attribute works too) |

## Server methods

With [`unscripted/ui-server`](../ui-server) on a Laravel app, a component's `#[Server]` methods
run on the server: the file the browser gets has a stub in each one's place. They're `static`
and get only their arguments, never the component's state, which the user controls.

```blade
<?php
use App\Models\Todo;
use Unscripted\Ui\{Component, Server, ServerError, State, Watch};

new class extends Component {
    #[State] public string $query = '';
    #[State] public string $draft = '';
    #[State] public array $todos = [];
    public ?string $error = null;

    // A loader: runs on mount and whenever $query changes, filling $todos.
    #[Server, Watch('query')]
    public static function todos(string $query): array
    {
        return Todo::where('title', 'like', "%$query%")->get(['id', 'title', 'done'])->toArray();
    }

    #[Server]
    public static function create(string $title): array
    {
        $valid = validator(['title' => $title], ['title' => 'required'])->validate();
        return Todo::create($valid)->only('id', 'title', 'done');
    }

    #[Server]
    public static function remove(int $id): void
    {
        Todo::findOrFail($id)->delete();
    }

    // An action that waits for the server: `yield` is its `await`.
    public function add(): Generator
    {
        try {
            yield static::create($this->draft);
            $this->draft = '';
        } catch (ServerError $e) {
            $this->error = $e->errors['title'][0] ?? $e->getMessage();
        }
    }
};
?>

<form u-on:submit.prevent="add">
    <input u-model="draft"> <button @disabled($this->loading('add'))>Add</button>
</form>
@if ($error) <p>{{ $error }}</p> @endif
<input type="search" u-model="query">
@foreach ($todos as $todo)
    <p u-key="{{ $todo['id'] }}">{{ $todo['title'] }}
        <button u-on:click="remove({{ $todo['id'] }})">×</button></p>
@endforeach
```

Served from `/unscripted/components/todos.blade.php`, the component's route, not a static file.
The page needs Laravel's `<meta name="csrf-token" content="{{ csrf_token() }}">`.

| | |
|---|---|
| `#[Server, Watch('query')]` | A loader: called with the watched properties' values on mount and when they change, its result put in the property of its name (`$todos`) |
| `u-on:click="remove(3)"` | A `#[Server]` method straight from the page; what it returns isn't kept |
| `$rows = yield static::search($q);` | In an action that returns `Generator`: waits for the call. `yield [static::a(), static::b()]` makes both in one request, getting both results |
| `catch (ServerError $e)` | A failed call, thrown into the action at its `yield`: `getMessage()`, `$e->status`, and `$e->errors` for a validation failure's messages by field |
| `$this->loading('add')` | Whether that action or loader is waiting on the server; `$this->loading()` for any |

After an action's server calls are done (a direct call's too), every loader runs again, so what
the page shows is what the server has. An action or loader that starts again while it's still
waiting replaces the earlier one: the old result is dropped, so typing quickly into a search
shows the last query's results, not the slowest. The server still does what the dropped calls
asked.

**Every public `#[Server]` method is a public HTTP endpoint**, whatever the template does with
it: anyone can call it with any arguments. Check authorisation (`Gate::authorize`, `auth()`)
and validate arguments in the method itself. Return arrays and scalars, picking the columns
(`->get(['id', 'title'])`, `->only(...)`), so nothing the browser shouldn't see goes with it.
`private` and `protected` `#[Server]` methods are server-only helpers, sent to the browser not
even as stubs. The file's other code is sent as written: `use` lines name your classes.

## Rules

- **Escape what you print.** Blade's `{{ }}` does; `{!! !!}` and `<?= ?>` don't.
- **Actions** are the component's own public, non-static methods. `u-on` can't call
  `Component`'s methods (`mount`, `refresh`), private ones or magic ones.
- **`u-model` writes only public `#[State]` properties**, and not ones that are `readonly` or
  `private(set)`: `#[State] public private(set) array $rows` can be shown but not bound.
- **State changed outside an action** (in a timer or a fetch callback) needs
  `$this->refresh()` afterwards.

## How it renders

Each render patches the DOM in place with `@unscripted/runtime`'s `morph`: elements that are
still there are kept and only what changed is updated, so focus, the caret, scroll positions and
anything else on them survive. Give list items a `u-key` so they're matched by identity rather
than position. Events are delegated: one listener per event type on the root, not one per
element.

On a host without `morph` (Unscripted's browser, for now), each render replaces the root's HTML
instead, keeping only a bound input's focus and caret.

## License

MIT OR Apache-2.0 (`LICENSE-MIT`, `LICENSE-APACHE`).
