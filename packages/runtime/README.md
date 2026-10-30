# @unscripted/runtime

Runs [Unscripted](https://github.com/mattsplat/unscripted) language packs in any browser, so a
page written for Unscripted (PHP today; Python and sandboxed JS to come) works in Firefox,
Chrome and Safari too. It answers the pack's one import, `host.request`, against the real DOM.

## No build step

```html
<script type="text/php" src="main.php"></script>
<script src="https://cdn.jsdelivr.net/npm/@unscripted/runtime/auto.js"
        data-php="https://cdn.jsdelivr.net/npm/@unscripted/php/php_pack.wasm"></script>
```

Each `data-<pack>` attribute names that pack's `.wasm`. Pages must be served over http(s).

## As a module

```js
import { load } from '@unscripted/runtime';
import { wasm } from '@unscripted/php';

const php = await load('php', wasm);
php.run(`<?php
use Unscripted as U;
U::document()->querySelector('h1')->textContent = 'Hello from PHP';
`, 'hello.php');
```

`runPage({ php: wasm })` does what `auto.js` does: runs the page's `<script type="text/php">`
tags in order.

## Not included

Unscripted's own protections: its network gate, time limit, memory cap and replay. Here the
page's code runs as any page's JavaScript does. Packs need WebAssembly exception handling, so a
recent browser.

License: MIT OR Apache-2.0.
