# @mtjncn/ui

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
<script type="text/php" src="https://cdn.jsdelivr.net/npm/@mtjncn/ui@0.1/ui.php"></script>
<script src="https://cdn.jsdelivr.net/npm/@mtjncn/runtime@0.1/auto.js"
        data-php="https://cdn.jsdelivr.net/npm/@mtjncn/php84@0.1/php_pack.wasm"></script>
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
| `u-on:...` modifiers | `.stop`, `.self` (only from the element itself), `.once`, `.window` (anywhere on the page), `.outside` (a click outside the element), keys (`.enter`, `.escape`, `.space`, `.tab`, `.up`/`.down`/`.left`/`.right`, `.delete`, `.backspace`, or one character: `.k`), and `.ctrl`/`.shift`/`.alt`/`.meta`. Combine them: `u-on:keydown.ctrl.k`. An element can have several for one event |
| `u-model="draft"` | Two-way: the input shows `$this->draft`, and typing writes it back. Checkboxes bind to bools, radios to their value, `<select multiple>` to a list. Checkboxes, radios and selects update on change |
| `u-model="todos.3.done"` | A path into an array property |
| `u-model.blur` | Writes on change (leaving the field), not on each keystroke |
| `u-model.debounce.300ms` | Writes once typing has paused that long (250 ms without a number) |
| `u-model.number`, `.boolean` | Writes a number (`"4.5"` → `4.5`), or a bool (`"true"`, `"1"`, `"on"`, `"yes"`) |
| `u-model` on `<input type="file">` | Uploads the file as it's chosen (with unscripted/ui-server): see Uploads |
| `u-key="{{ $todo['id'] }}"` | Which item this is, so a re-render keeps each item's element when items are added, removed or reordered (an `id` attribute works too) |
| `u-loading="add"` | Shown only while `add` (an action or loader) waits on the server; with no value, while anything does. `u-loading.remove` hides instead |
| `u-offline` | Shown only while the browser is offline |
| `u-poll.5s="refreshStats"` | Calls the action every 5 s (`.500ms`, default 2 s) while the element is on the page |
| `u-init="load"` | Calls the action once, just after the element first appears |
| `u-confirm="Delete it?"` | With a `u-on` on the same element: asks first, and does nothing if the answer is no |
| `u-ignore` | Re-renders leave the element alone, children and attributes (for what other code manages, such as a chart) |
| `u-navigate` | On a link: goes to the page without reloading PHP (see Navigation) |
| `u-persist="player"` | Kept as it is across `u-navigate` page changes, where the next page has the same id |

## Component features

```php
use Unscripted\Ui\{Component, Computed, On, State, Url};

new class extends Component {
    #[State, Url(as: 'q')] public string $query = '';      // kept in ?q=...
    #[State] public array $items = [];

    #[Computed] public function count(): int { return count($this->items); }  // $this->count

    protected function mounted(): void {}                     // on the page, after its first render
    protected function unmounted(): void {}                   // taken off (a child its parent dropped)
    protected function rendering(): void {}                   // before each render
    protected function rendered(): void {}                    // after, once the DOM shows it
    protected function updatingQuery(string $value): void {}  // before u-model writes $query
    protected function updatedQuery(string $value): void {}   // after
    protected function updated(string $path, mixed $value): void {}   // after any u-model write
    protected function failed(ServerError $e): void {}        // a call failed and nothing caught it

    #[On('saved')] public function reload(?array $detail): void {}    // another component's dispatch
    public function save(): void { $this->dispatch('saved', ['id' => 3]); $this->redirect('/done'); }
};
```

- **`#[Computed]`** runs once per render, then keeps its result for the rest of it.
- **`#[Url]`** reads the property from the query string on mount (and on the server, with
  unscripted/ui-server), and writes it back with `history.replaceState`. It's left out while
  it has its default value.
- **Hooks** must be protected or public: Component calls them, and can't see private ones.
  The default `failed()` throws the error, to the console.
- **Events:** `dispatch('saved', $detail)` is a `CustomEvent` on window, so `#[On('saved')]`
  methods in any component on the page hear it, and so can JavaScript
  (`window.addEventListener('saved', e => e.detail)`). JavaScript can send one too:
  `window.dispatchEvent(new CustomEvent('saved', {detail: {...}}))`. And it can call an action:
  `root.dispatchEvent(new CustomEvent('unscripted:call', {detail: {action: 'save', args: []}}))`.
- **`promise()`:** the host's promises can't chain from PHP (a callback's return value is
  dropped), so wrap one: `promise(U::fetch($url))->then(fn($r) => $r->json())->then(fn($data) => ...)`.

## Validation

