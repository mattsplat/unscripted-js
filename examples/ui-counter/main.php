<?php
// A counter with a step you can type: an action with an argument, and u-model on a number.

use Unscripted\Ui\{Component, State};

final class Counter extends Component
{
    #[State] public int $count = 0;
    #[State] public int $step = 1;

    public function add(int $times = 1): void
    {
        $this->count += $this->step * $times;
    }

    public function reset(): void
    {
        $this->count = 0;
    }

    protected function render(): void { ?>
        <p class="count"><?= $this->count ?></p>
        <div class="row">
            <button u-on:click="add(-1)">− <?= $this->step ?></button>
            <button u-on:click="add">+ <?= $this->step ?></button>
            <button u-on:click="add(10)">+ <?= $this->step * 10 ?></button>
            <button u-on:click="reset" <?= $this->count === 0 ? 'disabled' : '' ?>>Reset</button>
        </div>
        <label>Step <input type="number" u-model="step" min="1"></label>
    <?php }
}

new Counter()->mount('#app');
