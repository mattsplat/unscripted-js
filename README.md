# unscripted-js

Run pages written for [Unscripted](https://github.com/mattsplat/unscripted) in any browser.

Unscripted is an experimental browser where JavaScript is one language among several: pages can
be written in PHP, Python, Rust or Dart, each running as WebAssembly. This repository makes
those pages work in Firefox, Chrome and Safari too, with a small JavaScript runtime that plays
the part Unscripted's browser plays.

```html
<p>Clicked <span id="count">0</span> times</p>
<button id="add">Add one</button>

<script type="text/php">
<?php
use Unscripted as U;
$clicks = 0;
U::document()->querySelector('#add')->addEventListener('click', function () use (&$clicks) {
    U::document()->querySelector('#count')->textContent = (string) ++$clicks;
});
</script>

<script src="https://cdn.jsdelivr.net/npm/@unscripted/runtime/auto.js"
        data-php="https://cdn.jsdelivr.net/npm/@unscripted/php84/php_pack.wasm"></script>
```

> **Status: experiment, not yet published to npm.** The package names are placeholders.

## Packages

| Package | What it is | Size |
|---|---|---|
| [`@unscripted/runtime`](packages/runtime) | The runtime: answers a pack's requests against the real DOM. `auto.js` for a plain `<script>` tag, `index.js` as a module. | ~12 KB |
| [`@unscripted/php84`](packages/php84) | PHP 8.4 as a language pack | 4.7 MB (~1.5 MB compressed) |
| [`@unscripted/php82`](packages/php82) | PHP 8.2 as a language pack | 4.5 MB (~1.5 MB compressed) |
| [`@unscripted/ui`](packages/ui) | Reactive components for PHP 8.4, in single Blade files: `ui.php`, loaded as a page script | ~29 KB |
| [`unscripted/ui-server`](packages/ui-server) (Composer) | The server side of `@unscripted/ui`, for Laravel: `#[Server]` methods that run on the server, called from components | |

A page only downloads the packs it uses: the runtime loads a pack when the page has scripts
for it.

**PHP versions.** Each PHP minor version is its own package, because PHP's minors differ
(code written for 8.2 can behave differently on 8.4). Pick the line your code is written for:
its patch releases bring that line's PHP security fixes, and `phpVersion` says the exact
release. A line's package is deprecated when the PHP project stops supporting it.

**Rust** needs no pack: a Rust page's `<script type="application/rust" src="app.wasm">` is its
own compiled program (built on Unscripted's `unscripted` crate), which the runtime starts.
More languages (Python, a sandboxed JS) use the same runtime.

## How it works

A language pack is one WebAssembly module: the language's interpreter plus a thin bridge. It
reaches the page through a single import, `host.request`, carrying JSON:
`{"op": "call", "target": 3, "name": "querySelector", "args": ["#count"]}`. Its other imports
are a few WASI functions (clock, random bytes, output), and it has no files, network or
environment.

In Unscripted, the browser answers those requests. Here `@unscripted/runtime` does, against the
browser's real DOM: it keeps a table of page objects by number, carries out each
`get`/`set`/`call`, and turns callbacks into functions that call back into the pack. The
protocol is described in Unscripted's
[pack contract](https://github.com/mattsplat/unscripted/blob/main/docs/packs.md).

## Browser support

Recent Firefox, Chrome and Safari: the PHP pack needs WebAssembly exception handling
(exnref). Pages must be served over http(s); browsers don't load `.wasm` from `file://`.

Tested so far in headless Chromium only.

## What's different from Unscripted

The page's code runs as any page's JavaScript does. Unscripted's own protections aren't here:
its network gate, the 5-second time limit, the per-page memory cap, recording and replay. Code
written for Unscripted works here (browsers offer everything Unscripted does), but code written
for a normal browser may use APIs Unscripted doesn't have yet.

## Development

The packs are built in the Unscripted repo. Packing copies them in from there:
`$UNSCRIPTED_REPO`, or `../browser-test` beside this folder.

```sh
(cd ../browser-test && packs/php/build.sh)                       # PHP 8.4, once
(cd ../browser-test && PHP_VERSION=8.2.35 packs/php/build.sh)    # PHP 8.2, once
npm run pack       # copies each line's pack in and writes dist/*.tgz
npm run serve      # serves this folder at http://localhost:8000/ (Node; any static server works)
```

To try it from other devices on the same network (a phone, another laptop), serve to the
network: `HOST=0.0.0.0 npm run serve` prints the address to open there. Anyone on that network
can read the folder while it runs.

Then open <http://localhost:8000/examples/php-snake/>: the PHP Snake game (a copy of
Unscripted's `examples/php-snake`), running through the runtime.

See [CONTRIBUTING.md](CONTRIBUTING.md) for more, and [CHANGELOG.md](CHANGELOG.md) for changes.

## License

The runtime and Unscripted's bridge code are licensed under either of
[Apache License, Version 2.0](LICENSE-APACHE) or [MIT license](LICENSE-MIT), at your option.

`@unscripted/php84` and `@unscripted/php82` also contain PHP, under the PHP License 3.01. This product includes PHP
software, freely available from <http://www.php.net/software/>. See
[packages/php84/NOTICE](packages/php84/NOTICE).
