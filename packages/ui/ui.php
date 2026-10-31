<?php
/*
 * @unscripted/ui: reactive components for Unscripted PHP. A component is a class: its #[State]
 * properties are the data, its public methods are actions, and render() prints its HTML. After
 * every action (and every u-model change) it renders again, so the page always shows the state.
 *
 *   final class Counter extends Component {
 *       #[State] public int $count = 0;
 *       public function add(int $by = 1): void { $this->count += $by; }
 *       protected function render(): void { ?>
 *           <button u-on:click="add">Clicked <?= $this->count ?> times</button>
 *       <?php }
 *   }
 *   new Counter()->mount('#app');
 *
 * In the HTML:
 *   u-on:<event>="action"           calls $this->action() (one listener on the root, not one per element)
 *   u-on:<event>="action(1, \"x\")"  with arguments, as JSON literals
 *   u-on:submit.prevent="action"    calls $event->preventDefault() first
 *   u-model="draft"                 two-way: the element shows $this->draft, input writes it back
 *   u-model="todos.3.done"          a path into an array property
 *
 * Or as one Blade file: the class in a PHP block at the top (an anonymous class, no render()),
 * the template under it. Its public properties are the template's variables.
 *
 *   <?php
 *   use Unscripted\Ui\{Component, State};
 *   new class extends Component {
 *       #[State] public int $count = 0;
 *       public function add(): void { $this->count++; }
 *   };
 *   ?>
 *   <button u-on:click="add">Clicked {{ $count }} times</button>
 *
 * mounted from the page with <script type="text/blade" src="counter.blade.php" data-mount="#app">.
 * The Blade is a subset: {{ }}, {!! !!}, {{-- --}}, @{{ }}, @if/@elseif/@else, @unless, @isset,
 * @empty, @foreach, @forelse/@empty, @for, @while, @break, @continue, @php, @verbatim, @class,
 * @checked/@selected/@disabled/@readonly/@required, @json. Other @words are left as written.
 *
 * Served by unscripted/ui-server, a component's #[Server] static methods run on the server: an
 * action yields them ($rows = yield static::search($q);), the page calls them
 * (u-on:click="remove(3)"), or #[Watch('query')] makes one a loader that fills the property
 * named like it.
 *
 * Load it before the page's own PHP: <script type="text/php" src="ui.php"></script>.
 * PHP 8.4. Rendering patches the DOM with the runtime's morph, or replaces the root's HTML where
 * the host has none (keeping a u-model input's focus and caret).
 */

namespace Unscripted\Ui;

use Attribute;
use Closure;
use Error;
use Exception;
use Generator;
use ParseError;
use ReflectionMethod;
use ReflectionObject;
use ReflectionProperty;
use Throwable;
use Unscripted as U;

/** Marks a property as component state: u-model may write it unless it's private(set). */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class State {}

/** Marks a public static method that runs on the server (unscripted/ui-server), not here: the
 * file the browser gets has a stub in its place, which returns a ServerCall. */
#[Attribute(Attribute::TARGET_METHOD)]
final class Server {}

/** On a #[Server] method: call it with these properties' values on mount and whenever they
 * change, and put what it returns in the property named like the method. */
#[Attribute(Attribute::TARGET_METHOD)]
final class Watch
{
    public array $properties;

    public function __construct(string ...$properties)
    {
        $this->properties = $properties;
    }
}

/** A call to a #[Server] method, made when an action yields it: `$rows = yield static::search($q);`. */
final class ServerCall
{
    public function __construct(
        public readonly string $component,
        public readonly string $method,
        public readonly array $args,
    ) {}
}

/** Thrown into an action when a server call it yielded fails. `$errors` has a validation
 * failure's messages, by field. */
final class ServerError extends Exception
{
    public function __construct(string $message, public readonly int $status = 0, public readonly array $errors = [])
    {
        parent::__construct($message, $status);
    }
}

/** Text for HTML, attributes included. `<?= ?>` doesn't escape: use `<?= e($value) ?>`. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/** @class: the keys whose values are true, and the values with no key. */
function classes(array $classes): string
{
    $names = [];
    foreach ($classes as $key => $value) {
        if (is_int($key)) {
            $names[] = $value;
        } elseif ($value) {
            $names[] = $key;
        }
    }
    return e(implode(' ', $names));
}

