// Before packing a PHP line's package (`node ../../scripts/copy-php.js 8.4` from it): copy in
// that line's pack, built by Unscripted's `PHP_VERSION=8.4.x packs/php/build.sh`, its exact PHP
// version, and that release's license. The Unscripted repo is $UNSCRIPTED_REPO, or
// ../browser-test beside this repo.
import { copyFileSync, existsSync, readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const line = process.argv[2];
if (!/^\d+\.\d+$/.test(line ?? '')) {
  console.error('usage: copy-php.js <line>, e.g. 8.4');
  process.exit(1);
}
const here = fileURLToPath(new URL('..', import.meta.url));
const repo = process.env.UNSCRIPTED_REPO ?? resolve(here, '../browser-test');
const built = resolve(repo, `packs/php/target/php-${line}`);
const fail = (what) => {
  console.error(`${what} missing: build PHP ${line} in the Unscripted repo (PHP_VERSION=${line}.x packs/php/build.sh); set UNSCRIPTED_REPO if it isn't ${repo}`);
  process.exit(1);
};
if (!existsSync(resolve(built, 'php_pack.wasm'))) fail(resolve(built, 'php_pack.wasm'));
const version = readFileSync(resolve(built, 'VERSION'), 'utf8').trim();
const license = resolve(repo, `packs/php/.build/php-${version}/LICENSE`);
if (!existsSync(license)) fail(license);

const into = process.cwd();
copyFileSync(resolve(built, 'php_pack.wasm'), resolve(into, 'php_pack.wasm'));
copyFileSync(license, resolve(into, 'LICENSE-PHP'));
writeFileSync(resolve(into, 'version.js'), `// Written by scripts/copy-php.js when packing.\nexport const phpVersion = '${version}';\n`);
console.log(`packed PHP ${version} (${line} line)`);
