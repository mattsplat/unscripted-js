// A static file server for trying the examples: `npm run serve` (port 8000, or PORT). Browsers
// won't load .wasm from file:// pages, and need it sent as application/wasm. Node only; no
// dependencies. Serves this repo's folder, or the folder given (`node scripts/serve.js dir`),
// and nothing outside it.
//
// Only this machine can reach it unless HOST says otherwise: `HOST=0.0.0.0 npm run serve`
// serves to your local network too (other devices on the same Wi-Fi), and prints their URLs.
import { createServer } from 'node:http';
import { createReadStream, statSync } from 'node:fs';
import { networkInterfaces } from 'node:os';
import { extname, join, resolve, sep } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(process.argv[2] ?? fileURLToPath(new URL('..', import.meta.url)));
const port = Number(process.env.PORT ?? 8000);
const host = process.env.HOST ?? '127.0.0.1';
const types = {
  '.html': 'text/html; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.mjs': 'text/javascript; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.json': 'application/json',
  '.wasm': 'application/wasm',
  '.php': 'text/plain; charset=utf-8',
  '.py': 'text/plain; charset=utf-8',
  '.md': 'text/plain; charset=utf-8',
  '.png': 'image/png',
  '.svg': 'image/svg+xml',
};

createServer((req, res) => {
  const path = decodeURIComponent(new URL(req.url, 'http://localhost').pathname);
  let file = resolve(join(root, path));
  if (file !== root && !file.startsWith(root + sep)) {
    res.writeHead(403).end('outside the served folder');
    return;
  }
  try {
    if (statSync(file).isDirectory()) file = join(file, 'index.html');
    statSync(file);
  } catch {
    res.writeHead(404).end('not found');
    return;
  }
  res.writeHead(200, { 'content-type': types[extname(file)] ?? 'application/octet-stream' });
  createReadStream(file).pipe(res);
}).listen(port, host, () => {
  console.log(`serving ${root}`);
  console.log(`  on this machine: http://localhost:${port}/`);
  if (host === '0.0.0.0' || host === '::') {
    // The addresses other devices on the network can use.
    for (const addresses of Object.values(networkInterfaces())) {
      for (const a of addresses ?? []) {
        if (a.family === 'IPv4' && !a.internal) console.log(`  on your network: http://${a.address}:${port}/`);
      }
    }
    console.log('  Anyone on this network can read the served folder while this runs.');
  }
});
