# @mtjncn/php84

PHP 8.4 compiled to WebAssembly as an [Unscripted](https://github.com/mattsplat/unscripted)
language pack, for [`@mtjncn/runtime`](../runtime). With it, a page's
`<script type="text/php">` runs in any modern browser.

```php
<?php
use Unscripted as U;

U::document()->querySelector('#count')->textContent = '1';
U::document()->querySelector('#add')->addEventListener('click', fn($event) => U::console()->log($event->type));
```

Page code reaches the browser through the `Unscripted` class (`document()`, `window()`,
`console()`, `fetch()`, timers, storage); members use the web's own names. The full list is in
Unscripted's [page API reference](https://github.com/mattsplat/unscripted/blob/main/docs/api.md).

## Use

```js
import { load } from '@mtjncn/runtime';
import { wasm, phpVersion } from '@mtjncn/php84';

const php = await load('php', wasm);
php.run('<?php echo PHP_VERSION;', 'hello.php');   // echo goes to the console: 8.4.x
```

Or with no build step, see [`@mtjncn/runtime`](../runtime#no-build-step).

## PHP versions

One package per PHP minor version, since PHP's minors differ (8.2 and 8.4 behave differently):
`@mtjncn/php82`, `@mtjncn/php84`. Pick the one your code is written for; its patch
releases bring that line's PHP security fixes, and `phpVersion` says the exact release. This
line is supported by the PHP project until December 2028; after that the package is deprecated.

## What's in it

`php_pack.wasm` (about 4.5–4.7 MB, 1.5 MB compressed): php-src's embed SAPI with the core
extensions (strings, arrays, JSON, PCRE, hashing, dates, SPL), plus Unscripted's bridge. It has
no files, network, processes or environment of its own. Not available: Fibers, `sleep`, the
`exec` family. Integers are 32-bit (`PHP_INT_MAX` is 2147483647), as WebAssembly's pointers
are.

## License

This product includes PHP software, freely available from <http://www.php.net/software/>.

PHP is under the PHP License 3.01 (`LICENSE-PHP`); Unscripted's bridge is MIT OR Apache-2.0.
See `NOTICE`. This package is not made or endorsed by the PHP Group.