abstract class Component
{
    private ?object $root = null;
    /** Event type => the u-on attribute names seen for it (with their modifiers). */
    private array $listening = [];
    /** A Blade component's compiled template, and its file's name for error messages. */
    private ?Closure $view = null;
    private ?string $file = null;
    /** Whether the host has unscriptedMorph; null until the first render asks. */
    private static ?bool $morph = null;
    /** Flows waiting on the server, by name (an action's, or a loader's): [id, Generator, is a loader]. */
    private array $flows = [];
    private int $flowId = 0;
    /** Each #[Watch] loader's arguments when it last ran, as JSON. */
    private array $watched = [];
    /** Set when an action's server calls are done, so every loader runs again for fresh data. */
    private bool $stale = false;

    private const string ENDPOINT = '/unscripted/call';

    /** Prints the component's HTML. Runs on every render, so it should only read state. A Blade
     * component's template is its render(). */
    protected function render(): void
    {
        if ($this->view === null) {
            throw new Error(static::class . ' needs a render() method or a Blade template');
        }
        $data = [];
        foreach (new ReflectionObject($this)->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if (!$property->isStatic()) {
                $data[$property->name] = $property->getValue($this);
            }
        }
        ($this->view)($data);
    }

    /** Renders into the element `$selector` finds, and keeps it up to date from then on. */
    public function mount(string $selector): static
    {
        foreach (new ReflectionObject($this)->getMethods() as $method) {
            if ($method->getAttributes(Server::class) && (string) $method->getReturnType() !== ServerCall::class) {
                throw new Error(($this->file ?? static::class) . " came to the browser with its #[Server] method {$method->name} as written: serve it with unscripted/ui-server, which keeps the method on the server");
            }
        }
        $root = U::document()->querySelector($selector);
        if ($root === null) {
            throw new Error("there's no element $selector to mount " . static::class . ' on');
        }
        $this->root = $root;
        $this->listen('input', []);
        $this->refresh();
        return $this;
    }

    /** Renders again. Actions and u-model changes do this already; call it after changing
     * state anywhere else (a timer, a fetch). */
    public function refresh(): void
    {
        $this->load();
        ob_start();
        try {
            $this->render();
        } catch (Throwable $e) {
            throw $this->located($e);
        } finally {
            $html = ob_get_clean();
        }

        // Patch the DOM where the host can (@unscripted/runtime's morph), so nodes that didn't
        // change are kept, focus and scroll with them. Elsewhere, replace it, then put back the
        // focus and caret of a bound input at least.
        self::$morph ??= U::window()->unscriptedMorph !== null;
        if (self::$morph) {
            U::window()->unscriptedMorph($this->root, $html);
        } else {
            $focus = $this->focused();
            $this->root->innerHTML = $html;
        }

        // Listen for each event type the HTML uses, once per type.
        preg_match_all('/\su-on:([a-z]+)((?:\.[a-z]+)*)\s*=/i', $html, $found, PREG_SET_ORDER);
        $seen = [];
        foreach ($found as [, $type, $modifiers]) {
            $seen[strtolower($type)][] = strtolower("u-on:$type$modifiers");
        }
        foreach ($seen as $type => $names) {
            $this->listen($type, $names);
        }

        $this->showModels();
        if (!self::$morph) {
            $this->restore($focus);
        }
    }

    private function listen(string $type, array $names): void
    {
        if (!isset($this->listening[$type])) {
            $this->listening[$type] = [];
            $this->root->addEventListener($type, fn($event) => $this->dispatch($type, $event));
        }
        $this->listening[$type] = array_values(array_unique([...$this->listening[$type], ...$names]));
    }

    private function dispatch(string $type, object $event): void
    {
        $changed = false;
        if ($type === 'input') {
            $changed = $this->readModel($event->target);
        }

        $names = $this->listening[$type];
        if ($names) {
            $selector = implode(', ', array_map(fn($name) => '[' . addcslashes($name, ':.') . ']', $names));
            $element = $event->target->closest($selector);
            if ($element !== null && $this->root->contains($element)) {
                foreach ($names as $name) {
                    if ($element->hasAttribute($name)) {
                        $this->modifiers($name, $event);
                        $this->call($element->getAttribute($name));
                        $changed = true;
                        break;
                    }
                }
            }
        }

        if ($changed) {
            $this->refresh();
        }
    }

    private function modifiers(string $name, object $event): void
    {
        foreach (array_slice(explode('.', $name), 1) as $modifier) {
            match ($modifier) {
                'prevent' => $event->preventDefault(),
                default => throw new Error("u-on doesn't know the modifier .$modifier (in $name)"),
            };
        }
    }

