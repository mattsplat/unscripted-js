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
