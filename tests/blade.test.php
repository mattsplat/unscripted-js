<?php
// The Blade compiler and single-file components: blade() and Blade::compile() in ui.php.

use function Unscripted\Ui\blade;

/** A template rendered by a component with these public properties. */
function render(string $template, array $props = []): string
{
    $declared = implode(' ', array_map(
        fn($name, $value) => "public \$$name = " . var_export($value, true) . ';',
        array_keys($props),
        $props,
    ));
    return html(blade("<?php\nuse Unscripted\\Ui\\Component;\nnew class extends Component { $declared };\n?>$template", 'test.blade.php'));
}

// A whole file: a PHP block with use lines, state and a private method, then the template.
$component = blade(<<<'BLADE'
<?php
use Unscripted\Ui\{Component, State};

new class extends Component {
    #[State] public array $todos = [['title' => 'Milk & <b>eggs</b>', 'done' => true], ['title' => 'Dog', 'done' => false]];
    private function left(): int { return count(array_filter($this->todos, fn($t) => !$t['done'])); }
};
?>
<ul>@foreach ($todos as $t)<li @class(['done' => $t['done'], 'item'])>{{ $t['title'] }}</li>@endforeach</ul>
<p>{{ $this->left() }} left, {!! '<i>raw</i>' !!}</p>
BLADE, 'todos.blade.php');
check('a single-file component', html($component),
    '<ul><li class="done item">Milk &amp; &lt;b&gt;eggs&lt;/b&gt;</li><li class="item">Dog</li></ul> <p>1 left, <i>raw</i></p>');
check('a file with no PHP block is a template', html(blade('<p>{{ 1 + 1 }}</p>', 'plain.blade.php')), '<p>2</p>');

// Echoes
check('{{ }} escapes', render('{{ $s }}', ['s' => '<a href="x">\'&']), '&lt;a href=&quot;x&quot;&gt;&apos;&amp;');
check('{{ null }} is empty', render('[{{ $n }}]', ['n' => null]), '[]');
check('{!! !!} does not escape', render('{!! $s !!}', ['s' => '<b>x</b>']), '<b>x</b>');
check('{{-- comments --}} are dropped', render('a{{-- hidden {{ $nope }} @if --}}b'), 'ab');
check('@{{ }} and @@ print as written', render('@{{ $n }} @{!! $n !!} @@if'), '{{ $n }} {!! $n !!} @if');
check('@verbatim', render('@verbatim {{ $x }} @if @endverbatim'), '{{ $x }} @if');

// Conditions
check('@if / @elseif / @else', render('@if($n > 5) big @elseif($n === 3) three @else other @endif', ['n' => 3]), 'three');
check('@unless', render('@unless($n) zero @endunless', ['n' => 0]), 'zero');
check('@isset', render('@isset($n) set @endisset', ['n' => 1]), 'set');
check('@empty(...)', render('@empty($a) none @endempty', ['a' => []]), 'none');
check('parentheses inside quoted arguments', render("@if(\$s === ')(') yes @else no @endif", ['s' => ')(']), 'yes');

// Loops
check('@foreach with keys', render('@foreach($a as $k => $v){{ $k }}={{ $v }};@endforeach', ['a' => ['x' => 1, 'y' => 2]]), 'x=1;y=2;');
check('@forelse with items', render('@forelse($a as $x){{ $x }},@empty nothing @endforelse', ['a' => [1, 2]]), '1,2,');
check('@forelse with none', render('@forelse($a as $x){{ $x }}@empty nothing @endforelse', ['a' => []]), 'nothing');
check('nested @forelse', render('@forelse($a as $r)[@forelse($r as $x){{ $x }}@empty-@endforelse]@empty none @endforelse', ['a' => [[1], []]]), '[1][-]');
check('@for, @break(...), @continue(...)', render('@for($i = 0; $i < 9; $i++)@continue($i === 1)@break($i === 4){{ $i }}@endfor'), '023');
check('@while', render('@php($i = 0)@while($i < 3){{ $i++ }}@endwhile'), '012');

// Attributes
check('@class', render('<p @class([\'a\', \'b\' => $on, \'c\' => !$on])>', ['on' => true]), '<p class="a b">');
check('@class escapes', render('<p @class([$c])>', ['c' => '" onclick="x']), '<p class="&quot; onclick=&quot;x">');
check('@checked, @disabled, @selected', render('<input @checked($on)><input @disabled(!$on)><option @selected(true)>', ['on' => true]), '<input checked><input ><option selected>');
check('@readonly, @required', render('<input @readonly(true) @required(false)>'), '<input readonly >');

// Other
check('@php ... @endphp', render('@php $x = 2 * 21; @endphp{{ $x }}'), '42');
check('@json is safe inside <script>', render('<script>let d = @json($a)</script>', ['a' => ['</script>', "it's"]]),
    // json_encode's escapes, built up so no tool or editor turns them into the characters.
    strtr('<script>let d = ["#3C\/script#3E","it#27s"]</script>', ['#' => '\\' . 'u00']));
check('other @words are left alone', render('a@b.com <style>@media (x) {}</style> @click @unknown(1)'), 'a@b.com <style>@media (x) {}</style> @click @unknown(1)');
check('u-on and u-model pass through', render('<button u-on:click="remove({{ $n }})" u-model="x">', ['n' => 2]), '<button u-on:click="remove(2)" u-model="x">');
check('$this and private methods', html(blade("<?php\nuse Unscripted\\Ui\\Component;\nnew class extends Component { private function secret(): string { return 'shh'; } };\n?>{{ \$this->secret() }}", 'this.blade.php')), 'shh');

// Mistakes
fails('@if without (...)', fn() => blade('@if yes @endif', 'x.blade.php'), '@if needs its (...)');
fails('@empty outside @forelse', fn() => blade('@empty', 'x.blade.php'), '@empty outside @forelse');
fails('@forelse with no end', fn() => blade('@forelse($a as $b)', 'x.blade.php'), '@forelse without @endforelse');
fails('@endforelse with no start', fn() => blade('@endforelse', 'x.blade.php'), '@endforelse without @forelse');
fails('a PHP block with no closing tag', fn() => blade('<?php new class extends Component {};', 'x.blade.php'), 'needs a closing ?>');
fails('a PHP block that makes no component', fn() => blade('<?php $x = 1; ?>hi', 'x.blade.php'), 'must make the component');
fails('a class that is not a Component', fn() => blade('<?php new class {}; ?>hi', 'x.blade.php'), 'must extend');

// Errors name the file and its line
fails('a PHP syntax error', fn() => blade("<?php\nuse Unscripted\\Ui\\Component;\nnew class extends Component {\n  public function x() { oops oops }\n};\n?>hi", 'bad.blade.php'),
    'unexpected identifier "oops" in bad.blade.php:4');
fails('a template syntax error', fn() => blade("<?php\nuse Unscripted\\Ui\\Component;\nnew class extends Component {};\n?>\n<p>ok</p>\n{{ \$a + }}", 'tpl.blade.php'),
    'in tpl.blade.php:6 (in the template)');
fails('an error while rendering', function () {
    $component = blade("<?php\nuse Unscripted\\Ui\\Component;\nnew class extends Component {};\n?>\n<p>\n{{ intdiv(1, 0) }}</p>", 'run.blade.php');
    Closure::bind(fn() => $this->html(), $component, Unscripted\Ui\Component::class)();
}, 'Division by zero in run.blade.php:6');

done();
