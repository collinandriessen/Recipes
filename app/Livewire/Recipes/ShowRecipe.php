<?php

namespace App\Livewire\Recipes;

use App\Jobs\RecomputeOnEditJob;
use App\Models\Ingredient;
use App\Models\Recipe;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Recipe detail / import-review page. Three states:
 *  - pending: ImportRecipeJob hasn't finished yet — polls until it does.
 *  - needs_review: JSON-LD parsed successfully but is NOT auto-trusted —
 *    parsed ingredient lines + their auto-matched ingredient/allergens are
 *    shown next to the raw source text for the user to confirm or edit
 *    before anything is treated as final (architecture §3: "never silently
 *    trust parser output for allergen-relevant data").
 *  - manual / confirmed: normal recipe view (manual entry owns the
 *    manual-status editing UI at ManualEntry).
 */
#[Layout('layouts.app')]
class ShowRecipe extends Component
{
    public Recipe $recipe;

    /** @var array<int, string> */
    public array $editedIngredientText = [];

    public function mount(Recipe $recipe): void
    {
        abort_unless($recipe->user_id === Auth::id(), 403);

        $this->recipe = $recipe->load(['ingredients.ingredient.allergens', 'nutrition', 'allergenFlags']);
        $this->editedIngredientText = $this->recipe->ingredients->pluck('raw_text', 'id')->all();
    }

    public function confirmImport(): void
    {
        foreach ($this->editedIngredientText as $lineId => $text) {
            $this->recipe->ingredients()->whereKey($lineId)->update(['raw_text' => trim($text)]);
        }

        $this->recipe->update(['import_status' => 'confirmed']);

        RecomputeOnEditJob::dispatch($this->recipe->id);

        $this->recipe->refresh()->load(['ingredients.ingredient.allergens', 'nutrition', 'allergenFlags']);
    }

    public function render()
    {
        // Cheap poll-driven refresh while the background pipeline is still
        // working (pending import, or needs_review rows still being matched).
        $this->recipe->refresh()->load(['ingredients.ingredient.allergens', 'nutrition', 'allergenFlags']);

        return view('livewire.recipes.show-recipe');
    }
}
