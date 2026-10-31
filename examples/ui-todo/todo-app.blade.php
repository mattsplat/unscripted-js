<?php
// A todo list as one Blade file: the component's class here, its template below. A form that
// submits to an action, u-model on a text input and on checkboxes inside a list, keyed list
// items (u-key), filters, and a derived count. Everything renders from the component's state.

use Unscripted\Ui\{Component, State};

new class extends Component {
    #[State] public array $todos = [];
    #[State] public string $draft = '';
    #[State] public string $filter = 'all';
    private int $nextId = 1;

    public function add(): void
    {
        $title = trim($this->draft);
        if ($title === '') return;
        $this->todos[] = ['id' => $this->nextId++, 'title' => $title, 'done' => false];
        $this->draft = '';
    }

    public function remove(int $i): void
    {
        array_splice($this->todos, $i, 1);
    }

    public function show(string $filter): void
    {
        $this->filter = $filter;
    }

    public function clearDone(): void
    {
        $this->todos = array_values(array_filter($this->todos, fn($t) => !$t['done']));
    }

    /** The todos the filter shows, keyed by their index in $todos (u-model paths use it). */
    private function visible(): array
    {
        return array_filter($this->todos, fn($t) => match ($this->filter) {
            'open' => !$t['done'],
            'done' => $t['done'],
            default => true,
        });
    }

    private function remaining(): int
    {
        return count(array_filter($this->todos, fn($t) => !$t['done']));
    }
};
?>

<form u-on:submit.prevent="add">
    <input u-model="draft" placeholder="What needs doing?" autofocus>
    <button @disabled(trim($draft) === '')>Add</button>
</form>

<ul>
    @foreach ($this->visible() as $i => $todo)
        <li u-key="{{ $todo['id'] }}" @class(['done' => $todo['done']])>
            <label>
                <input type="checkbox" u-model="todos.{{ $i }}.done">
                {{ $todo['title'] }}
            </label>
            <button class="remove" u-on:click="remove({{ $i }})" title="Remove">×</button>
        </li>
    @endforeach
</ul>

@if ($todos)
    <footer>
        <span>{{ $this->remaining() }} left</span>
        @foreach (['all' => 'All', 'open' => 'Open', 'done' => 'Done'] as $key => $label)
            <button u-on:click='show("{{ $key }}")' @class(['on' => $filter === $key])>{{ $label }}</button>
        @endforeach
        @if ($this->remaining() < count($todos))
            <button u-on:click="clearDone">Clear done</button>
        @endif
    </footer>
@else
    <p class="empty">Nothing to do.</p>
@endif
