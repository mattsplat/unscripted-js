// The PHP tests: `npm test`. Runs each tests/*.test.php in the PHP 8.4 pack (Node, no browser),
// after packages/ui/ui.php and tests/helpers.php. A test file reports with check() and fails(),
// and ends with done(), which throws if any failed: the run exits 1.
//
// Needs Node 24 or later (the pack uses WebAssembly exception references) and the pack copied
// in (see CONTRIBUTING.md, "Building"). There's no DOM: tests cover what runs without one.
import { readFileSync, readdirSync, existsSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const major = Number(process.versions.node.split('.')[0]);
if (major < 24) {
  console.error(`npm test needs Node 24 or later (this is ${process.version}): nvm use 24`);
  process.exit(1);
}

const root = fileURLToPath(new URL('..', import.meta.url));
const wasm = `${root}packages/php84/php_pack.wasm`;
if (!existsSync(wasm)) {
  console.error(`there's no ${wasm}: build the PHP pack and copy it in first (CONTRIBUTING.md, "Building")`);
  process.exit(1);
}

// The pack's stderr is only PHP's memory manager talking (munmap notices): not test output.
console.debug = () => {};

// What ui.php reaches for when it loads: a document with no Blade scripts on it.
globalThis.window = globalThis;
globalThis.document = { querySelectorAll: () => [] };
const { Pack } = await import('../packages/runtime/index.js');

let failed = false;
const files = readdirSync(`${root}tests`).filter((f) => f.endsWith('.test.php')).sort();
for (const file of files) {
  console.log(`\n${file}`);
  // A fresh pack for each file, so one can't leave state behind for the next.
  const pack = new Pack('PHP', 'php');
  const { instance } = await WebAssembly.instantiate(readFileSync(wasm), pack.imports());
  pack.exports = instance.exports;
  pack.exports._initialize?.();
  const check = pack.check.bind(pack);
  pack.check = (status, during) => {
    if (status !== 0n) failed = true;
    check(status, during);
  };
  for (const [source, name] of [
    [readFileSync(`${root}packages/ui/ui.php`, 'utf8'), 'packages/ui/ui.php'],
    [readFileSync(`${root}tests/helpers.php`, 'utf8'), 'tests/helpers.php'],
    [readFileSync(`${root}tests/${file}`, 'utf8'), `tests/${file}`],
  ]) {
    pack.run(source, name);
  }
}

console.log(failed ? '\nFAILED' : '\nall passed');
process.exitCode = failed ? 1 : 0;
