# Security

## What the runtime does and doesn't protect

A pack runs with the page's own privileges: whatever the page's JavaScript could do, its PHP
can do through the runtime. Unscripted's browser adds a network gate, time and memory limits,
and keeps packs away from cookies and storage it doesn't allow; those protections are not
part of this runtime. Don't treat a pack running here as a sandbox for untrusted code.

The packs themselves have no files, network, processes or environment of their own: their
WASI imports for those always fail.

## Reporting a problem

Please report security issues privately, through GitHub's
[private vulnerability reporting](https://github.com/mattsplat/unscripted-js/security/advisories/new)
for this repository, not in public issues.
