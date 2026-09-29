<?php

namespace App\Livewire\Recipes;

use App\Models\Collection as RecipeCollection;
use App\Models\MealPlanEntry;
use App\Models\Recipe;
use App\Models\Tag;
use App\Models\UserExclusion;
use App\Services\Billing\FeatureGate;
use App\Services\Recipes\RecipeFilterQuery;
use App\Services\ShoppingList\ShoppingListGenerator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Architecture §4: the recipe library. Owns the paginated grid; holds all
 * filter state in the URL query string via #[Url] so a filtered view is
 * shareable/bookmarkable (e.g. ?calorieMax=500&excludeAllergens=1,3). The
 * FilterPanel child owns the input widgets only — it never queries
 * anything itself, it just dispatches `filters-updated` which this
 * component listens for and turns into a fresh RecipeFilterQuery.
 *
 * Combined filter is the point of this issue: macro ranges and allergen
 * exclusions both apply to the SAME query, at the same time (see
 * RecipeFilterQuery::build) — not sequential/separate passes.
 */
#[Layout('layouts.app')]
class RecipeLibrary extends Component
{
    use WithPagination;

    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(as: 'cal_min', history: true)]
    public ?float $calorieMin = null;

    #[Url(as: 'cal_max', history: true)]
    public ?float $calorieMax = null;

    #[Url(as: 'pro_min', history: true)]
    public ?float $proteinMin = null;

    #[Url(as: 'pro_max', history: true)]
    public ?float $proteinMax = null;

    #[Url(as: 'carb_min', history: true)]
    public ?float $carbsMin = null;

    #[Url(as: 'carb_max', history: true)]
    public ?float $carbsMax = null;

    #[Url(as: 'fat_min', history: true)]
    public ?float $fatMin = null;

    #[Url(as: 'fat_max', history: true)]
    public ?float $fatMax = null;

    /** @var array<int, int> */
    #[Url(as: 'no_allergens', history: true)]
    public array $excludedAllergenIds = [];

    /** @var array<int, int> */
    #[Url(as: 'no_custom', history: true)]
    public array $excludedCustomExclusionIds = [];

    #[Url(as: 'tag', history: true)]
    public ?int $tagId = null;

    #[Url(as: 'collection', history: true)]
    public ?int $collectionId = null;

    public string $newTagName = '';

    public ?int $addTagToRecipeId = null;

    public bool $isPaidTier = false;

    /**
     * Architecture §6 / SAA-19: defense-in-depth gate check. FilterPanel
     * already no-ops gated values before dispatching filters-updated, but
     * RecipeLibrary also gates here since the macro/allergen properties are
     * #[Url]-bound — a free-tier user could otherwise load a paid filter
     * straight from a bookmarked/shared URL and have it silently apply.
     */
    public function mount(): void
    {
        $this->isPaidTier = FeatureGate::forUser(Auth::user())->canUseMacroAllergenFilters();

        $this->applyGate();
    }

    private function applyGate(): void
    {
        $gated = FeatureGate::forUser(Auth::user())->gateFilterInput([
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
        ]);

        foreach ($gated as $key => $value) {
            $this->{$key} = $value;
        }
    }

    /**
     * Consumes the FilterPanel child's debounced `filters-updated` event.
     * This is the ONLY write path into the filter properties above besides
     * direct URL load, so RecipeLibrary stays the single source of truth
     * (the child never queries or mutates library state on its own).
     */
    #[On('filters-updated')]
    public function applyFilters(array $filters): void
    {
        $filters = FeatureGate::forUser(Auth::user())->gateFilterInput($filters);

        $this->search = $filters['search'] ?? '';
        $this->calorieMin = $filters['calorieMin'] ?? null;
        $this->calorieMax = $filters['calorieMax'] ?? null;
        $this->proteinMin = $filters['proteinMin'] ?? null;
        $this->proteinMax = $filters['proteinMax'] ?? null;
        $this->carbsMin = $filters['carbsMin'] ?? null;
        $this->carbsMax = $filters['carbsMax'] ?? null;
        $this->fatMin = $filters['fatMin'] ?? null;
        $this->fatMax = $filters['fatMax'] ?? null;
        $this->excludedAllergenIds = array_map('intval', $filters['excludedAllergenIds'] ?? []);
        $this->excludedCustomExclusionIds = array_map('intval', $filters['excludedCustomExclusionIds'] ?? []);

        $this->resetPage();
    }

    public function filterByTag(?int $tagId): void
    {
        $this->tagId = $tagId;
        $this->resetPage();
    }

    public function filterByCollection(?int $collectionId): void
    {
        $this->collectionId = $collectionId;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset([
            'search', 'calorieMin', 'calorieMax', 'proteinMin', 'proteinMax',
            'carbsMin', 'carbsMax', 'fatMin', 'fatMax',
            'excludedAllergenIds', 'excludedCustomExclusionIds', 'tagId', 'collectionId',
        ]);
        $this->resetPage();
    }

    public function addToCollection(int $recipeId, int $collectionId): void
    {
        $recipe = Recipe::query()->where('user_id', Auth::id())->findOrFail($recipeId);
        $collection = RecipeCollection::query()->where('user_id', Auth::id())->findOrFail($collectionId);

        $recipe->collections()->syncWithoutDetaching([$collection->id]);
    }

    public function createCollection(string $name): void
    {
        if (trim($name) === '') {
            return;
        }

        RecipeCollection::query()->create([
            'user_id' => Auth::id(),
            'name' => trim($name),
        ]);

        $this->newTagName = '';
    }

    /**
     * Meal-planning calendar: drag a recipe card onto a day. Fired from the
     * card's native HTML5 drag events (see recipe-card.blade.php /
     * meal-plan-calendar partial) — the drop target carries the date and
     * meal slot, the drag source carries the recipe id, both passed here.
     */
    public function planRecipe(int $recipeId, string $plannedDate, string $mealSlot = 'dinner'): void
    {
        $recipe = Recipe::query()->where('user_id', Auth::id())->findOrFail($recipeId);

        MealPlanEntry::query()->create([
            'user_id' => Auth::id(),
            'recipe_id' => $recipe->id,
            'planned_date' => $plannedDate,
            'meal_slot' => $mealSlot,
        ]);
    }

    public function unplanRecipe(int $mealPlanEntryId): void
    {
        MealPlanEntry::query()
            ->where('user_id', Auth::id())
            ->whereKey($mealPlanEntryId)
            ->delete();
    }

    /**
     * "Add to shopping list" on a card in the grid — an ad-hoc, non-meal-plan
     * shortcut into the same ShoppingListGenerator the shopping-list page
     * uses for the weekly plan, so the aggregation rule stays identical
     * either way (SAA-19).
     */
    public function generateFromRecipe(int $recipeId): void
    {
        $recipe = Recipe::query()->where('user_id', Auth::id())->findOrFail($recipeId);

        app(ShoppingListGenerator::class)->generateFromRecipes(Auth::user(), [$recipe->id]);

        $this->dispatch('shopping-list-updated');
    }

    public function render()
    {
        $userId = Auth::id();

        $query = RecipeFilterQuery::forUser($userId)
            ->search($this->search)
            ->caloriesBetween($this->calorieMin, $this->calorieMax)
            ->proteinBetween($this->proteinMin, $this->proteinMax)
            ->carbsBetween($this->carbsMin, $this->carbsMax)
            ->fatBetween($this->fatMin, $this->fatMax)
            ->excludeAllergens($this->excludedAllergenIds);

        if ($this->excludedCustomExclusionIds !== []) {
            $ingredientIds = RecipeFilterQuery::resolveCustomExclusionIngredientIds(
                UserExclusion::query()
                    ->where('user_id', $userId)
                    ->whereIn('id', $this->excludedCustomExclusionIds)
                    ->get()
            );

            $query->excludeIngredients($ingredientIds);
        }

        if ($this->tagId !== null) {
            $query->withTags([$this->tagId]);
        }

        if ($this->collectionId !== null) {
            $query->inCollections([$this->collectionId]);
        }

        $recipes = $query->build()->paginate(12);

        $weekStart = now()->startOfWeek();
        $mealPlanDays = collect(range(0, 6))->map(fn ($i) => $weekStart->copy()->addDays($i));

        $mealPlanEntries = MealPlanEntry::query()
            ->where('user_id', $userId)
            ->whereBetween('planned_date', [$weekStart->toDateString(), $weekStart->copy()->addDays(6)->toDateString()])
            ->with('recipe')
            ->get()
            ->groupBy(fn (MealPlanEntry $entry) => $entry->planned_date->toDateString());

        return view('livewire.recipes.recipe-library', [
            'recipes' => $recipes,
            'tags' => Tag::query()->orderBy('name')->get(),
            'collections' => RecipeCollection::query()->where('user_id', $userId)->orderBy('name')->get(),
            'mealPlanDays' => $mealPlanDays,
            'mealPlanEntries' => $mealPlanEntries,
            'initialFilterState' => [
                'search' => $this->search,
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
            ],
        ]);
    }
}
