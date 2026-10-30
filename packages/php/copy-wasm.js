// Before packing: copy in the PHP pack built by Unscripted's packs/php/build.sh, and PHP's
// license. The Unscripted repo is $UNSCRIPTED_REPO, or ../browser-test beside this folder.
import { copyFileSync, existsSync } from 'node:fs';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = fileURLToPath(new URL('.', import.meta.url));
const repo = process.env.UNSCRIPTED_REPO ?? resolve(here, '../../../browser-test');
const built = resolve(repo, 'packs/php/target/php_pack.wasm');
const license = resolve(repo, 'packs/php/.build/php-8.4.27/LICENSE');
for (const [from, what] of [[built, 'php_pack.wasm'], [license, 'PHP license']]) {
  if (!existsSync(from)) {
    console.error(`${what} missing at ${from}: run packs/php/build.sh in the Unscripted repo (set UNSCRIPTED_REPO if it isn't ../browser-test)`);
    process.exit(1);
  }
}
copyFileSync(built, resolve(here, 'php_pack.wasm'));
copyFileSync(license, resolve(here, 'LICENSE-PHP'));
