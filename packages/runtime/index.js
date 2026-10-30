// @unscripted/runtime: runs Unscripted's language packs in any browser (Firefox, Chrome, Safari).
//
// A pack is WebAssembly that reaches the page through one import, `host.request`, carrying JSON
// (docs/packs.md, "DOM bindings"). In Unscripted the browser answers it; here this module does,
// against the real DOM. The pack's other imports are a few WASI functions, answered as the
// browser does (browser/src/interpreter.rs): a clock, random bytes, output to the console, and
// no files, environment or sockets.
//
//   import { load } from '@unscripted/runtime';
//   import { wasm } from '@unscripted/php84';
//   const php = await load('php', wasm);
//   php.run('<?php U::document()->title;', 'inline.php');
//
// Or, with no build step, `auto.js` runs a page's <script type="text/php"> tags.
//
// What Unscripted adds isn't here: the network gate, the time limit, the memory cap, replay.
// The page's code runs as any page's JavaScript would.

/** The packs this runtime knows: the script type that selects one, and its exports' prefix.
 * Python and JS packs use the same ABI; they're listed once they're tested here. */
export const PACKS = { php: { type: 'text/php', prefix: 'php' } };

/** packs/HOST_API: the protocol this runtime speaks. A pack built for another is refused. */
export const HOST_API = 2;

const encoder = new TextEncoder();
const decoder = new TextDecoder();

/** One pack's instance, and the page objects it holds by handle. */
export class Pack {
  constructor(name, prefix) {
    this.name = name;
    this.prefix = prefix;
    this.exports = null;
    // Handle ↔ object. The window, document and console keep theirs; everything else is
    // marked releasable, and forgotten when the pack lets go of it.
    this.objects = new Map();
    this.handles = new Map();
    this.nextHandle = 1;
    this.lasting = new Set();
    // Callback id → function, so passing the same callback twice gives the same function
    // (removeEventListener needs it).
    this.callbacks = new Map();
  }

  call(name, ...args) {
    return this.exports[`${this.prefix}_${name}`](...args);
  }

  bytes() {
    return new Uint8Array(this.exports.memory.buffer);
  }

  view() {
    return new DataView(this.exports.memory.buffer);
  }

  /** `text` into the pack's memory (with its alloc); the pack frees it. */
  write(text) {
    const data = encoder.encode(text);
    const ptr = this.call('alloc', Math.max(data.length, 1)) >>> 0;
    this.bytes().set(data, ptr);
    return [ptr, data.length];
  }

  read(ptr, len) {
    return decoder.decode(this.bytes().subarray(ptr, ptr + len));
  }

  /** A packed (ptr << 32 | len) string the pack returned: read, then freed. */
  take(packed) {
    const ptr = Number(packed >> 32n) >>> 0;
    const len = Number(packed & 0xffffffffn);
    const text = this.read(ptr, len);
    this.call('free', ptr, Math.max(len, 1));
    return text;
  }

  /** 0 is success; anything else is an error the page didn't catch. */
  check(status, during) {
    if (status !== 0n) console.error(`${this.name} (${during}): ${this.take(status)}`);
  }

  handleOf(object, lasting) {
    let handle = this.handles.get(object);
    if (handle === undefined) {
      handle = this.nextHandle++;
      this.handles.set(object, handle);
      this.objects.set(handle, object);
    }
    if (lasting) this.lasting.add(handle);
    return handle;
  }

  /** A JS value as the pack takes it (see `encode` in browser/src/interpreter.rs). */
  encode(value) {
    if (value === undefined) return { u: 1 };
    if (value === null || typeof value === 'boolean' || typeof value === 'string') return value;
    if (typeof value === 'number') return Number.isFinite(value) ? value : String(value);
    if (typeof value === 'bigint') return Number(value);
    const handle = this.handleOf(value, false);
    return this.lasting.has(handle) ? { o: handle } : { o: handle, r: 1 };
  }

