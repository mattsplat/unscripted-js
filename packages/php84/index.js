// @mtjncn/php84: PHP 8.4 (php-src's embed SAPI, built for WebAssembly) as an Unscripted
// language pack, for @mtjncn/runtime. One package per PHP minor version: code written for
// one line can behave differently on another, so a page pins its line and gets that line's
// patch releases.
//
//   import { load } from '@mtjncn/runtime';
//   import { wasm, phpVersion } from '@mtjncn/php84';
//   const php = await load('php', wasm);

/** Where the pack's .wasm is, for `load('php', wasm)`. */
export const wasm = new URL('./php_pack.wasm', import.meta.url).href;

/** The exact PHP release in this package, e.g. '8.4.x'. */
export { phpVersion } from './version.js';

/** The host API the pack was built for (packs/HOST_API in Unscripted). */
export const hostApi = 2;
