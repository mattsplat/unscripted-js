# Unscripted npm packages

- `packages/runtime` (`@unscripted/runtime`): runs Unscripted language packs in any browser.
- `packages/php` (`@unscripted/php`): the PHP pack.

The scope is a placeholder until it's settled; nothing is published yet.

The packs are built in the Unscripted repo ([mattsplat/unscripted](https://github.com/mattsplat/unscripted));
packing copies them in from there: `$UNSCRIPTED_REPO`, or `../browser-test` beside this folder.

## Build and try

```sh
(cd ../browser-test && packs/php/build.sh)   # once: builds the PHP pack
npm run pack                                  # copies it in and writes dist/*.tgz
python3 -m http.server 8000
```

Then open <http://localhost:8000/examples/php-snake/>: the PHP Snake game (a copy of
Unscripted's `examples/php-snake`), running through `@unscripted/runtime`.