  /** A value from the pack as JS: handles, callbacks, plain data. */
  decode(value) {
    if (value === null || typeof value !== 'object') return value;
    if (Array.isArray(value)) return value.map((v) => this.decode(v));
    if ('u' in value) return undefined;
    if ('o' in value) {
      if (!this.objects.has(value.o)) throw new Error('this object is no longer available');
      return this.objects.get(value.o);
    }
    if ('cb' in value) return this.callback(value.cb);
    if ('arr' in value) return value.arr.map((v) => this.decode(v));
    if ('obj' in value) return Object.fromEntries(Object.entries(value.obj).map(([k, v]) => [k, this.decode(v)]));
    return value;
  }

  callback(id) {
    let f = this.callbacks.get(id);
    if (!f) {
      f = (...args) => this.fire(id, args);
      this.callbacks.set(id, f);
    }
    return f;
  }

  /** Call the pack's callback `id` (a listener, a timer, a promise's) with `args`. */
  fire(id, args) {
    const [ptr, len] = this.write(JSON.stringify(args.map((a) => this.encode(a))));
    this.check(this.call('fire', id, ptr, len), 'callback');
  }

  /** One `host.request`: the browser's operations, done here against the real DOM. */
  answer(message) {
    const target = () => this.objects.get(message.target);
    switch (message.op) {
      case 'global': {
        const found = { window, document, console, Math }[message.name] ?? globalThis[message.name];
        if (found === undefined) throw new Error(`there's no global ${message.name}`);
        return { o: this.handleOf(found, true) };
      }
      case 'get':
        return this.encode(target()[message.name]);
      case 'set':
        target()[message.name] = this.decode(message.value);
        return { u: 1 };
      case 'call': {
        const object = target();
        const args = message.args.map((a) => this.decode(a));
        // The host has constructors as methods of window (`$window->WebSocket($url)`).
        if (object === window && (message.name === 'WebSocket' || message.name === 'XMLHttpRequest')) {
          return this.encode(new window[message.name](...args));
        }
        const method = object[message.name];
        if (typeof method !== 'function') {
          throw new TypeError(`${object?.constructor?.name ?? 'object'}.${message.name}() is not supported`);
        }
        return this.encode(method.apply(object, args));
      }
      case 'release': {
        const object = this.objects.get(message.target);
        if (!this.lasting.has(message.target)) {
          this.objects.delete(message.target);
          this.handles.delete(object);
        }
        return { u: 1 };
      }
      case 'console':
        console.log(message.text);
        return { u: 1 };
      default:
        throw new Error(`unknown request op ${message.op}`);
    }
  }

  /** The imports a pack is built against: `host.request` and its WASI functions. */
  imports() {
    const EBADF = 8;
    const ENOSYS = 52;
    const fail = () => EBADF;
    const started = performance.now();
    const wasi = {
      random_get: (buf, len) => {
        crypto.getRandomValues(this.bytes().subarray(buf, buf + len));
        return 0;
      },
      clock_time_get: (id, _precision, out) => {
        // 0 is real time; anything else, monotonic from the page's start (in nanoseconds).
        const ms = id === 0 ? Date.now() : performance.now() - started;
        this.view().setBigUint64(out, BigInt(Math.round(ms * 1e6)), true);
        return 0;
      },
      clock_res_get: (_id, out) => {
        this.view().setBigUint64(out, 1000n, true);
        return 0;
      },
      environ_sizes_get: (count, size) => {
        this.view().setUint32(count, 0, true);
        this.view().setUint32(size, 0, true);
        return 0;
      },
      environ_get: () => 0,
      args_sizes_get: (count, size) => {
        this.view().setUint32(count, 0, true);
        this.view().setUint32(size, 0, true);
        return 0;
      },
      args_get: () => 0,
      fd_write: (fd, iovs, iovsLen, written) => {
        const view = this.view();
        let text = '';
        let total = 0;
        for (let i = 0; i < iovsLen; i++) {
          const ptr = view.getUint32(iovs + i * 8, true);
          const len = view.getUint32(iovs + i * 8 + 4, true);
          text += this.read(ptr, len);
          total += len;
        }
        view.setUint32(written, total, true);
        // stdout is the page's output; stderr only the interpreter's own (PHP's memory
        // manager), which Unscripted keeps out of the page's console too.
        (fd === 2 ? console.debug : console.log)(text.replace(/\n$/, ''));
        return 0;
      },
      poll_oneoff: () => ENOSYS,
      sched_yield: () => 0,
      proc_exit: (code) => {
        throw new Error(`the ${this.name} pack exited with code ${code}`);
      },
    };
    // No files, sockets or directories: every other WASI call fails, as in the browser.
    const names = ['fd_close', 'fd_datasync', 'fd_fdstat_get', 'fd_fdstat_set_flags', 'fd_filestat_get',
      'fd_filestat_set_size', 'fd_pread', 'fd_prestat_get', 'fd_prestat_dir_name', 'fd_read', 'fd_readdir',
      'fd_seek', 'fd_sync', 'fd_tell', 'path_create_directory', 'path_filestat_get', 'path_filestat_set_times',
      'path_link', 'path_open', 'path_readlink', 'path_remove_directory', 'path_rename', 'path_symlink',
      'path_unlink_file', 'sock_accept', 'sock_recv', 'sock_send'];
    for (const name of names) wasi[name] ??= fail;
    const request = (ptr, len) => {
      let reply;
      try {
        reply = { ok: this.answer(JSON.parse(this.read(ptr, len))) };
      } catch (e) {
        reply = { err: String(e) };
      }
      const [out, outLen] = this.write(JSON.stringify(reply));
      return (BigInt(out) << 32n) | BigInt(outLen);
    };
    return { host: { request }, wasi_snapshot_preview1: wasi };
  }