Rules go on the `#[Server]` method's parameters, where the server enforces them (see Server
methods): `#[Validate('required|max:100', as: 'draft')] string $title`. `as` keys the errors by
the u-model path the argument comes from, so editing that field updates its error at once: the
browser checks the rules it knows (required, string length and number sizes with min, max and
between, numeric, integer, email, url, in, boolean), with Laravel's messages. Others (unique,
exists, date...) are left to the server. `$this->validate()` checks every such field before a
call (`if (!$this->validate()) return;`).

A form is an array property and an array argument with rules by field:

```php
#[State] public array $form = ['title' => '', 'email' => ''];

#[Server]
public static function save(#[Validate(['title' => 'required', 'email' => 'email'], as: 'form')] array $form): void {}
```

```blade
<input u-model="form.title"> @error('form.title') {{ $message }} @enderror
```

## Child components

```blade
@foreach ($todos as $todo)
    @child('todo-item', ['todo' => $todo], $todo['id'])
@endforeach
```

`todo-item.blade.php` is a component of its own, fetched from beside this one's file. Its
public properties are set from the props, and again whenever they change; the rest of its state
is its own, and survives the parent's re-renders. The key tells children in a list apart. A
child its parent stops rendering is unmounted. With unscripted/ui-server, children are rendered
with their parent on the server. A child talks to its parent with `dispatch()` and `#[On]`.

Partials (Blade with no state) work through unscripted/ui-server: `@include('row', [...])` and
`<x-row :todo="$todo">slot</x-row>`, with `@props(['tone' => 'grey'])` for defaults.

## Streaming, uploads and navigation (with unscripted/ui-server)

**Streaming:** a `#[Server]` method that's a generator streams what it yields:
`$this->text = ''; $result = yield static::story($topic)->into('text');` appends each chunk to
`$text` as it arrives over HTTP, rendering each time, then gets the method's return value (or
the whole text). Over the Reverb socket, or in tests, the text comes all at once.

**Uploads:** `<input type="file" u-model="photo">` uploads the file as it's chosen. While it
goes, `$this->progress('photo')` is the percent done (and `loading('photo')` is true); then
`$photo` is `['id' => ..., 'name' => ..., 'size' => ..., 'type' => ..., 'url' => ...]` (a list
for `multiple`), `url` showing it to this visitor only. A server method takes it:

```php
#[Server] public static function save(array $photo): string
{
    return Upload::find($photo)?->store('avatars') ?? abort(422, 'Upload it again');
}
```

**Navigation:** `<a u-navigate href="/next">` fetches the page and swaps its body in, without
loading PHP again (the 5 MB pack stays). The head's styles and the title follow; components are
unmounted and the new page's mounted; back and forward work. `u-persist="id"` keeps an element
(an audio player, say), with any component in it, where the next page has the same id.

**Pagination** is a loader that watches a page number:
`#[Server, Watch(['query', 'page'])] public static function rows(string $query, int $page): array`,
returning `Todo::paginate(20, page: $page)->toArray()`. Buttons that change `$page` (an action
such as `next()`, or `u-model.number="page"`) load that page; `#[Url]` on `$page` keeps it in
the address.

## Server methods

With [`unscripted/ui-server`](../ui-server) on a Laravel app, a component's `#[Server]` methods
run on the server: the file the browser gets has a stub in each one's place. They're `static`
and get only their arguments, never the component's state, which the user controls.

