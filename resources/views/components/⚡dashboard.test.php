<?php

use Livewire\Livewire;

it('renders successfully', function () {
    Livewire::test('dashboard')
        ->assertStatus(200);
});
