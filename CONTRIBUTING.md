# Contributing

## Layout

```
packages/runtime/   @mtjncn/runtime: index.js (the runtime), auto.js (drop-in script)
packages/php84/     @mtjncn/php84: PHP 8.4 (index.js; version.js and the pack are copied in when packing)
packages/php82/     @mtjncn/php82: PHP 8.2
packages/ui/        @mtjncn/ui: reactive components for PHP 8.4 (ui.php, loaded as a page script)
packages/ui-server/ unscripted/ui-server (Composer): #[Server] methods for @mtjncn/ui, on Laravel
scripts/            copy-php.js (copies a PHP line in), serve.js (a local static server)
tests/              npm test: run.mjs runs each *.test.php in the PHP 8.4 pack, in Node
examples/           pages that run through the runtime
```

The language packs themselves (interpreters, bridges, `packs/HOST_API`) live in the
[Unscripted repo](https://github.com/mattsplat/unscripted) under `packs/`. This repo only
packages and runs them.

## Building

```sh
(cd ../browser-test && packs/php/build.sh)                      # PHP 8.4
(cd ../browser-test && PHP_VERSION=8.2.35 packs/php/build.sh)   # PHP 8.2
npm run pack                                                     # dist/*.tgz
```

Set `UNSCRIPTED_REPO` if the Unscripted repo isn't at `../browser-test`.

## Testing

`npm test` runs `tests/*.test.php` in the PHP 8.4 pack, in Node with no browser: what
`@mtjncn/ui` does without a DOM, such as compiling Blade. It needs Node 24 or later
(`nvm use 24`) and the pack copied in. A test file loads after `ui.php` and `tests/helpers.php`,
reports with `check($label, $got, $want)` and `fails($label, $fn, $message)`, and ends with
`done()`. Each file gets a fresh pack.

`packages/ui-server` has its own: `composer test` there (the host's PHP 8.4, no Laravel). To
try `#[Server]` methods in a browser, use a Laravel app that requires the package through a
Composer path repository (`"url": "../unscripted-js/packages/ui-server"`).

For what needs a page, serve the repo (`npm run serve`, or any static server that sends `.wasm` as
`application/wasm`) and open each example in a browser, checking
its console. Before a release, test in Firefox, Chrome and Safari, and test the packed
`.tgz` files installed into a fresh project (`npm install ./dist/*.tgz`), not just the
workspace; `node scripts/serve.js <folder>` serves that project.

## Keeping in step with Unscripted

- The runtime's `HOST_API` must match `packs/HOST_API` in Unscripted. When Unscripted bumps
  it, rebuild the packs, update `HOST_API` in `packages/runtime/index.js` and each pack's
  `hostApi`, and release them together.
- The runtime answers requests the way Unscripted's browser does (`browser/src/interpreter.rs`
  for the WASI functions, `browser/src/host.rs` for the operations). Behaviour should match
  where it can.
- `window.unscriptedMorph` (the runtime's `morph`) is this repo's own: Unscripted's browser has
  no equivalent yet, so `@mtjncn/ui` falls back to replacing innerHTML there. Giving the
  browser one would make the two match.

## PHP versions

Each PHP minor version is a package (`packages/php<NN>`) with its own semver: patch releases
for that line's PHP patch releases and bridge fixes, minor for new bridge features, major for
breaking changes (a host API bump, a change to the `Unscripted` class). To add a line: build it
in Unscripted (`PHP_VERSION=8.5.x packs/php/build.sh`, with `packs/php/patches/8.5/`), copy a
`packages/php<NN>` folder, change its name, line and support date, and add it to the README.
When PHP stops supporting a line, deprecate its package on npm, pointing at a newer one.

## Adding a language

Add it to `PACKS` in `packages/runtime/index.js` (its script type and export prefix), then a
package like `packages/php` that ships its `.wasm`, licenses and notices, and an example.

## Commits

Small, focused commits with messages that say why. No generated files: `.wasm` packs and
`dist/` are built, not committed.
