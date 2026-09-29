<div class="bg-white border border-gray-200 rounded-lg p-5 space-y-6">
    @unless ($isPaidTier)
        {{-- Architecture §6 / SAA-19: FeatureGate upsell state. Macro range +
             allergen exclusion are the paid-tier hook per the roadmap; free
             users keep search/tags/collections/meal-plan/shopping-list in
             full, this banner only covers the gated section below. --}}
        <div class="rounded border border-indigo-200 bg-indigo-50 px-3 py-2.5 text-sm text-indigo-800">
            <p class="font-medium">Macro & allergen filtering is a paid feature.</p>
            <p class="text-indigo-700 text-xs mt-0.5">
                Upgrade to filter your library by calories/protein/carbs/fat and to exclude
                allergens. The controls below are disabled on the free plan.
            </p>
        </div>
    @endunless

    <div>
        <label for="filter-search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
        <input
            id="filter-search"
            type="text"
            wire:model.live.debounce.300ms="search"
            placeholder="Search your recipes…"
            class="w-full rounded border-gray-300 shadow-sm text-sm"
        >
    </div>

    <div class="space-y-4 {{ $isPaidTier ? '' : 'opacity-50' }}">
        <h3 class="text-sm font-semibold text-gray-700">Macros (per recipe)</h3>

        <div>
            <div class="flex justify-between text-xs text-gray-500 mb-1">
                <span>Calories</span>
                <span>{{ $calorieMin ?? 0 }}–{{ $calorieMax ?? $calorieCeiling }} kcal</span>
            </div>
            <div class="flex gap-2">
                <input type="range" min="0" max="{{ $calorieCeiling }}" step="25" @disabled(! $isPaidTier)
                    wire:model.live.debounce.300ms="calorieMin" class="w-full">
                <input type="range" min="0" max="{{ $calorieCeiling }}" step="25" @disabled(! $isPaidTier)
                    wire:model.live.debounce.300ms="calorieMax" class="w-full">
            </div>
        </div>

        <div>
            <div class="flex justify-between text-xs text-gray-500 mb-1">
                <span>Protein</span>
                <span>{{ $proteinMin ?? 0 }}–{{ $proteinMax ?? $proteinCeiling }} g</span>
            </div>
            <div class="flex gap-2">
                <input type="range" min="0" max="{{ $proteinCeiling }}" step="5" @disabled(! $isPaidTier)
                    wire:model.live.debounce.300ms="proteinMin" class="w-full">
                <input type="range" min="0" max="{{ $proteinCeiling }}" step="5" @disabled(! $isPaidTier)
                    wire:model.live.debounce.300ms="proteinMax" class="w-full">
            </div>
        </div>

        <div>
            <div class="flex justify-between text-xs text-gray-500 mb-1">
                <span>Carbs</span>
                <span>{{ $carbsMin ?? 0 }}–{{ $carbsMax ?? $carbsCeiling }} g</span>
            </div>
            <div class="flex gap-2">
                <input type="range" min="0" max="{{ $carbsCeiling }}" step="5" @disabled(! $isPaidTier)
                    wire:model.live.debounce.300ms="carbsMin" class="w-full">
                <input type="range" min="0" max="{{ $carbsCeiling }}" step="5" @disabled(! $isPaidTier)
                    wire:model.live.debounce.300ms="carbsMax" class="w-full">
            </div>
        </div>

        <div>
            <div class="flex justify-between text-xs text-gray-500 mb-1">
                <span>Fat</span>
                <span>{{ $fatMin ?? 0 }}–{{ $fatMax ?? $fatCeiling }} g</span>
            </div>
            <div class="flex gap-2">
                <input type="range" min="0" max="{{ $fatCeiling }}" step="5" @disabled(! $isPaidTier)
                    wire:model.live.debounce.300ms="fatMin" class="w-full">
                <input type="range" min="0" max="{{ $fatCeiling }}" step="5" @disabled(! $isPaidTier)
                    wire:model.live.debounce.300ms="fatMax" class="w-full">
            </div>
        </div>
    </div>

    <div class="{{ $isPaidTier ? '' : 'opacity-50' }}">
        <h3 class="text-sm font-semibold text-gray-700 mb-2">Exclude allergens</h3>

        {{-- SAA-21 placement priority #4 (filter-level disclaimer). Shown
             persistently under the panel, not just once, since it's cheap
             and this is the exact page a wrongly-excluded/included allergen
             would hurt the most. --}}
        <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-1.5 mb-2">
            Filtering by allergen removes recipes RecipeFit's automated tagging flags as containing
            that allergen. This filter is a convenience, not a safety guarantee — always verify
            ingredients yourself for any recipe before eating it if you have a food allergy.
        </p>

        <div class="grid grid-cols-2 gap-1.5">
            @foreach ($topAllergens as $allergen)
                <label class="flex items-center gap-1.5 text-sm text-gray-700">
                    <input
                        type="checkbox"
                        value="{{ $allergen->id }}"
                        @disabled(! $isPaidTier)
                        wire:model.live.debounce.300ms="excludedAllergenIds"
                        class="rounded border-gray-300"
                    >
                    {{ $allergen->name }}
                </label>
            @endforeach
        </div>

        @if ($customExclusions->isNotEmpty())
            <h4 class="text-xs font-medium text-gray-500 mt-3 mb-1">Your custom exclusions</h4>
            <div class="grid grid-cols-2 gap-1.5">
                @foreach ($customExclusions as $exclusion)
                    <label class="flex items-center gap-1.5 text-sm text-gray-700">
                        <input
                            type="checkbox"
                            value="{{ $exclusion->id }}"
                            @disabled(! $isPaidTier)
                            wire:model.live.debounce.300ms="excludedCustomExclusionIds"
                            class="rounded border-gray-300"
                        >
                        {{ $exclusion->label }}
                    </label>
                @endforeach
            </div>
        @endif
    </div>

    <button
        type="button"
        wire:click="clearAll"
        class="text-xs text-gray-500 hover:text-indigo-600 underline"
    >
        Clear all filters
    </button>
</div>