    /** `name` or `name(args)`, the arguments as JSON literals. */
    private function call(string $expression): void
    {
        if (!preg_match('/^\s*([A-Za-z_]\w*)\s*(?:\((.*)\))?\s*$/s', $expression, $m)) {
            throw new Error("u-on=\"$expression\" isn't an action call; write name or name(args)");
        }
        $name = $m[1];
        $args = trim($m[2] ?? '') === '' ? [] : json_decode("[$m[2]]", true, 512, JSON_THROW_ON_ERROR);

        // Actions are the component's own public methods: not Component's, not static, not magic.
        if (!method_exists($this, $name) || method_exists(self::class, $name) || str_starts_with($name, '__')) {
            throw new Error("$name isn't an action of " . static::class);
        }
        $method = new ReflectionMethod($this, $name);
        if ($method->isPublic() && $method->isStatic() && $method->getAttributes(Server::class)) {
            // A server method called straight from the page: what it returns isn't kept, but
            // the loaders run again once it's done.
            $call = $method->invokeArgs(null, $args);
            $this->run($name, (function () use ($call) {
                yield $call;
            })());
            return;
        }
        if (!$method->isPublic() || $method->isStatic()) {
            throw new Error("$name isn't an action of " . static::class . ': actions are public, non-static methods');
        }
        try {
            $result = $method->invokeArgs($this, $args);
        } catch (Throwable $e) {
            throw $this->located($e);
        }
        if ($result instanceof Generator) {
            $this->run($name, $result);
        }
    }

    /** Whether the flow `$name` (an action or a loader) is waiting on the server; with no name,
     * whether any is. */
    public function loading(?string $name = null): bool
    {
        return $name === null ? $this->flows !== [] : isset($this->flows[$name]);
    }

    /** Starts each #[Watch] loader whose properties changed since it last ran: all of them after
     * an action's server calls. */
    private function load(): void
    {
        $stale = $this->stale;
        $this->stale = false;
        foreach (new ReflectionObject($this)->getMethods(ReflectionMethod::IS_STATIC) as $method) {
            $watch = $method->getAttributes(Watch::class)[0] ?? null;
            if ($watch === null) {
                continue;
            }
            $name = $method->name;
            $args = array_map(fn($property) => $this->{$property}, $watch->newInstance()->properties);
            $key = json_encode($args);
            if (!$stale && ($this->watched[$name] ?? null) === $key) {
                continue;
            }
            if (!property_exists($this, $name)) {
                throw new Error("the #[Watch] method $name needs a property \$$name to load into");
            }
            $this->watched[$name] = $key;
            $call = $method->invokeArgs(null, $args);
            $this->run($name, (function () use ($name, $call) {
                $this->{$name} = yield $call;
            })(), loader: true);
        }
    }

    /** Runs a flow: each server call it yields is sent, and the result sent back in (or the
     * failure thrown in, a ServerError), rendering after each step. A flow started with the name
     * of one still waiting replaces it, so an old result can't land after a newer one. */
    private function run(string $name, Generator $flow, bool $loader = false): void
    {
        $this->flows[$name] = [++$this->flowId, $flow, $loader];
        $this->advance($name, fn() => $flow->current());
    }

    /** Resumes `$name`'s flow with `$resume`, then sends the server calls it yields next. */
    private function advance(string $name, Closure $resume): void
    {
        [$id, $flow, $loader] = $this->flows[$name];
        try {
            $resume();
            $yielded = $flow->valid() ? $flow->current() : null;
        } catch (Throwable $e) {
            unset($this->flows[$name]);
            throw $this->located($e);
        }
        if (!$flow->valid()) {
            unset($this->flows[$name]);
            return;
        }

        $calls = $yielded instanceof ServerCall ? [$yielded] : $yielded;
        if (!is_array($calls) || $calls === [] || array_filter($calls, fn($call) => !$call instanceof ServerCall)) {
            unset($this->flows[$name]);
            throw new Error("$name yielded something that isn't a server call: yield static::method(...), or an array of them");
        }
        $this->send(array_values($calls), function (?array $results, ?ServerError $error) use ($name, $id, $loader, $yielded) {
            if (($this->flows[$name][0] ?? null) !== $id) {
                return; // replaced by a newer flow
            }
            $flow = $this->flows[$name][1];
            try {
                $this->advance($name, fn() => $error !== null ? $flow->throw($error) : $flow->send(
                    $yielded instanceof ServerCall ? $results[0] : array_combine(array_keys($yielded), $results),
                ));
            } finally {
                if (!$loader && !isset($this->flows[$name])) {
                    $this->stale = true;
                }
                $this->refresh();
            }
        });
    }

