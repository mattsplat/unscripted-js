# Changelog

## Unreleased

### Added

- `@unscripted/runtime` 0.1.0: runs language packs against the real DOM. `load(name, wasmUrl)`
  and `runPage({name: wasmUrl})` as a module; `auto.js` as a drop-in script configured with
  `data-<pack>` attributes. Speaks host API 2 (`packs/HOST_API` in Unscripted) and refuses
  packs built for another.
- `@unscripted/php84` 0.1.0 (PHP 8.4.27) and `@unscripted/php82` 0.1.0 (PHP 8.2.35): one package
  per PHP minor version, each with PHP's license and notice and a `phpVersion` export.
- `npm run serve`: a dependency-free static server for the examples.
- Rust programs: the runtime runs a page's `<script type="application/rust" src="app.wasm">`,
  each its own instance, started with `rust_main`.
- `examples/php-snake`: the PHP Snake game running through the runtime.
- The runtime's `morph(root, html)`, also `window.unscriptedMorph`: patches a subtree to match
  new HTML in one request, keeping the elements that stay.
- `@unscripted/ui` 0.1.0: reactive components for PHP 8.4. `#[State]` properties, public methods
  as actions (`u-on:<event>`, with JSON arguments and `.prevent`), two-way `u-model` (paths into
  arrays too), rendering after every action by patching the DOM in place (`u-key` matches list
  items), so focus, the caret and scroll positions survive; replacing innerHTML where the host
  has no `unscriptedMorph`, keeping bound inputs' focus. Components are single Blade files (`<script type="text/blade" src="x.blade.php" data-mount="#app">`: the
  class in a PHP block, the template under it), compiled in the browser from a Blade subset,
  with errors naming the file and line; or classes with a `render()` in PHP's own template
  syntax. `examples/ui-todo` (Blade) and `examples/ui-counter` (PHP templates) use it.
- `@unscripted/ui` server calls: `#[Server]` static methods run on the server, called from a
  generator action (`$rows = yield static::search($q);`, several at once with
  `yield [...]`), straight from the page (`u-on:click="remove(3)"`), or as `#[Watch]` loaders
  that refill a property when the properties they watch change (`debounce: ms` to wait for a
  pause). Loaders run again after an action's server calls; a flow started again drops the
  earlier one's result; failures arrive as `ServerError`; `$this->loading()`.
  `#[Server(can: 'ability')]` has the server check a Laravel Gate first. A component served
  with its `#[Server]` methods as written refuses to mount.
- `unscripted/ui-server` 0.1.0 (Composer, Laravel 12 and 13): serves `resources/components`
  with `#[Server]` method bodies replaced by stubs, and calls them at `POST /unscripted/call`,
  in the `web` middleware group.
- Server-side rendering: `@unscripted('todos')` in a Laravel view renders the component on the
  server (loaders first) with `ui.php` itself, and sends its state with the HTML. In the
  browser, `mount($selector, $state)` takes that state, doesn't run the loaders again, and
  patches the server's DOM. ui-server serves its `ui.php` at `/unscripted/ui.php`, and
  `ui.php` only mounts the page when it's running in one.
- Server calls over a WebSocket, opt-in: `unscripted.transport = 'reverb'` sends a page's calls
  over one socket to Laravel Reverb, where ui-server answers them (as the user whose private
  channel the socket joined).
- Validation: `#[Validate('required|max:100')]` on a `#[Server]` method's parameter has
  ui-server check the argument with Laravel's validator before the method runs. A 422 fills the
  component's error bag (`$errors`, `$this->errors()`, `@error('title') ... @enderror`), and an
  action that doesn't catch it just stops.
- Live updates: `#[Live('key')]` on a loader reruns it on every page when the server calls
  `Live::changed('key')` (ui-server, over the 'reverb' transport's socket). `#[Watch]` with no
  properties, and a `mounted()` hook.
- `@unscripted/ui` features: u-on modifiers (`.stop`, `.self`, `.once`, `.window`, `.outside`,
  keys, `.ctrl`...), u-model modifiers (`.blur`, `.debounce`, `.number`, `.boolean`) and
  `<select>`; `u-loading`, `u-offline`, `u-poll`, `u-init`, `u-confirm`, `u-ignore`; lifecycle
  hooks (`mounted`, `unmounted`, `rendering`/`rendered`, `updating`/`updated`, `failed`);
  `#[Computed]`, `#[Url]`, `#[On]` and `dispatch()` (events between components and with
  JavaScript); `redirect()`; `promise()`, chainable promises over the host's.
- Child components: `@child('name', [props], $key)`, rendered with the parent on the server.
- Validation: `as:`, rules by field for forms, the browser checking the rules it knows as a
  field is edited, `$this->validate()`.
- `unscripted/ui-server`: partials (`@include`, `<x-...>`, `@props`), `@unscripted` options
  (`id`, `lazy`), full pages (`Route::unscripted()`), streaming generator methods
  (`->into()`), uploads (`Upload::find()`), a test helper (`Components::test()`), and u-model
  values in the server render.
- Navigation: `u-navigate` and `u-persist`, changing pages without loading PHP again.
- The runtime: `u-ignore` in `morph`, and `new` for any capitalised window function
  (`CustomEvent`, `FormData`). Morph tests in a browser: `tests/morph.html`.
