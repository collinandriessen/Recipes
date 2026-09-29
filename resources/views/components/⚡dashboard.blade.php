<?php

use Livewire\Component;

new class extends Component
{
    public int $count = 0;

    public function increment(): void
    {
        $this->count++;
    }
};
?>

<div class="max-w-xl mx-auto mt-16 p-6 rounded-lg border border-gray-200 text-center">
    <h1 class="text-xl font-semibold mb-4">Livewire is working</h1>
    <p class="text-3xl font-bold mb-4">{{ $count }}</p>
    <button
        type="button"
        wire:click="increment"
        class="px-4 py-2 rounded bg-indigo-600 text-white hover:bg-indigo-700"
    >
        Increment
    </button>
</div>