    /** Posts the calls to the server in one request; `$done` gets their results, or the error. */
    private function send(array $calls, Closure $done): void
    {
        $token = U::document()->querySelector('meta[name="csrf-token"]')?->getAttribute('content');
        $headers = ['Content-Type' => 'application/json', 'Accept' => 'application/json'];
        if ($token !== null) {
            $headers['X-CSRF-TOKEN'] = $token;
        }
        $body = json_encode(['calls' => array_map(fn(ServerCall $call) => [
            'component' => $call->component,
            'method' => $call->method,
            'args' => $call->args,
        ], $calls)], JSON_THROW_ON_ERROR);

        U::fetch(self::ENDPOINT, ['method' => 'POST', 'headers' => $headers, 'body' => $body])->then(
            function ($response) use ($done) {
                $ok = $response->ok;
                $status = $response->status;
                // Nested, not chained: a PHP callback's return value doesn't reach the next then().
                $response->text()->then(function (string $text) use ($done, $ok, $status) {
                    $reply = json_decode($text, true);
                    if ($ok && is_array($reply['results'] ?? null)) {
                        $done($reply['results'], null);
                    } else {
                        $done(null, new ServerError($reply['message'] ?? "the server answered $status", $status, $reply['errors'] ?? []));
                    }
                });
            },
            fn($error) => $done(null, new ServerError("couldn't reach the server: {$error->message}")),
        );
    }

    /** A Blade component's code runs from eval(), which PHP reports as "eval()'d code": name
     * the file instead (the line numbers are the file's). */
    private function located(Throwable $e): Throwable
    {
        if ($this->file === null || !str_contains($e->getFile(), "eval()'d code")) {
            return $e;
        }
        return new Error("{$e->getMessage()} in {$this->file}:{$e->getLine()}", 0, $e);
    }

    /** An input event from a u-model element: its value into state. False if it wasn't one. */
    private function readModel(object $element): bool
    {
        $path = $element->getAttribute('u-model');
        if ($path === null || !$this->root->contains($element)) {
            return false;
        }
        $keys = explode('.', $path);
        $property = $this->modelProperty($keys[0], $path);

        $value = $element->type === 'checkbox' ? (bool) $element->checked : (string) $element->value;
        if (count($keys) === 1) {
            $this->{$keys[0]} = $this->coerce($value, $property->getType()?->getName());
            return true;
        }
        // Copy, change, assign: works for hooked properties too, which can't be referenced.
        $data = $this->{$keys[0]};
        $slot = &$data;
        foreach (array_slice($keys, 1) as $key) {
            if (!is_array($slot) || !array_key_exists($key, $slot)) {
                throw new Error("u-model=\"$path\": there's nothing at $key");
            }
            $slot = &$slot[$key];
        }
        $slot = $this->coerce($value, get_debug_type($slot));
        unset($slot);
        $this->{$keys[0]} = $data;
        return true;
    }

    private function modelProperty(string $name, string $path): ReflectionProperty
    {
        if (!property_exists($this, $name)) {
            throw new Error("u-model=\"$path\": " . static::class . " has no property $name");
        }
        $property = new ReflectionProperty($this, $name);
        if (!$property->getAttributes(State::class) || !$property->isPublic() || $property->isStatic()) {
            throw new Error("u-model=\"$path\": $name isn't a public #[State] property");
        }
        if ($property->isReadOnly() || $property->isPrivateSet() || $property->isProtectedSet()) {
            throw new Error("u-model=\"$path\": $name is read-only to the page");
        }
        return $property;
    }

