# @mtjncn/runtime

Runs [Unscripted](https://github.com/mattsplat/unscripted) language packs in any browser, so a
page written for Unscripted (PHP and Rust today; Python and sandboxed JS to come) works in
Firefox, Chrome and Safari too. It answers the pack's one import, `host.request`, against the real DOM.

## No build step

```html
<script type="text/php" src="main.php"></script>
<script src="https://cdn.jsdelivr.net/npm/@mtjncn/runtime@0.1/auto.js"
        data-php="https://cdn.jsdelivr.net/npm/@mtjncn/php84@0.1/php_pack.wasm"></script>
```

Each `data-<pack>` attribute names that pack's `.wasm`: here PHP 8.4. Use `@mtjncn/php82` for PHP 8.2.

A Rust page needs no pack: its program is the script's `src`, built with Unscripted's
`packs/rust/build.sh` on the `unscripted` crate, and started once (`rust_main`):

```html
<script type="application/rust" src="app.wasm"></script>
<script src="https://cdn.jsdelivr.net/npm/@mtjncn/runtime@0.1/auto.js"></script>
``` Pages must be served over http(s).

## As a module

```js
import { load } from '@mtjncn/runtime';
import { wasm } from '@mtjncn/php84';

const php = await load('php', wasm);
php.run(`<?php
use Unscripted as U;
U::document()->querySelector('h1')->textContent = 'Hello from PHP';
`, 'hello.php');
```

`runPage({ php: wasm })` does what `auto.js` does: runs the page's `<script type="text/php">`
tags in order.

## DOM patching

`morph(root, html)` makes `root`'s children match `html`, keeping the elements that stay and
changing only what differs (matched by `u-key` or `id`, else by position). It's also
`window.unscriptedMorph`, which `@mtjncn/ui` calls once per render.

## Not included

Unscripted's own protections: its network gate, time limit, memory cap and replay. Here the
page's code runs as any page's JavaScript does, so a pack isn't a sandbox for untrusted code
(see `SECURITY.md` in the repository). Packs need WebAssembly exception handling, so a
recent browser.

## License

MIT OR Apache-2.0 (`LICENSE-MIT`, `LICENSE-APACHE`).
