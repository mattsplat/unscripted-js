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
  that refill a property when the properties they watch change. Loaders run again after an
  action's server calls; a flow started again drops the earlier one's result; failures arrive
  as `ServerError`; `$this->loading()`. A component served with its `#[Server]` methods as
  written refuses to mount.
- `unscripted/ui-server` 0.1.0 (Composer, Laravel 12 and 13): serves `resources/components`
  with `#[Server]` method bodies replaced by stubs, and calls them at `POST /unscripted/call`,
  in the `web` middleware group.
