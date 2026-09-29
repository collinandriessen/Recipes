<div class="space-y-6" x-data>
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900">Recipe Library</h1>
        <div class="flex gap-3 text-sm">
            <a href="{{ route('recipes.manual-entry') }}" wire:navigate class="text-indigo-600 hover:underline">
                + Add manually
            </a>
            <a href="{{ route('recipes.import') }}" wire:navigate class="text-indigo-600 hover:underline">
                + Import from URL
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[280px_1fr] gap-6">
        <aside class="space-y-4">
            @livewire('recipes.filter-panel', ['initial' => $initialFilterState], key('filter-panel'))

            @if ($tags->isNotEmpty())
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">Tags</h3>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            wire:click="filterByTag(null)"
                            @class([
                                'px-2 py-0.5 rounded text-xs',
                                'bg-indigo-600 text-white' => $tagId === null,
                                'bg-gray-100 text-gray-600 hover:bg-gray-200' => $tagId !== null,
                            ])
                        >All</button>
                        @foreach ($tags as $tag)
                            <button
                                wire:click="filterByTag({{ $tag->id }})"
                                @class([
                                    'px-2 py-0.5 rounded text-xs',
                                    'bg-indigo-600 text-white' => $tagId === $tag->id,
                                    'bg-gray-100 text-gray-600 hover:bg-gray-200' => $tagId !== $tag->id,
                                ])
                            >{{ $tag->name }}</button>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-2">Collections</h3>
                <div class="flex flex-wrap gap-1.5 mb-3">
                    <button
                        wire:click="filterByCollection(null)"
                        @class([
                            'px-2 py-0.5 rounded text-xs',
                            'bg-indigo-600 text-white' => $collectionId === null,
                            'bg-gray-100 text-gray-600 hover:bg-gray-200' => $collectionId !== null,
                        ])
                    >All</button>
                    @foreach ($collections as $collection)
                        <button
                            wire:click="filterByCollection({{ $collection->id }})"
                            @class([
                                'px-2 py-0.5 rounded text-xs',
                                'bg-indigo-600 text-white' => $collectionId === $collection->id,
                                'bg-gray-100 text-gray-600 hover:bg-gray-200' => $collectionId !== $collection->id,
                            ])
                        >{{ $collection->name }}</button>
                    @endforeach
                </div>

                <form wire:submit.prevent="createCollection(newTagName)" class="flex gap-1">
                    <input
                        type="text"
                        wire:model="newTagName"
                        placeholder="New collection…"
                        class="flex-1 rounded border-gray-300 text-xs shadow-sm"
                    >
                    <button type="submit" class="px-2 py-1 rounded bg-gray-100 text-gray-600 text-xs hover:bg-gray-200">
                        Add
                    </button>
                </form>
            </div>
        </aside>

        <div class="space-y-4">
            @if ($search !== '' || $calorieMin || $calorieMax || $proteinMin || $proteinMax || $carbsMin || $carbsMax || $fatMin || $fatMax || $excludedAllergenIds || $excludedCustomExclusionIds || $tagId || $collectionId)
                <div class="flex items-center justify-between text-xs text-gray-500 bg-indigo-50 border border-indigo-100 rounded px-3 py-2">
                    <span>
                        Showing {{ $recipes->total() }} {{ Str::plural('recipe', $recipes->total()) }} matching your filters.
                        This view is bookmarkable — the URL updates as you filter.
                    </span>
                    <button wire:click="clearFilters" class="text-indigo-600 hover:underline ml-3 shrink-0">Clear</button>
                </div>
            @endif

            @if ($recipes->isEmpty())
                <div class="bg-white border border-gray-200 rounded-lg p-10 text-center text-sm text-gray-400">
                    No recipes match these filters yet.
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                    @foreach ($recipes as $recipe)
                        <x-recipe-card :recipe="$recipe" />
                    @endforeach
                </div>

                <div>
                    {{ $recipes->links() }}
                </div>
            @endif
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg p-5">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">This week's meal plan</h2>
        <p class="text-xs text-gray-400 mb-3">Drag a recipe card above onto a day to plan it.</p>

        <div class="grid grid-cols-7 gap-2">
            @foreach ($mealPlanDays as $day)
                @php $dateKey = $day->toDateString(); @endphp
                <div
                    class="border border-dashed border-gray-300 rounded p-2 min-h-[100px] text-xs"
                    x-on:dragover.prevent
                    x-on:drop.prevent="
                        const recipeId = event.dataTransfer.getData('text/plain');
                        if (recipeId) { $wire.planRecipe(parseInt(recipeId), '{{ $dateKey }}'); }
                    "
                >
                    <div class="font-medium text-gray-600 mb-1">{{ $day->format('D j') }}</div>

                    @foreach ($mealPlanEntries->get($dateKey, collect()) as $entry)
                        <div class="flex items-center justify-between bg-gray-50 rounded px-1.5 py-1 mb-1 gap-1">
                            <span class="truncate">{{ $entry->recipe->title }}</span>
                            <button
                                wire:click="unplanRecipe({{ $entry->id }})"
                                class="text-gray-400 hover:text-red-500 shrink-0"
                                aria-label="Remove from plan"
                            >&times;</button>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>
</div>
