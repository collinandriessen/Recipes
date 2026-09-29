<?php

namespace App\Services\ShoppingList;

use App\Models\MealPlanEntry;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\ShoppingListItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Architecture §4/§6: generates shopping_list_items from planned meals
 * (MealPlanEntry, Phase 2.3's meal-plan calendar) or from an ad-hoc set of
 * selected recipes. Not gated by FeatureGate — the macro/allergen filter is
 * the paid hook per the roadmap, the shopping list itself is a free-tier
 * feature (architecture §6 lists it under core, not premium).
 *
 * Aggregation rule: within a single generation call, ingredient lines that
 * resolve to the same matched Ingredient (or, for unmatched lines, the same
 * lowercased raw ingredient text) AND the same unit are summed into one
 * shopping_list_item rather than creating a duplicate row per recipe — a
 * user planning chicken breast in three recipes this week wants one line
 * with a combined quantity, not three. Lines with a different unit for the
 * same ingredient are kept as separate rows (no unit conversion here; that's
 * out of scope for Phase 2.4 and better handled once real nutrition/unit
 * conversion tables exist).
 *
 * Each generated item keeps a `source_meal_plan_id` pointing at the FIRST
 * meal-plan entry that contributed to it, for traceability back to "why is
 * this on my list" — it is not meant to be the only contributor once
 * aggregated.
 */
class ShoppingListGenerator
{
    /**
     * Generate shopping list items from everything planned for a user
     * within an inclusive date range (typically "this week").
     *
     * @return Collection<int, ShoppingListItem>
     */
    public function generateFromMealPlan(User $user, Carbon|string $from, Carbon|string $to): Collection
    {
        $entries = MealPlanEntry::query()
            ->where('user_id', $user->id)
            ->whereBetween('planned_date', [
                Carbon::parse($from)->toDateString(),
                Carbon::parse($to)->toDateString(),
            ])
            ->with('recipe.ingredients.ingredient')
            ->get();

        $lines = $entries->flatMap(
            fn (MealPlanEntry $entry) => $entry->recipe->ingredients->map(
                fn ($line) => ['line' => $line, 'sourceMealPlanId' => $entry->id]
            )
        );

        return $this->persist($user, $this->aggregate($lines));
    }

    /**
     * Generate shopping list items from an ad-hoc set of recipes the user
     * picked directly (e.g. "add to shopping list" from the recipe library
     * grid), with no meal-plan entry involved.
     *
     * @param  array<int, int>  $recipeIds
     * @return Collection<int, ShoppingListItem>
     */
    public function generateFromRecipes(User $user, array $recipeIds): Collection
    {
        $recipes = Recipe::query()
            ->where('user_id', $user->id)
            ->whereIn('id', $recipeIds)
            ->with('ingredients.ingredient')
            ->get();

        $lines = $recipes->flatMap(
            fn (Recipe $recipe) => $recipe->ingredients->map(
                fn ($line) => ['line' => $line, 'sourceMealPlanId' => null]
            )
        );

        return $this->persist($user, $this->aggregate($lines));
    }

    /**
     * @param  Collection<int, array{line: RecipeIngredient, sourceMealPlanId: ?int}>  $lines
     * @return array<string, array{ingredient_id: ?int, label: string, quantity: ?float, unit: ?string, source_meal_plan_id: ?int}>
     */
    private function aggregate(Collection $lines): array
    {
        $aggregated = [];

        foreach ($lines as $entry) {
            $line = $entry['line'];
            $unit = $line->unit;
            $ingredient = $line->ingredient;
            $label = $ingredient?->name ?? trim($line->raw_text);

            $key = $ingredient
                ? "ingredient:{$ingredient->id}:".mb_strtolower((string) $unit)
                : 'label:'.mb_strtolower($label).':'.mb_strtolower((string) $unit);

            if (! isset($aggregated[$key])) {
                $aggregated[$key] = [
                    'ingredient_id' => $ingredient?->id,
                    'label' => $label,
                    'quantity' => null,
                    'unit' => $unit,
                    'source_meal_plan_id' => $entry['sourceMealPlanId'],
                ];
            }

            if ($line->quantity !== null) {
                $aggregated[$key]['quantity'] = ($aggregated[$key]['quantity'] ?? 0) + (float) $line->quantity;
            }
        }

        return $aggregated;
    }

    /**
     * @param  array<string, array{ingredient_id: ?int, label: string, quantity: ?float, unit: ?string, source_meal_plan_id: ?int}>  $aggregated
     * @return Collection<int, ShoppingListItem>
     */
    private function persist(User $user, array $aggregated): Collection
    {
        return collect($aggregated)->map(
            fn (array $item) => ShoppingListItem::query()->create([
                'user_id' => $user->id,
                'ingredient_id' => $item['ingredient_id'],
                'label' => $item['label'],
                'quantity' => $item['quantity'],
                'unit' => $item['unit'],
                'checked' => false,
                'source_meal_plan_id' => $item['source_meal_plan_id'],
            ])
        )->values();
    }
}
