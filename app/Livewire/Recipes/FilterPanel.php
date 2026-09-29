<?php

namespace App\Livewire\Recipes;

use App\Models\Allergen;
use App\Models\UserExclusion;
use App\Services\Billing\FeatureGate;
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
 *
 * Architecture §6 / SAA-19: the combined macro + allergen filter is the
 * paid-tier hook. FeatureGate is checked in mount() (so a free user's inputs
 * render already-cleared/disabled, no flash of a working filter) AND in
 * updated() (so a crafted wire:model payload can't bypass the gate — the
 * gate no-ops the value server-side before it ever reaches
 * emitFiltersUpdated). $isPaidTier / $upsellVisible drive the upsell state
 * in the view; FilterPanel never redirects or blocks rendering, it just
 * disables the gated inputs and shows the upsell message inline.
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

    public bool $isPaidTier = false;

    public function mount(array $initial = []): void
    {
        $gate = FeatureGate::forUser(Auth::user());
        $this->isPaidTier = $gate->canUseMacroAllergenFilters();

        $initial = $gate->gateFilterInput($initial);

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
     * outbound event, regardless of which field changed. Gated properties
     * are snapped back to their cleared value here for free-tier users
     * before the event goes out, so the parent's query never sees a gated
     * value even if a client-side edit slipped through.
     */
    public function updated(string $property): void
    {
        $gate = FeatureGate::forUser(Auth::user());

        if (! $gate->canUseMacroAllergenFilters() && $gate->isGatedProperty($property)) {
            $this->{$property} = in_array($property, FeatureGate::GATED_ALLERGEN_KEYS, true) ? [] : null;
        }

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
        $filters = FeatureGate::forUser(Auth::user())->gateFilterInput([
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

        $this->dispatch('filters-updated', filters: $filters);
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
