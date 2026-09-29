<div class="bg-white border border-gray-200 rounded-lg p-6 max-w-xl mx-auto">
    <h1 class="text-lg font-semibold mb-1">Import a recipe from a URL</h1>
    <p class="text-sm text-gray-500 mb-4">
        We'll look for structured recipe data on the page. If we find it, you'll review it before
        saving. If not, you'll fill it in manually — fast, and just as first-class.
    </p>

    <form wire:submit="import" class="space-y-4">
        <div>
            <label for="url" class="block text-sm font-medium text-gray-700">Recipe URL</label>
            <input
                wire:model="url"
                id="url"
                type="url"
                placeholder="https://example.com/my-favorite-recipe"
                autofocus
                class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            >
            @error('url') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <button
            type="submit"
            wire:loading.attr="disabled"
            class="w-full py-2 rounded bg-indigo-600 text-white font-medium hover:bg-indigo-700 disabled:opacity-50"
        >
            <span wire:loading.remove>Import recipe</span>
            <span wire:loading>Fetching…</span>
        </button>
    </form>

    <p class="mt-4 text-center text-sm text-gray-500">
        Prefer to type it in yourself?
        <a href="{{ route('recipes.manual-entry') }}" wire:navigate class="text-indigo-600 hover:underline">
            Enter a recipe manually
        </a>
    </p>
</div>
