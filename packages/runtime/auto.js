// @unscripted/runtime, drop-in: runs the page's <script type="text/php"> tags, and its
// <script type="application/rust" src="app.wasm"> programs, with no build step.
//
//   <script type="text/php" src="main.php"></script>
//   <script src="https://cdn.jsdelivr.net/npm/@unscripted/runtime/auto.js"
//           data-php="https://cdn.jsdelivr.net/npm/@unscripted/php84/php_pack.wasm"></script>
//
// Each data-<pack> attribute names that pack's .wasm (relative URLs resolve against the page).
// Rust programs need none: the script's src is the program.
(() => {
  const me = document.currentScript;
  const wasmUrls = Object.fromEntries(Object.entries(me.dataset).map(([name, url]) => [name, new URL(url, document.baseURI).href]));
  import(new URL('./index.js', me.src).href)
    .then((runtime) => runtime.runPage(wasmUrls))
    .catch((e) => console.error('@unscripted/runtime:', e));
})();