    private function coerce(string|bool $value, ?string $type): mixed
    {
        return match ($type) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => is_bool($value) ? $value : $value !== '',
            default => $value,
        };
    }

    /** Each u-model element shows its state (new HTML has none of what was typed). */
    private function showModels(): void
    {
        foreach ($this->root->querySelectorAll('[u-model]') as $element) {
            $value = $this->readPath($element->getAttribute('u-model'));
            $type = $element->type;
            if ($type === 'checkbox') {
                $element->checked = (bool) $value;
            } elseif ($type === 'radio') {
                $element->checked = $element->value === (string) $value;
            } elseif ($element->value !== (string) $value) {
                // Only when it differs: it may be the input being typed in, kept by morph.
                $element->value = (string) $value;
            }
        }
    }

    private function readPath(string $path): mixed
    {
        $keys = explode('.', $path);
        $this->modelProperty($keys[0], $path);
        $value = $this->{$keys[0]};
        foreach (array_slice($keys, 1) as $key) {
            $value = is_array($value) ? ($value[$key] ?? null) : null;
        }
        return $value;
    }

    /** The focused u-model element and its caret, to put back after the HTML is replaced. */
    private function focused(): ?array
    {
        $active = U::document()->activeElement;
        if ($active === null || !$this->root->contains($active)) {
            return null;
        }
        $path = $active->getAttribute('u-model');
        if ($path === null) {
            return null;
        }
        return [$path, $active->selectionStart, $active->selectionEnd];
    }

    private function restore(?array $focus): void
    {
        if ($focus === null) {
            return;
        }
        [$path, $start, $end] = $focus;
        $element = $this->root->querySelector('[u-model="' . addcslashes($path, '"\\') . '"]');
        if ($element === null) {
            return;
        }
        $element->focus();
        if ($start !== null) {
            $element->setSelectionRange($start, $end);
        }
    }
}

/**
 * A component from a Blade file's source: the PHP block at the top makes it (`new class extends
 * Component { ... };`), the rest is its template. A file with no PHP block is a template with no
 * state. `$file` names it in error messages.
 */
function blade(string $source, string $file): Component
{
    $block = '';
    $template = $source;
    if (str_starts_with(ltrim($source), '<?php')) {
        $end = strpos($source, '?>');
        if ($end === false) {
            throw new Error("$file: the PHP block at the top needs a closing ?>");
        }
        $block = substr($source, 0, $end + 2);
        $template = substr($source, $end + 2);
    }

    // The block's `use` lines, for the template too: it's compiled separately.
    $uses = '';
    $class = preg_match('/\bnew\s+class\b/', $block, $m, PREG_OFFSET_CAPTURE) ? $m[0][1] : null;
    if ($class !== null) {
        preg_match_all('/^\s*use\s+[^;]+;/m', substr($block, 0, $class), $found);
        $uses = implode(' ', array_map('trim', $found[0]));
    }

    if ($block === '') {
        $component = new class extends Component {};
    } elseif ($class === null) {
        throw new Error("$file: the PHP block must make the component: new class extends Component { ... };");
    } else {
        // eval'd from the block's own opening tag, so its line numbers are the file's.
        $code = '?>' . substr_replace($block, 'return new class', $class, strlen($m[0][0]));
        try {
            $component = eval($code);
        } catch (ParseError $e) {
            throw new Error("{$e->getMessage()} in $file:{$e->getLine()}", 0, $e);
        }
        if (!$component instanceof Component) {
            throw new Error("$file: the PHP block's class must extend " . Component::class);
        }
    }

    // The template's first line is the file's line after the block's last.
    $padding = str_repeat("\n", substr_count($block, "\n"));
    $code = "$uses return function (array \$__data): void { extract(\$__data, EXTR_SKIP);$padding?>"
        . Blade::compile($template) . '<?php };';
    try {
        $view = eval($code);
    } catch (ParseError $e) {
        throw new Error("{$e->getMessage()} in $file:{$e->getLine()} (in the template)", 0, $e);
    }

    // The template runs as the component: $this, and its private methods, work in it.
    Closure::bind(function () use ($view, $component, $file) {
        $this->view = Closure::bind($view, $component, get_class($component));
        $this->file = $file;
    }, $component, Component::class)();
    return $component;
}

