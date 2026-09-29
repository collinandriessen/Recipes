@props(['recipe'])

@php
    /**
     * RecipeCard — architecture §4: plain Blade component, not Livewire.
     * Rendered once per card in a paginated grid (potentially dozens per
     * page), so it must stay a static render with zero per-card Livewire
     * wire overhead. All data ($recipe->nutrition, ->allergenFlags, ->tags)
     * is already eager-loaded by RecipeFilterQuery — no queries fire here.
     *
     * Allergen badges + the "verify ingredients yourself" disclaimer icon
     * are non-negotiable per SAA-21 (allergen-disclaimer-copy doc,
     * placement priority #1): this is the lowest-cost, highest-visibility
     * disclaimer placement, sitting right where the risky decision happens.
     */
    $nutrition = $recipe->nutrition;
    $allergens = $recipe->allergenFlags;
    $disclaimerText = "Allergen tags are generated automatically from ingredient data and are not medically verified. Always check the full ingredient list yourself before relying on this for an allergy.";
@endphp

<div
    class="group relative bg-white border border-gray-200 rounded-lg overflow-hidden hover:shadow-md transition-shadow"
    draggable="true"
    x-data
    x-on:dragstart="$el.dataset.recipeId = {{ $recipe->id }}; event.dataTransfer.setData('text/plain', {{ $recipe->id }})"
    data-recipe-id="{{ $recipe->id }}"
>
    <a href="{{ route('recipes.show', $recipe) }}" wire:navigate class="block">
        <div class="aspect-video bg-gray-100 flex items-center justify-center overflow-hidden">
            @if ($recipe->photo_path)
                <img src="{{ $recipe->photo_path }}" alt="" class="w-full h-full object-cover">
            @else
                <span class="text-gray-300 text-xs">No photo</span>
            @endif
        </div>

        <div class="p-4">
            <h3 class="font-medium text-gray-900 truncate" title="{{ $recipe->title }}">
                {{ $recipe->title }}
            </h3>

            <p class="text-xs text-gray-400 mt-0.5">
                {{ $recipe->servings }} {{ Str::plural('serving', $recipe->servings) }}
            </p>

            @if ($nutrition)
                <dl class="mt-3 grid grid-cols-4 gap-1 text-center text-[11px] text-gray-600">
                    <div>
                        <dt class="text-gray-400">kcal</dt>
                        <dd class="font-medium">{{ $nutrition->calories_kcal !== null ? round($nutrition->calories_kcal) : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-400">protein</dt>
                        <dd class="font-medium">{{ $nutrition->protein_g !== null ? round($nutrition->protein_g).'g' : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-400">carbs</dt>
                        <dd class="font-medium">{{ $nutrition->carbs_g !== null ? round($nutrition->carbs_g).'g' : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-400">fat</dt>
                        <dd class="font-medium">{{ $nutrition->fat_g !== null ? round($nutrition->fat_g).'g' : '—' }}</dd>
                    </div>
                </dl>
                @if ($nutrition->stale)
                    <p class="mt-1 text-[10px] text-gray-400">Estimate incomplete</p>
                @endif
            @endif

            @if ($allergens->isNotEmpty())
                <div class="mt-3 flex flex-wrap items-center gap-1">
                    @foreach ($allergens as $allergen)
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-red-50 text-red-700 text-[10px] font-medium">
                            {{ $allergen->name }}
                        </span>
                    @endforeach

                    {{-- Disclaimer icon — SAA-21 placement priority #1 (non-negotiable). --}}
                    <span
                        class="group/disclaimer relative inline-flex items-center justify-center w-4 h-4 rounded-full bg-gray-100 text-gray-500 text-[10px] cursor-help"
                        tabindex="0"
                        aria-label="Allergen tags are automated, not medically verified"
                    >
                        i
                        <span class="pointer-events-none absolute z-10 bottom-full mb-1.5 left-1/2 -translate-x-1/2 w-56 rounded bg-gray-900 text-white text-[10px] leading-snug px-2 py-1.5 opacity-0 group-hover/disclaimer:opacity-100 group-focus/disclaimer:opacity-100 transition-opacity">
                            {{ $disclaimerText }}
                        </span>
                    </span>
                </div>
            @endif

            @if ($recipe->tags->isNotEmpty())
                <div class="mt-2 flex flex-wrap gap-1">
                    @foreach ($recipe->tags as $tag)
                        <span class="inline-block px-1.5 py-0.5 rounded bg-gray-100 text-gray-500 text-[10px]">
                            {{ $tag->name }}
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
    </a>

    <div class="px-4 pb-3 -mt-1">
        <button
            type="button"
            wire:click.stop="generateFromRecipe({{ $recipe->id }})"
            class="w-full text-xs px-2 py-1.5 rounded border border-gray-200 text-gray-600 hover:border-indigo-300 hover:text-indigo-600"
        >
            + Add to shopping list
        </button>
    </div>
</div>
