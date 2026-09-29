<?php

namespace App\Livewire\Recipes;

use App\Models\Allergen;
use App\Models\UserExclusion;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Architecture §4: FilterPanel is a plain child component — it owns none of
 * the result set, only the filter *inputs*. It receives the current filter
 * values from RecipeLibrary (the single source of truth, which mirrors them
 * into the URL query string) and pushes changes back up via a debounced
 * `filters-updated` event so the parent can re-run RecipeFilterQuery without
 * a network round trip on every keystroke.
 *
 * All range/checkbox inputs use wire:model.live.debounce.300ms in the view;
 * `updated()` fires after that debounce and is where the event actually
 * goes out, so a burst of edits (e.g. dragging a range slider) collapses
 * into a single filters-updated dispatch.
 */
class FilterPanel extends Component
{
    public ?float $calorieMin = null;

    public ?float $calorieMax = null;

    public ?float $proteinMin = null;

    public ?float $proteinMax = null;

    public ?float $carbsMin = null;

    public ?float $carbsMax = null;

    public ?float $fatMin = null;

    public ?float $fatMax = null;

    /** @var array<int, int> */
    public array $excludedAllergenIds = [];

    /** @var array<int, int> */
    public array $excludedCustomExclusionIds = [];

    public string $search = '';

    /** Upper bounds shown on the sliders — generous, real-world recipe range. */
    public int $calorieCeiling = 2000;

    public int $proteinCeiling = 150;

    public int $carbsCeiling = 200;

    public int $fatCeiling = 150;

    public function mount(array $initial = []): void
    {
        foreach ($initial as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }

    /**
     * Livewire calls this after ANY public property changes (post-debounce,
     * since the inputs use wire:model.live.debounce.300ms) — this is the
     * single choke point that turns "the user changed something" into one
     * outbound event, regardless of which field changed.
     */
    public function updated(): void
    {
        $this->emitFiltersUpdated();
    }

    public function clearAll(): void
    {
        $this->reset([
            'calorieMin', 'calorieMax', 'proteinMin', 'proteinMax',
            'carbsMin', 'carbsMax', 'fatMin', 'fatMax',
            'excludedAllergenIds', 'excludedCustomExclusionIds', 'search',
        ]);

        $this->emitFiltersUpdated();
    }

    private function emitFiltersUpdated(): void
    {
        $this->dispatch('filters-updated', filters: [
            'calorieMin' => $this->calorieMin,
            'calorieMax' => $this->calorieMax,
            'proteinMin' => $this->proteinMin,
            'proteinMax' => $this->proteinMax,
            'carbsMin' => $this->carbsMin,
            'carbsMax' => $this->carbsMax,
            'fatMin' => $this->fatMin,
            'fatMax' => $this->fatMax,
            'excludedAllergenIds' => $this->excludedAllergenIds,
            'excludedCustomExclusionIds' => $this->excludedCustomExclusionIds,
            'search' => $this->search,
        ]);
    }

    public function render()
    {
        return view('livewire.recipes.filter-panel', [
            'topAllergens' => Allergen::query()->where('is_top9', true)->orderBy('name')->get(),
            'customExclusions' => Auth::check()
                ? UserExclusion::query()->where('user_id', Auth::id())->orderBy('label')->get()
                : collect(),
        ]);
    }
}