/** Blade, compiled to PHP. A subset: see the top of this file. */
final class Blade
{
    public static function compile(string $template): string
    {
        // Text that's printed as written: @verbatim blocks, @{{ }} and @{!! !!}. @php blocks are
        // already PHP. Each is set aside until the end, so nothing below touches it.
        $kept = [];
        $keep = function (string $text) use (&$kept): string {
            $kept[] = $text;
            return "\x00" . (count($kept) - 1) . "\x00";
        };
        $t = preg_replace_callback('/@verbatim(.*?)@endverbatim/s', fn($m) => $keep($m[1]), $template);
        $t = preg_replace_callback('/@php(?!\s*\()(.*?)@endphp/s', fn($m) => $keep("<?php{$m[1]}?>"), $t);
        $t = preg_replace_callback('/@(\{\{.*?\}\}|\{!!.*?!!\})/s', fn($m) => $keep($m[1]), $t);
        $t = preg_replace('/\{\{--.*?--\}\}/s', '', $t);

        // @directives, with balanced parentheses (quoted strings may hold any).
        $forelse = [];
        $next = 0;
        $t = preg_replace_callback(
            '/\B@(@?\w+)([ \t]*)(\((?:[^()\'"]++|\'(?:[^\'\\\\]|\\\\.)*+\'|"(?:[^"\\\\]|\\\\.)*+"|(?3))*\))?/s',
            function ($m) use (&$forelse, &$next) {
                $args = isset($m[3]) ? substr($m[3], 1, -1) : null;
                $compiled = self::directive($m[1], $args, $forelse, $next);
                return $compiled ?? $m[0];
            },
            $t,
        );
        if ($forelse) {
            throw new Error('@forelse without @endforelse');
        }

        $t = preg_replace_callback('/\{!!\s*(.+?)\s*!!\}/s', fn($m) => "<?php echo {$m[1]}; ?>", $t);
        $t = preg_replace_callback('/\{\{\s*(.+?)\s*\}\}/s', fn($m) => "<?php echo \\Unscripted\\Ui\\e({$m[1]}); ?>", $t);

        return preg_replace_callback('/\x00(\d+)\x00/', fn($m) => $kept[(int) $m[1]], $t);
    }

    /** One directive as PHP, or null for an @word that isn't one (an email, a CSS @media). */
    private static function directive(string $name, ?string $args, array &$forelse, int &$next): ?string
    {
        $needs = fn() => $args ?? throw new Error("@$name needs its (...)");
        return match ($name) {
            'if' => "<?php if ({$needs()}): ?>",
            'elseif' => "<?php elseif ({$needs()}): ?>",
            'else' => '<?php else: ?>',
            'unless' => "<?php if (!({$needs()})): ?>",
            'isset' => "<?php if (isset({$needs()})): ?>",
            'endif', 'endunless', 'endisset', 'endempty' => '<?php endif; ?>',
            'foreach' => "<?php foreach ({$needs()}): ?>",
            'endforeach' => '<?php endforeach; ?>',
            'for' => "<?php for ({$needs()}): ?>",
            'endfor' => '<?php endfor; ?>',
            'while' => "<?php while ({$needs()}): ?>",
            'endwhile' => '<?php endwhile; ?>',
            'forelse' => (function () use ($needs, &$forelse, &$next) {
                $forelse[] = $empty = '$__empty' . $next++;
                return "<?php $empty = true; foreach ({$needs()}): $empty = false; ?>";
            })(),
            // @empty is forelse's else, or with arguments, `if (empty(...))`.
            'empty' => $args !== null
                ? "<?php if (empty($args)): ?>"
                : '<?php endforeach; if (' . (end($forelse) ?: throw new Error('@empty outside @forelse')) . '): ?>',
            'endforelse' => array_pop($forelse) !== null ? '<?php endif; ?>' : throw new Error('@endforelse without @forelse'),
            'break', 'continue' => $args === null ? "<?php $name; ?>" : "<?php if ($args) $name; ?>",
            'php' => "<?php ({$needs()}); ?>",
            'class' => "class=\"<?php echo \\Unscripted\\Ui\\classes({$needs()}); ?>\"",
            'checked', 'selected', 'disabled', 'readonly', 'required' => "<?php if ({$needs()}) echo '$name'; ?>",
            'json' => "<?php echo json_encode({$needs()}, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>",
            default => str_starts_with($name, '@') ? $name . ($args !== null ? "($args)" : '') : null,
        };
    }
}

/** Mounts the page's Blade components: <script type="text/blade" src="x.blade.php" data-mount="#app">,
 * or with the Blade inline instead of a src. */
function mountPage(): void
{
    foreach (U::document()->querySelectorAll('script[type="text/blade"]') as $script) {
        $target = $script->getAttribute('data-mount');
        if ($target === null) {
            throw new Error('a <script type="text/blade"> needs data-mount, the selector to mount it on');
        }
        $src = $script->src;
        if ($src === '') {
            blade($script->textContent, 'inline Blade for ' . $target)->mount($target);
            continue;
        }
        U::fetch($src)->then(function ($response) use ($src, $target) {
            if (!$response->ok) {
                throw new Error("couldn't load $src: {$response->status}");
            }
            $response->text()->then(fn(string $source) => blade($source, $src)->mount($target));
        }, fn($error) => U::console()->error("couldn't load $src: " . $error));
    }
}

mountPage();
