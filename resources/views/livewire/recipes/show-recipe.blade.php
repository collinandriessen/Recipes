<div class="max-w-2xl mx-auto space-y-6" @if($recipe->import_status === 'pending') wire:poll.2s @endif>
    <div class="bg-white border border-gray-200 rounded-lg p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold">{{ $recipe->title }}</h1>
                @if ($recipe->source_url)
                    <a href="{{ $recipe->source_url }}" target="_blank" class="text-xs text-gray-400 hover:underline break-all">
                        {{ $recipe->source_url }}
                    </a>
                @endif
            </div>

            <span class="shrink-0 text-xs font-medium px-2 py-1 rounded
                @class([
                    'bg-yellow-100 text-yellow-700' => $recipe->import_status === 'pending',
                    'bg-blue-100 text-blue-700' => $recipe->import_status === 'needs_review',
                    'bg-gray-100 text-gray-600' => $recipe->import_status === 'manual',
                    'bg-green-100 text-green-700' => $recipe->import_status === 'confirmed',
                    'bg-red-100 text-red-700' => $recipe->import_status === 'fetch_failed',
                ])
            ">
                {{ str($recipe->import_status)->replace('_', ' ')->title() }}
            </span>
        </div>

        @if ($recipe->photo_path)
            <img src="{{ $recipe->photo_path }}" alt="" class="mt-4 rounded max-h-64 object-cover">
        @endif
    </div>

    @if ($recipe->import_status === 'pending')
        <div class="bg-white border border-gray-200 rounded-lg p-6 text-sm text-gray-500">
            Fetching and parsing this recipe in the background… this page will update automatically.
        </div>
    @elseif ($recipe->import_status === 'fetch_failed')
        <div class="bg-white border border-gray-200 rounded-lg p-6 text-sm">
            <p class="text-red-600 mb-3">We couldn't fetch that URL after a few retries.</p>
            <a href="{{ route('recipes.manual-entry') }}" wire:navigate class="text-indigo-600 hover:underline">
                Enter this recipe manually instead
            </a>
        </div>
    @elseif ($recipe->import_status === 'manual')
        <div class="bg-white border border-gray-200 rounded-lg p-6 text-sm">
            <p class="text-gray-600 mb-3">
                We couldn't find structured recipe data on that page, so we've only pulled the title
                and photo. Fill in the rest below — it's quick.
            </p>
            <a
                href="{{ route('recipes.manual-entry.edit', $recipe) }}"
                wire:navigate
                class="inline-block px-4 py-2 rounded bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700"
            >
                Finish this recipe
            </a>
        </div>
    @elseif ($recipe->import_status === 'needs_review')
        <div class="bg-white border border-gray-200 rounded-lg p-6">
            <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded px-3 py-2 mb-4">
                We parsed this from the source page automatically — please confirm the ingredients
                below (or fix anything that looks wrong) before we compute nutrition and allergen info.
            </p>

            <h2 class="text-sm font-medium text-gray-700 mb-2">Ingredients (source text on the left)</h2>
            <div class="space-y-2">
                @foreach ($recipe->ingredients as $line)
                    <div class="grid grid-cols-2 gap-3 items-center text-sm border-b border-gray-100 pb-2">
                        <input
                            wire:model="editedIngredientText.{{ $line->id }}"
                            type="text"
                            class="rounded border-gray-300 shadow-sm text-sm"
                        >
                        <div class="text-gray-500">
                            @if ($line->ingredient)
                                matched: <span class="font-medium text-gray-700">{{ $line->ingredient->name }}</span>
                                @if ($line->match_status !== 'auto')
                                    <span class="text-amber-600">(low confidence — please confirm)</span>
                                @endif
                                @if ($line->ingredient->allergens->isNotEmpty())
                                    <div class="mt-0.5 text-xs">
                                        allergens:
                                        @foreach ($line->ingredient->allergens as $allergen)
                                            <span class="inline-block px-1.5 py-0.5 rounded bg-red-50 text-red-700 mr-1">
                                                {{ $allergen->name }} ({{ $allergen->pivot->confidence }})
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            @else
                                <span class="text-gray-400">no match yet</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <button
                wire:click="confirmImport"
                class="mt-4 px-4 py-2 rounded bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700"
            >
                Looks good — confirm
            </button>
        </div>
    @endif

    @if ($recipe->import_status === 'confirmed')
        <div class="bg-white border border-gray-200 rounded-lg p-6">
            @if ($recipe->allergenFlags->isNotEmpty())
                {{-- SAA-21 placement priority #3: recipe detail banner, the last
                     checkpoint before a user acts on the recipe. --}}
                <div class="text-sm bg-amber-50 border border-amber-200 rounded px-3 py-2 mb-6">
                    <p class="font-medium text-amber-800 mb-1">Allergen tags are automated, not a guarantee.</p>
                    <p class="text-amber-700">
                        RecipeFit flags likely allergens by matching ingredient names and categories against
                        a keyword list we maintain — it is not a certified or medically reviewed allergen check,
                        and it can miss allergens hidden in a branded product, a substitution, or an ambiguous
                        ingredient name. If you or someone you're cooking for has a food allergy or intolerance,
                        always read the full ingredient list below yourself, and check packaging/labels for
                        anything store-bought, before relying on this recipe.
                    </p>
                </div>
            @endif

            <h2 class="text-sm font-medium text-gray-700 mb-3">Ingredients</h2>
            <ul class="text-sm text-gray-600 space-y-1 mb-6">
                @foreach ($recipe->ingredients as $line)
                    <li>{{ $line->raw_text }}</li>
                @endforeach
            </ul>

            @if ($recipe->nutrition)
                <h2 class="text-sm font-medium text-gray-700 mb-2">Nutrition (per serving)</h2>
                <div class="grid grid-cols-4 gap-3 text-sm text-gray-600 mb-6">
                    <div>{{ $recipe->nutrition->calories_kcal }} kcal</div>
                    <div>{{ $recipe->nutrition->protein_g }}g protein</div>
                    <div>{{ $recipe->nutrition->carbs_g }}g carbs</div>
                    <div>{{ $recipe->nutrition->fat_g }}g fat</div>
                </div>
                @if ($recipe->nutrition->stale)
                    <p class="text-xs text-gray-400 mb-6">Estimate incomplete — some ingredients are unmatched.</p>
                @endif
            @endif

            @if ($recipe->allergenFlags->isNotEmpty())
                <h2 class="text-sm font-medium text-gray-700 mb-2">Allergens</h2>
                <div class="flex flex-wrap gap-2">
                    @foreach ($recipe->allergenFlags as $allergen)
                        <span class="inline-block px-2 py-1 rounded bg-red-50 text-red-700 text-xs">
                            {{ $allergen->name }} ({{ $allergen->pivot->confidence }})
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</div>
