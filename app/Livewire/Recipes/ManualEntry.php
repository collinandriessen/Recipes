<?php

namespace App\Livewire\Recipes;

use App\Jobs\RecomputeOnEditJob;
use App\Models\Recipe;
use App\Services\Ingredients\IngredientFuzzyMatcher;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Manual entry — first-class path per architecture §3, not an afterthought:
 * this is what every recipe hits when JSON-LD import fails/absent, and it's
 * also directly reachable for "just type in a recipe." Fast per-line
 * ingredient autocomplete against the `ingredients` dictionary
 * (IngredientFuzzyMatcher::suggest, local-only — no FDC round-trip while
 * typing) is the point: minimal friction per line since this carries more
 * of the activation funnel than originally planned (no vendor parser
 * fallback).
 *
 * Works both for a freshly-created blank recipe and for filling in a
 * `manual` stub created by ImportRecipeJob (pre-filled title/photo from
 * <title>/OpenGraph only).
 */
#[Layout('layouts.app')]
class ManualEntry extends Component
{
    public ?int $recipeId = null;

    public string $title = '';

    public int $servings = 1;

    /** @var array<int, array{text: string, sort_order: int}> */
    public array $ingredientLines = [];

    public string $instructionsText = '';

    public string $activeAutocompleteText = '';

    /** @var array<int, string> */
    public array $autocompleteSuggestions = [];

    public function mount(?Recipe $recipe = null): void
    {
        if ($recipe !== null) {
            $this->recipeId = $recipe->id;
            $this->title = $recipe->title === 'Importing…' ? '' : $recipe->title;
            $this->servings = $recipe->servings;
            $this->instructionsText = collect($recipe->instructions ?? [])->implode("\n");

            $this->ingredientLines = $recipe->ingredients->isNotEmpty()
                ? $recipe->ingredients->map(fn ($i) => ['text' => $i->raw_text, 'sort_order' => $i->sort_order])->values()->all()
                : [['text' => '', 'sort_order' => 0]];
        } else {
            $this->ingredientLines = [['text' => '', 'sort_order' => 0]];
        }
    }

    public function addIngredientLine(): void
    {
        $this->ingredientLines[] = ['text' => '', 'sort_order' => count($this->ingredientLines)];
    }

    public function removeIngredientLine(int $index): void
    {
        unset($this->ingredientLines[$index]);
        $this->ingredientLines = array_values($this->ingredientLines);
    }

    /**
     * Called on wire:model.live per-keystroke from the active ingredient
     * line to drive the autocomplete dropdown. Local-only lookup — fast
     * enough to run per keystroke, no FDC call (that only happens on save,
     * via IngredientFuzzyMatcher::match inside RecomputeOnEditJob).
     */
    public function updateAutocomplete(string $partial): void
    {
        $this->activeAutocompleteText = $partial;

        $this->autocompleteSuggestions = trim($partial) === ''
            ? []
            : collect(app(IngredientFuzzyMatcher::class)->suggest($partial))
                ->pluck('name')
                ->all();
    }

    public function save()
    {
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'servings' => ['required', 'integer', 'min:1', 'max:100'],
            'ingredientLines' => ['array'],
            'ingredientLines.*.text' => ['nullable', 'string', 'max:500'],
        ]);

        $nonEmptyLines = collect($this->ingredientLines)
            ->pluck('text')
            ->map(fn ($t) => trim($t))
            ->filter(fn ($t) => $t !== '')
            ->values();

        $instructions = collect(preg_split('/\r?\n/', $this->instructionsText))
            ->map(fn ($line) => trim($line))
            ->filter(fn ($line) => $line !== '')
            ->values()
            ->all();

        $attributes = [
            'user_id' => Auth::id(),
            'title' => $this->title,
            'source_type' => $this->recipeId === null ? 'manual' : null, // preserve url_import provenance when filling in an import stub.
            'servings' => $this->servings,
            'instructions' => $instructions,
            'import_status' => 'confirmed',
        ];

        // Only overwrite source_type for brand-new manual recipes; don't
        // clobber "url_import" provenance on an imported stub being filled in.
        if ($attributes['source_type'] === null) {
            unset($attributes['source_type']);
        }

        $recipe = $this->recipeId !== null
            ? tap(Recipe::query()->findOrFail($this->recipeId))->update($attributes)
            : Recipe::query()->create($attributes + ['source_type' => 'manual']);

        $recipe->ingredients()->delete();

        foreach ($nonEmptyLines as $index => $line) {
            $recipe->ingredients()->create([
                'raw_text' => $line,
                'sort_order' => $index,
                'match_status' => 'unmatched',
            ]);
        }

        RecomputeOnEditJob::dispatch($recipe->id);

        return $this->redirectRoute('recipes.show', $recipe, navigate: true);
    }

    public function render()
    {
        return view('livewire.recipes.manual-entry');
    }
}
