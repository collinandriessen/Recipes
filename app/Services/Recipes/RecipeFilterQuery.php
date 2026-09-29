<?php

namespace App\Services\Recipes;

use App\Models\Recipe;
use App\Models\UserExclusion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Architecture §4/§6: the combined macro + allergen filter, applied
 * together, is the feature the whole pitch depends on. This builder is the
 * single place that assembles that query so RecipeLibrary (and anything
 * else, e.g. the meal-plan picker) never hand-rolls it differently.
 *
 * Hard constraints (per issue SAA-18):
 *  - only ever touches cached columns — recipe_nutrition.* and
 *    recipe_allergen_flags — never a live FDC/API call and never N+1 (all
 *    exclusions are expressed as a single whereDoesntHave/whereHas per
 *    filter axis, not a per-recipe loop).
 *  - always scoped to one user's own recipes.
 */
class RecipeFilterQuery
{
    /** @var array<int, int> allergen_id list to exclude */
    private array $excludedAllergenIds = [];

    /** @var array<int, int> ingredient_id list to exclude (custom exclusions) */
    private array $excludedIngredientIds = [];

    private ?float $minCalories = null;

    private ?float $maxCalories = null;

    private ?float $minProtein = null;

    private ?float $maxProtein = null;

    private ?float $minCarbs = null;

    private ?float $maxCarbs = null;

    private ?float $minFat = null;

    private ?float $maxFat = null;

    private ?string $search = null;

    /** @var array<int, int> */
    private array $collectionIds = [];

    /** @var array<int, int> */
    private array $tagIds = [];

    public function __construct(
        private readonly int $userId,
    ) {}

    public static function forUser(int $userId): self
    {
        return new self($userId);
    }

    public function excludeAllergens(array $allergenIds): self
    {
        $this->excludedAllergenIds = array_values(array_unique(array_map('intval', $allergenIds)));

        return $this;
    }

    public function excludeIngredients(array $ingredientIds): self
    {
        $this->excludedIngredientIds = array_values(array_unique(array_map('intval', $ingredientIds)));

        return $this;
    }

    public function caloriesBetween(?float $min, ?float $max): self
    {
        $this->minCalories = $min;
        $this->maxCalories = $max;

        return $this;
    }

    public function proteinBetween(?float $min, ?float $max): self
    {
        $this->minProtein = $min;
        $this->maxProtein = $max;

        return $this;
    }

    public function carbsBetween(?float $min, ?float $max): self
    {
        $this->minCarbs = $min;
        $this->maxCarbs = $max;

        return $this;
    }

    public function fatBetween(?float $min, ?float $max): self
    {
        $this->minFat = $min;
        $this->maxFat = $max;

        return $this;
    }

    public function search(?string $term): self
    {
        $this->search = $term !== null && trim($term) !== '' ? trim($term) : null;

        return $this;
    }

    public function inCollections(array $collectionIds): self
    {
        $this->collectionIds = array_values(array_unique(array_map('intval', $collectionIds)));

        return $this;
    }

    public function withTags(array $tagIds): self
    {
        $this->tagIds = array_values(array_unique(array_map('intval', $tagIds)));

        return $this;
    }

    /**
     * @return Builder<Recipe>
     */
    public function build(): Builder
    {
        $query = Recipe::query()
            ->where('user_id', $this->userId)
            ->with(['nutrition', 'allergenFlags', 'tags', 'ingredients.ingredient']);

        if ($this->search !== null) {
            $query->where('title', 'like', '%'.$this->search.'%');
        }

        // Macro range: only join/constrain against recipe_nutrition when at
        // least one bound is set, so an unfiltered library still runs a
        // plain indexed lookup on recipes.user_id.
        $hasMacroFilter = $this->minCalories !== null || $this->maxCalories !== null
            || $this->minProtein !== null || $this->maxProtein !== null
            || $this->minCarbs !== null || $this->maxCarbs !== null
            || $this->minFat !== null || $this->maxFat !== null;

        if ($hasMacroFilter) {
            $query->whereHas('nutrition', function (Builder $nutrition) {
                $this->applyMacroBound($nutrition, 'calories_kcal', $this->minCalories, $this->maxCalories);
                $this->applyMacroBound($nutrition, 'protein_g', $this->minProtein, $this->maxProtein);
                $this->applyMacroBound($nutrition, 'carbs_g', $this->minCarbs, $this->maxCarbs);
                $this->applyMacroBound($nutrition, 'fat_g', $this->minFat, $this->maxFat);
            });
        }

        // Allergen exclusion: a recipe is excluded the moment ANY excluded
        // allergen is flagged against it (either confidence level — "may
        // contain" still counts, this is a safety filter, not a scoring
        // heuristic). Single whereDoesntHave against the indexed
        // recipe_allergen_flags pivot, no per-recipe follow-up query.
        if ($this->excludedAllergenIds !== []) {
            $query->whereDoesntHave('allergenFlags', function (Builder $allergens) {
                $allergens->whereIn('allergens.id', $this->excludedAllergenIds);
            });
        }

        // Custom ingredient exclusions (UserExclusion rows resolve to a set
        // of matched ingredient_ids before this is called): exclude any
        // recipe that has a recipe_ingredients row pointing at one of them.
        if ($this->excludedIngredientIds !== []) {
            $query->whereDoesntHave('ingredients', function (Builder $ingredients) {
                $ingredients->whereIn('ingredient_id', $this->excludedIngredientIds);
            });
        }

        if ($this->collectionIds !== []) {
            $query->whereHas('collections', function (Builder $collections) {
                $collections->whereIn('collections.id', $this->collectionIds);
            });
        }

        if ($this->tagIds !== []) {
            $query->whereHas('tags', function (Builder $tags) {
                $tags->whereIn('tags.id', $this->tagIds);
            });
        }

        return $query->latest('id');
    }

    private function applyMacroBound(Builder $query, string $column, ?float $min, ?float $max): void
    {
        if ($min !== null) {
            $query->where($column, '>=', $min);
        }

        if ($max !== null) {
            $query->where($column, '<=', $max);
        }
    }

    /**
     * Resolve a user's saved custom exclusions (by label) into the ingredient
     * ids they matched, for feeding into excludeIngredients(). Kept here so
     * the Livewire component doesn't need to know about UserExclusion's
     * storage shape.
     *
     * @param  Collection<int, UserExclusion>  $exclusions
     * @return array<int, int>
     */
    public static function resolveCustomExclusionIngredientIds(Collection $exclusions): array
    {
        return $exclusions
            ->flatMap(fn ($exclusion) => $exclusion->matched_ingredient_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
