# Contributing

## Layout

```
packages/runtime/   @unscripted/runtime: index.js (the runtime), auto.js (drop-in script)
packages/php84/     @unscripted/php84: PHP 8.4 (index.js; version.js and the pack are copied in when packing)
packages/php82/     @unscripted/php82: PHP 8.2
scripts/            copy-php.js (copies a PHP line in), serve.js (a local static server)
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

Serve the repo (`npm run serve`, or any static server that sends `.wasm` as
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