```blade
<?php
use App\Models\Todo;
use Unscripted\Ui\{Component, Server, State, Validate, Watch};

new class extends Component {
    #[State] public string $query = '';
    #[State] public string $draft = '';
    #[State] public array $todos = [];

    // A loader: runs on mount and whenever $query changes, filling $todos.
    #[Server, Watch('query')]
    public static function todos(string $query): array
    {
        return Todo::where('title', 'like', "%$query%")->get(['id', 'title', 'done'])->toArray();
    }

    // The server checks the argument before the method runs.
    #[Server]
    public static function create(#[Validate('required|max:100')] string $title): array
    {
        return Todo::create(['title' => $title])->only('id', 'title', 'done');
    }

    #[Server]
    public static function remove(int $id): void
    {
        Todo::findOrFail($id)->delete();
    }

    // An action that waits for the server: `yield` is its `await`.
    // If create() fails validation, the action stops there and @error shows why.
    public function add(): Generator
    {
        yield static::create($this->draft);
        $this->draft = '';
    }
};
?>

<form u-on:submit.prevent="add">
    <input u-model="draft"> <button @disabled($this->loading('add'))>Add</button>
</form>
@error('title') <p>{{ $message }}</p> @enderror
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
| `#[Watch('query', debounce: 300)]` | Only once `$query` has stopped changing for 300 ms, as for a search box. `#[Watch(['query', 'page'])]` watches several, passed in that order |
| `u-on:click="remove(3)"` | A `#[Server]` method straight from the page; what it returns isn't kept |
| `$rows = yield static::search($q);` | In an action that returns `Generator`: waits for the call. `yield [static::a(), static::b()]` makes both in one request, getting both results |
| `#[Server(can: 'remove-todo')]` | The server checks the Laravel ability first (`Gate::authorize`), passing it the call's arguments: `Gate::define('remove-todo', fn(?User $user, int $id) => ...)`. Denied is a 403 |
| `#[Validate('required\|max:100')]` | On a `#[Server]` method's parameter: Laravel validation rules the server checks before the method runs (after `can`), keyed by the parameter's name. A missing argument is checked with its default; a variadic one's rules apply to each value |
| `@error('title') {{ $message }} @enderror` | Shows a field's first message from the error bag. A failed validation (a 422) fills it, replacing what was there; an action's next successful call empties it. `$errors` in a template and `$this->errors()` in the component are the bag: `has()`, `first()`, `get()`, `all()`, `any()`, `add()`, `clear()` |
| `catch (ServerError $e)` | A failed call, thrown into the action at its `yield`: `getMessage()`, `$e->status`, and `$e->errors` for a validation failure's messages by field. An action needn't catch a validation failure: if it doesn't, the action stops there and the error bag shows it. A loader's isn't put in the bag |
| `#[Server, Watch, Live('todos')]` | Runs the loader again on every page that has it when the server calls `Live::changed('todos')` (ui-server). Pages hear over ui-server's `reverb` transport, or else ask every `poll` ms (5000 by default). `#[Watch]` with no properties loads once on mount |
| `#[Live('game')]` on a browser method | Called with the data on every page when the server calls `Live::send('game', $data)` (ui-server), then renders. Needs the `reverb` transport |
| `$this->loading('add')` | Whether that action or loader is waiting on the server; `$this->loading()` for any |

After an action's server calls are done (a direct call's too), every loader runs again, so what
the page shows is what the server has. An action or loader that starts again while it's still
waiting replaces the earlier one: the old result is dropped, so typing quickly into a search
shows the last query's results, not the slowest. The server still does what the dropped calls
asked.

**Every public `#[Server]` method is a public HTTP endpoint**, whatever the template does with
it: anyone can call it with any arguments. Check authorisation, with `can:` or in the method,
and validate arguments, with `#[Validate]` or in the method. Return arrays and scalars, picking the columns
(`->get(['id', 'title'])`, `->only(...)`), so nothing the browser shouldn't see goes with it.
`private` and `protected` `#[Server]` methods are server-only helpers, sent to the browser not
even as stubs. The file's other code is sent as written: `use` lines name your classes.

## Rules

- **Children are separate:** a component's handlers stop at its children's roots, so a
  child's buttons call the child's actions.
- **The browser's PHP is 32-bit:** an `int` stops at 2,147,483,647 there, while the server's
  goes much higher. A larger number from JSON arrives as a `float`, and `(int)` of one wraps
  around. Keep timestamps in milliseconds as floats (`microtime(true) * 1000`), and watch for
  ids or counts that could pass two billion.
- **`mounted()`:** override the protected method to run code once the component is on the
  page, after its first render, such as starting a timer (`U::setInterval(fn() => $this->refresh(), 250)`).

- **Escape what you print.** Blade's `{{ }}` does; `{!! !!}` and `<?= ?>` don't.
- **Actions** are the component's own public, non-static methods. `u-on` can't call
  `Component`'s methods (`mount`, `refresh`), private ones or magic ones.
- **`u-model` writes only public `#[State]` properties**, and not ones that are `readonly` or
  `private(set)`: `#[State] public private(set) array $rows` can be shown but not bound.
- **State changed outside an action** (in a timer or a fetch callback) needs
  `$this->refresh()` afterwards.

## How it renders

Each render patches the DOM in place with `@mtjncn/runtime`'s `morph`: elements that are
still there are kept and only what changed is updated, so focus, the caret, scroll positions and
anything else on them survive. Give list items a `u-key` so they're matched by identity rather
than position. Events are delegated: one listener per event type on the root, not one per
element.

On a host without `morph` (Unscripted's browser, for now), each render replaces the root's HTML
instead, keeping only a bound input's focus and caret.

Served by `unscripted/ui-server`, a component can be rendered on the server first
(`@unscripted('name')`): the page arrives with its HTML, and `mount()` picks up from the state
it was rendered with, patching that HTML rather than loading again. See ui-server's README.

## License

MIT OR Apache-2.0 (`LICENSE-MIT`, `LICENSE-APACHE`).
