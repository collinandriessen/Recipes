<div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">Shopping list</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                Generated from your planned meals and any recipes you've added directly.
            </p>
        </div>
    </div>

    {{-- Disclaimer copy: SAA-21 allergen-disclaimer-copy doc. This list is built from
         ingredient text/quantities only — it carries no allergen information of its
         own, but since it's assembled from recipes whose allergen tags are automated
         (not vendor-curated), a shopper relying on "what's in my cart" still needs the
         same steer toward checking real labels before eating anything. Kept short and
         placed once at the top rather than per-line, since this page is a checklist,
         not a recipe decision point (that disclaimer already lives on RecipeCard /
         recipe detail per Phase 2.3). --}}
    <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded px-3 py-2">
        This list is built from your recipes' ingredient text, not from label data —
        quantities and items can be missing or off. Allergen tags on RecipeFit are
        automated, not medically verified, so always check ingredient labels yourself
        while shopping if you or someone you're cooking for has a food allergy.
    </p>

    <div class="bg-white border border-gray-200 rounded-lg p-5 flex flex-wrap items-center gap-3">
        <div class="text-sm text-gray-600">
            <span class="font-medium">Week of {{ \Illuminate\Support\Carbon::parse($weekStart)->format('M j') }}</span>
            — {{ $plannedRecipeCount }} {{ Str::plural('meal', $plannedRecipeCount) }} planned
        </div>
        <button
            type="button"
            wire:click="generateFromMealPlan"
            class="ml-auto px-3 py-1.5 rounded bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700"
        >
            Generate from this week's meal plan
        </button>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-gray-700">Items ({{ $items->count() }})</h2>

            @if ($items->where('checked', true)->isNotEmpty())
                <button
                    type="button"
                    wire:click="clearChecked"
                    class="text-xs text-gray-500 hover:text-indigo-600 underline"
                >
                    Clear checked
                </button>
            @endif
        </div>

        @if ($items->isEmpty())
            <p class="text-sm text-gray-400">
                Nothing here yet. Plan some meals on the
                <a href="{{ route('recipes.library') }}" wire:navigate class="text-indigo-600 hover:underline">recipe library</a>
                calendar, then generate a list above.
            </p>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach ($items as $item)
                    <li class="flex items-center gap-3 py-2.5">
                        <input
                            type="checkbox"
                            wire:click="toggleChecked({{ $item->id }})"
                            @checked($item->checked)
                            class="rounded border-gray-300"
                        >
                        <div class="flex-1 min-w-0 {{ $item->checked ? 'line-through text-gray-400' : 'text-gray-800' }}">
                            <span class="text-sm">
                                @if ($item->quantity !== null)
                                    {{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }}
                                    {{ $item->unit }}
                                @endif
                                {{ $item->label }}
                            </span>
                            @if ($item->sourceMealPlan?->recipe)
                                <span class="block text-[11px] text-gray-400 truncate">
                                    from {{ $item->sourceMealPlan->recipe->title }}
                                </span>
                            @endif
                        </div>
                        <button
                            type="button"
                            wire:click="removeItem({{ $item->id }})"
                            class="text-gray-300 hover:text-red-500 text-sm"
                            aria-label="Remove item"
                        >
                            &times;
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
