<?php
// Helpers for the tests/*.test.php files (run by tests/run.mjs).

use Unscripted\Ui\Component;

$GLOBALS['__failed'] = 0;

/** A component's rendered HTML, whitespace collapsed. */
function html(Component $component): string
{
    ob_start();
    try {
        (fn() => $this->render())->call($component);
    } finally {
        $html = ob_get_clean();
    }
    return preg_replace('/\s+/', ' ', trim($html));
}

function check(string $label, mixed $got, mixed $want): void
{
    if ($got === $want) {
        echo "  ok   $label\n";
        return;
    }
    $GLOBALS['__failed']++;
    echo "  FAIL $label\n       got:  " . var_export($got, true) . "\n       want: " . var_export($want, true) . "\n";
}

/** `$f` throws, with a message containing `$contains`. */
function fails(string $label, callable $f, string $contains): void
{
    try {
        $f();
    } catch (Throwable $e) {
        if (str_contains($e->getMessage(), $contains)) {
            echo "  ok   $label\n";
        } else {
            $GLOBALS['__failed']++;
            echo "  FAIL $label\n       threw: {$e->getMessage()}\n       want:  ...$contains...\n";
        }
        return;
    }
    $GLOBALS['__failed']++;
    echo "  FAIL $label: didn't throw\n";
}

/** Ends a test file: throws if anything failed, so the run fails. */
function done(): void
{
    if ($GLOBALS['__failed']) {
        throw new Error("{$GLOBALS['__failed']} failed");
    }
}
