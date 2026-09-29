<div class="bg-white border border-gray-200 rounded-lg p-6 max-w-2xl mx-auto">
    <h1 class="text-lg font-semibold mb-1">
        {{ $recipeId ? 'Finish this recipe' : 'Enter a recipe manually' }}
    </h1>
    <p class="text-sm text-gray-500 mb-6">
        Type each ingredient on its own line — start typing a name and pick a match if one shows up,
        or just keep typing your own text. Quantity and unit are parsed automatically.
    </p>

    <form wire:submit="save" class="space-y-6">
        <div>
            <label for="title" class="block text-sm font-medium text-gray-700">Title</label>
            <input
                wire:model="title"
                id="title"
                type="text"
                class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            >
            @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="w-32">
            <label for="servings" class="block text-sm font-medium text-gray-700">Servings</label>
            <input
                wire:model="servings"
                id="servings"
                type="number"
                min="1"
                class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            >
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Ingredients</label>

            <div class="space-y-2">
                @foreach ($ingredientLines as $index => $line)
                    <div class="flex items-center gap-2 relative">
                        <input
                            wire:model="ingredientLines.{{ $index }}.text"
                            wire:keyup.debounce.200ms="updateAutocomplete($event.target.value)"
                            type="text"
                            placeholder="e.g. 2 cups flour"
                            class="flex-1 rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        >
                        <button
                            type="button"
                            wire:click="removeIngredientLine({{ $index }})"
                            class="text-gray-400 hover:text-red-600 text-sm px-2"
                            aria-label="Remove line"
                        >
                            &times;
                        </button>
                    </div>
                @endforeach
            </div>

            @if (! empty($autocompleteSuggestions))
                <div class="mt-1 text-xs text-gray-500">
                    Matching known ingredients: {{ implode(', ', $autocompleteSuggestions) }}
                </div>
            @endif

            <button
                type="button"
                wire:click="addIngredientLine"
                class="mt-2 text-sm text-indigo-600 hover:underline"
            >
                + Add ingredient line
            </button>
        </div>

        <div>
            <label for="instructionsText" class="block text-sm font-medium text-gray-700">
                Instructions (one step per line)
            </label>
            <textarea
                wire:model="instructionsText"
                id="instructionsText"
                rows="6"
                class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
            ></textarea>
        </div>

        <button
            type="submit"
            wire:loading.attr="disabled"
            class="w-full py-2 rounded bg-indigo-600 text-white font-medium hover:bg-indigo-700 disabled:opacity-50"
        >
            Save recipe
        </button>
    </form>
</div>
