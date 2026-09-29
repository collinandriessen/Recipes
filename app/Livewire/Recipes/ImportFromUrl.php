<?php

namespace App\Livewire\Recipes;

use App\Jobs\ImportRecipeJob;
use App\Models\Recipe;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * URL import entry point (architecture §3). Creates a `pending` placeholder
 * Recipe immediately (so we have a stable id to redirect to) then dispatches
 * ImportRecipeJob to fetch/parse and fill it in. The recipe's show page
 * polls (wire:poll) while import_status stays `pending`, then renders either
 * the needs_review confirmation UI or the manual-entry form once the job
 * completes.
 */
#[Layout('layouts.app')]
class ImportFromUrl extends Component
{
    public string $url = '';

    public function import()
    {
        $this->validate([
            'url' => ['required', 'url', 'max:2048'],
        ]);

        $recipe = Recipe::query()->create([
            'user_id' => Auth::id(),
            'title' => 'Importing…',
            'source_url' => $this->url,
            'source_type' => 'url_import',
            'servings' => 1,
            'import_status' => 'pending',
        ]);

        ImportRecipeJob::dispatch(Auth::id(), $this->url, $recipe->id)->onQueue('imports');

        return $this->redirectRoute('recipes.show', $recipe, navigate: true);
    }

    public function render()
    {
        return view('livewire.recipes.import-from-url');
    }
}
