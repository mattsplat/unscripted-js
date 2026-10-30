// @unscripted/php: the PHP language pack (php-src 8.4, embed SAPI, built for WebAssembly), for
// @unscripted/runtime.
//
//   import { load } from '@unscripted/runtime';
//   import { wasm } from '@unscripted/php';
//   const php = await load('php', wasm);

/** Where the pack's .wasm is, for `load('php', wasm)`. */
export const wasm = new URL('./php_pack.wasm', import.meta.url).href;

/** The host API the pack was built for (packs/HOST_API). */
export const hostApi = 2;
