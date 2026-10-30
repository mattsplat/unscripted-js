# @unscripted/php

PHP 8.4 (php-src's embed SAPI) compiled to WebAssembly as an
[Unscripted](https://github.com/mattsplat/unscripted) language pack, for
[`@unscripted/runtime`](../runtime). Page code reaches the page through the `Unscripted` class:

```php
<?php
use Unscripted as U;

U::document()->querySelector('#count')->textContent = '1';
```

`php_pack.wasm` is 4.7 MB. It has no files, network or environment of its own: everything goes
through the browser.

## License

PHP is distributed under the PHP License 3.01 (`LICENSE-PHP`); the Unscripted bridge in it is
MIT OR Apache-2.0. This package isn't made or endorsed by the PHP Group.
