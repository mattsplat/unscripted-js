# @unscripted/ui roadmap

Where `@unscripted/ui` and `unscripted/ui-server` are, and what's left. Not shipped in the npm
package.

## Where things are

| | |
|---|---|
| `packages/ui/ui.php` | The framework, run in the page: Component, Blade compiler, events, u-model, server flows |
| `packages/runtime/index.js` | `morph(root, html)`, also `window.unscriptedMorph` |
| `packages/ui-server/` | Composer package for Laravel: strips `#[Server]` methods, `POST /unscripted/call`, `@unscripted('name')` server rendering, `Route::unscripted()` |
| `packages/ui-server/src/` | `Components` (strip, call, render, partials, test), `SocketCalls` (Reverb), `Live`, `Upload`, `ComponentTest` |
| `packages/ui-server/src/ui.php` | A copy of `packages/ui/ui.php` for the server: `npm run sync-ui` after changing the original (`npm test` and `composer test` fail if they differ) |
| `tests/` | `npm test` (Node 24+, `nvm use 26`): PHP tests run in the pack. `tests/morph.html`: the morph, in a browser (`npm run serve`) |
| `packages/ui-server/tests/` | `composer test` there: stripping, calls, rendering, partials, the test helper, no Laravel |
| `examples/ui-todo`, `examples/ui-counter` | Static examples (`npm run serve`) |
| `../unscripted-laravel-demo` | Laravel 13 demo (outside this repo, not in git). `php artisan serve --port=8010` and `php artisan reverb:start` (port 8081); `/` todos, `/spread` and `/voxel` the games, `/features` and `/counters` exercise the features; `php artisan test` runs `tests/Feature/TodosComponentTest.php`; `php artisan migrate:fresh --seed` resets the data. Needs this checkout beside it |

Work is on the `ui-framework` branch.

## Done

1. **Core:** `#[State]`, `u-on` (delegated, `.prevent`), `u-model` (paths, checkboxes, radios), a
   render after each event.
2. **Blade:** single-file components compiled in the browser, a Blade subset, errors that name
   the file and line.