  async start(url) {
    let instance;
    try {
      ({ instance } = await WebAssembly.instantiateStreaming(fetch(url), this.imports()));
    } catch (e) {
      // A server that doesn't send application/wasm: compile from the bytes instead.
      if (!(e instanceof TypeError)) throw e;
      const bytes = await (await fetch(url)).arrayBuffer();
      ({ instance } = await WebAssembly.instantiate(bytes, this.imports()));
    }
    this.exports = instance.exports;
    const builtFor = this.call('host_api');
    if (builtFor !== HOST_API) {
      throw new Error(`${url} was built for host API ${builtFor}; this file speaks ${HOST_API}`);
    }
    this.exports._initialize?.();
  }

  /** Run one page script; `name` shows in error locations. */
  run(source, name) {
    const [src, srcLen] = this.write(source);
    const [at, atLen] = this.write(name);
    this.check(this.call('run', src, srcLen, at, atLen), name);
  }
}

/** Start pack `name` (a key of PACKS) from the `.wasm` at `wasmUrl`. */
export async function load(name, wasmUrl) {
  const known = PACKS[name];
  if (!known) throw new Error(`unknown pack ${name}; this runtime knows ${Object.keys(PACKS).join(', ')}`);
  const pack = new Pack(name.toUpperCase(), known.prefix);
  await pack.start(String(wasmUrl));
  return pack;
}

/** The page's scripts of `type`, in order: inline text, or fetched from `src`. */
async function scripts(type) {
  const found = [...document.querySelectorAll(`script[type="${type}"]`)];
  return Promise.all(found.map(async (s, i) => {
    if (!s.src) return [s.textContent, `${location.href} (inline script ${i + 1})`];
    const response = await fetch(s.src);
    if (!response.ok) throw new Error(`couldn't load ${s.src}: ${response.status}`);
    return [await response.text(), s.src];
  }));
}

/** Run the page's scripts for each pack in `wasmUrls` ({php: url, ...}), as Unscripted would,
 * once the page has loaded. Packs whose scripts the page doesn't have aren't loaded. */
export async function runPage(wasmUrls) {
  if (document.readyState === 'loading') {
    await new Promise((done) => document.addEventListener('DOMContentLoaded', done, { once: true }));
  }
  for (const [name, { type }] of Object.entries(PACKS)) {
    const wanted = await scripts(type);
    if (wanted.length === 0) continue;
    if (!wasmUrls[name]) {
      console.error(`@unscripted/runtime: this page has ${type} scripts but no ${name} pack to run them`);
      continue;
    }
    const pack = await load(name, wasmUrls[name]);
    for (const [source, url] of wanted) pack.run(source, url);
  }
}
