<?php

namespace App\Livewire\Legal;

use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Static allergen/nutrition disclaimer page — SAA-21 copy variant #4
 * (footer/Terms full paragraph). Reachable from the persistent footer link
 * (layouts.app) added in Phase 2.4; content is copy-only, no logic.
 */
#[Layout('layouts.app')]
class Disclaimer extends Component
{
    public function render()
    {
        return view('livewire.legal.disclaimer');
    }
}