3. **Morph:** the DOM is patched in place, with `u-key`; replaces innerHTML where the host has no
   morph (Unscripted's own browser).
4. **Server methods:**
   - `#[Server]` static methods, stripped by ui-server.
   - Called by `yield` in generator actions (with `yield [...]` for several), straight from
     `u-on`, or as `#[Watch]` loaders (with `debounce`).
   - A newer flow drops the older one's result; `ServerError`; `loading()`.
   - Loaders run again after an action's server calls.
   - `#[Server(can:)]` checks a Laravel Gate.
   - The server loads compiled files, not `eval`.
5. **Server-side rendering:**
   - `@unscripted('todos')` renders on the server with the real `ui.php` (through
     `blade($source, $file, $load)`, whose loader writes compiled files), loaders first via
     `Components::call` (so `can` applies). Its output is `<div id="unscripted-todos">` and a
     `<script data-state>`.
   - `mount($selector, $state)` adopts the state, marks loaders as run, and morphs the server's
     DOM. Checked in the Laravel demo: no `/unscripted/call` on load, and the same nodes are
     kept.
   - ui-server serves `/unscripted/ui.php`, so the browser and server use the same version.
6. **Calls over Reverb (opt-in):** `unscripted.transport = 'reverb'`. ui.php's `Socket` speaks
   enough of the Pusher protocol to join `private-unscripted.<id>` (signed by
   `/broadcasting/auth`); ui-server's `SocketCalls` answers `unscripted:call` messages from
   Reverb's `MessageReceived`, logged in as that channel's user for the message. Checked in the
   demo as a guest and logged in, with errors (422, 403, 404) and Reverb restarting.
7. **Validation:** `#[Validate('rules')]` on `#[Server]` method parameters, checked by
   ui-server (after `can`) through an injected validator closure. A 422 fills the component's
   `ErrorBag` (`$errors`, `@error`); an action's uncaught 422 is swallowed; a loader's isn't.
8. **Live updates and a game:** `#[Live('key')]` loaders, `Live::changed('key')` (published
   in-process inside Reverb, since `broadcast()` there would wait on itself), `mounted()`, and
   `#[Watch]` with no properties. The demo's `/spread` is a multiplayer game built on them,
   checked with two players in two tabs.
9. **Batch 1 of the feature ideas** (checked in the demo's `/features` page and
   `tests/features.test.php`):
   - u-on modifiers: `.stop`, `.self`, `.once`, `.window`, `.outside`, keys (`.enter`,
     `.escape`, `.a`, ...) and `.ctrl`/`.shift`/`.alt`/`.meta`; several per element and event.
   - u-model modifiers: `.blur`, `.debounce.300ms`, `.number`, `.boolean`; `<select>` and
     `<select multiple>`; checkboxes, radios and selects read on `change` (one render per
     click, and a checkbox isn't reset by another click handler's render).
   - Hooks: `mounted()`, `rendering()`/`rendered()`, `updating{Prop}`/`updated{Prop}`/
     `updated()`, `failed(ServerError)`; `redirect()`; an edit clears its path's errors.
   - `#[Computed]`, `#[Url]` (server render included), `#[On]` and `dispatch()` (between
     components, and to and from JavaScript as window CustomEvents).
   - `u-loading` (`.remove`), `u-offline`, `u-poll.Nms`, `u-init`, `u-confirm`, `u-ignore`
     (in the runtime's morph); `#[Live]` polls (`poll:` ms) without the 'reverb' transport.
   - `@unscripted('name', ['id' => ..., 'lazy' => true, 'placeholder' => ...])`, and the call
     endpoint sent as `data-endpoint`.
   - Morph tests in a browser: `tests/morph.html`.
   - The runtime makes any capitalised window function with `new` (`CustomEvent`, `FormData`).
   - Component's private helpers no longer reserve their names from actions.
10. **Batch 2:**
   - `fire()`, interim: `promise($hostPromise)->then()->then()` (`Unscripted\Ui\Promise`) chains;
     the HOST_API 3 fix is still to do in Unscripted.
   - Validation: `#[Validate(rules, as: 'draft')]`, rules by field for a form (array) argument
     (`form.title`), messages naming the parameter; the browser checks the rules it knows
     (`Rules`, Laravel's common ones and messages) as a field is edited, and
     `$this->validate()` checks them all before a call. The pack has no filter extension, so
     email/url/integer are patterns.
   - Testing: `app(Components::class)->test('todos')` (`ComponentTest`): set, call, assertSee,
     assertSet, assertHasErrors, assertDispatched, assertRedirect, assertFailed. Built from
     the browser's stubbed source, so calls go through `call()`. Checked under PHPUnit in the
     demo (`tests/Feature/TodosComponentTest.php`).
   - Partials: ui-server inlines `@include('row', [...])` and `<x-row :a="$b">slot</x-row>`
     (files in the components folder with no PHP block; `@props` defaults), each run in its
     own function. Lines shift below one, so errors there are approximate.
   - Nested components: `@child('counter', [props], $key)`. Fetched from beside the parent's
     file and mounted into a placeholder the parent's morph leaves alone (`u-ignore`); props
     passed down when they change; unmounted (`unmounted()` hook, timers stopped) when gone;
     rendered with the parent on the server, state included. A component's handlers stop at
     its children's roots. Checked in the demo's `/features` (counters).
   - The server render fills u-model elements' value/checked/selected/text from state
     (PHP 8.4's `Dom\HTMLDocument`).
   - `u-navigate` links (`Navigator`): fetch the page, swap the body, the head's styles and the
     title, unmount and mount, push history, handle back; `u-persist="id"` elements (and their
     components) carried over. PHP isn't loaded again. Checked in the demo.
   - Full pages: `Route::unscripted('/counters', 'counters', ['title' => ...])`, ui-server's
     `unscripted::page` view or your `layout` (given `$slot`, `$title`); `unscripted.runtime`
     and `unscripted.pack` config for the script URLs.
   - JavaScript calls an action: `root.dispatchEvent(new CustomEvent('unscripted:call',
     {detail: {action, args}}))`.
   - Streaming: a generator `#[Server]` method, `yield static::story($t)->into('text')`: over
     HTTP an NDJSON response, each chunk appended and rendered as it comes; over the socket and
     in tests run through (`{"unscripted:stream": {text, result}}`, unpacked by `send()`).
     Reverb, like Octane, must be restarted after code changes.
   - File uploads: `u-model` on a file input uploads each file as it's chosen (XHR, with
     `$this->progress('photo')` and `loading()`), to ui-server's `POST /unscripted/upload`
     (`unscripted.upload_rules`); the property holds `{id, name, size, type, url}` (a list for
     `multiple`); a `#[Server]` method takes it with `Upload::find($photo)->store('dir')`. Kept an
     hour, findable only by the session or user that uploaded it (so over Reverb, a logged-in
     user's only). Checked in the demo's `/features`.
11. **`Live::send('key', $data)`:** the data itself to every page's `#[Live('key')]` browser
    method (not a loader), over the socket only; a loader with the same key reruns after a
    reconnect. Used by the demo's `/voxel`: a 3D block world in Rust (`voxel/`, wasm-bindgen
    and WebGL 2, `npm run build:voxel`, into `public/wasm/voxel`) that talks to its PHP component through CustomEvents
    one way and `window.voxel` the other. Checked by hand.

## Next

1. **`fire()`, the real fix:** the interim `promise()` wrapper is in `ui.php` (done, above).
   What's left is the host contract change, `HOST_API` 2 → 3, so a PHP callback's return value
   reaches the next `then()` of the host's own promises. It touches Unscripted's `prelude.php`
   (`__fire`), `pack.c` (`php_fire`), `browser/src/interpreter.rs` and `page_document.rs`,
   needs both PHP packs rebuilt, and needs the runtime's `fire()` here. Wait until
   `browser-test`'s uncommitted work (`page_document.rs`) is committed.

## Left on purpose, or out of this repo

- **A 64-bit pack** (wasm64 or memory64): the browser's PHP `int` is 32-bit (documented in the
  README's Rules). A pack build change, in Unscripted.
- **Browsers:** tested in Chromium only (the browse tool is Chromium). Try Firefox and Safari
  before a release.
- **Symfony support:** ui-server is Laravel-only; `Components` itself mostly isn't.
- **Page layouts** (`@extends`/`@section`) aren't for components: pages are Laravel views, or
  `Route::unscripted()` with a `layout`.
- **`u-dirty` and `u-transition`:** skipped. "Dirty" needs a definition here (state differs
  from what?), and transitions are CSS's job.

## Smaller work

- **No automated tests for the socket:** `SocketCalls`, `#[Live]` and the browser's `Socket`
  need Reverb; they were checked by hand in the demo (two-player game, calls as guest and as a
  user, Reverb restarting).
- **Browser features are checked by hand:** `/features`, `/counters`, `/spread` in the demo,
  through the browse tool. `tests/features.test.php` covers what runs without a DOM, and
  `tests/morph.html` the morph in a browser; nothing runs the demo pages automatically.
- **Reverb:** installing it moved the demo's Guzzle from 8 back to 7 (Reverb 1.12 needs
  `guzzlehttp/psr7` ^2). A slow method blocks its one event loop for everyone. Restart it
  after code changes. Uploads over it work for logged-in users only (no session).
- **The 32-bit clock glitch:** while testing the game, a page once showed a timer computed from
  a wrapped clock after the fix. Not seen again in four fresh runs; likeliest a page loaded
  before the fix, unconfirmed.
- **SSR:** state must be JSON-shaped; the page still downloads the 5 MB pack before it's
  interactive (`u-navigate` avoids it after the first page).
- **Leaving early only drops the result:** a flow started again discards the earlier one's
  result, but the server still did the work (an `AbortController` could cancel HTTP ones).
- **Partials shift line numbers** below them, so errors there point near, not at, the line.
- **What reaches the browser:** client methods, `use` lines (which name app classes), the
  template and `#[Validate]` rules are sent as written. Default values of `#[Server]` method
  parameters are also copied into the stub.
- **Argument errors are recognised by message text:** ui-server tells argument TypeErrors from
  errors inside the method by the message and the declaration line. That could break if PHP
  rewords its messages.
- **Browser validation is a subset:** email, url and integer are patterns (the pack has no
  filter extension), looser than Laravel's; the server checks everything again.

## Decisions so far

- **PHP 8.4 only:** no 8.2 support. Property hooks and the like are fine to use.
- **Generators, not Fibers:** the packs build without Fibers (WASI has no stack switching).
  Asyncify or JSPI could bring them back later.
- **A render after every event, Livewire-style:** no signals.
- **Server methods are `static` and get only their arguments:** client state is under the
  user's control.
- **Calls over a socket are opt-in, through Reverb:** HTTP stays the default. Octane can't
  route WebSocket messages, so Reverb's process runs them; the user comes from the private
  channel the socket joined.
- **ui-server parses with PHP's tokenizer,** not nikic/php-parser: no dependency.
- **The server's `ui.php` is a synced copy,** not a shared source: Packagist and npm get one
  file each, and tests catch drift.
