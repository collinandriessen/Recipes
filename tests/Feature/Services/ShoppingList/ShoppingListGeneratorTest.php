<?php

namespace Tests\Feature\Services\ShoppingList;

use App\Models\Ingredient;
use App\Models\MealPlanEntry;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\ShoppingListItem;
use App\Models\User;
use App\Services\ShoppingList\ShoppingListGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SAA-19: shopping list generation from planned meals / selected recipes.
 * Covers: meal-plan aggregation, same-ingredient+unit summing across
 * multiple recipes, unmatched-ingredient fallback to raw text, per-user
 * scoping, and the direct "add this recipe" ad-hoc path.
 */
class ShoppingListGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private ShoppingListGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = app(ShoppingListGenerator::class);
    }

    private function recipeWithIngredient(User $user, string $title, ?Ingredient $ingredient, string $rawText, ?float $qty, ?string $unit): Recipe
    {
        $recipe = Recipe::factory()->for($user)->create(['title' => $title, 'import_status' => 'confirmed']);

        RecipeIngredient::query()->create([
            'recipe_id' => $recipe->id,
            'ingredient_id' => $ingredient?->id,
            'raw_text' => $rawText,
            'quantity' => $qty,
            'unit' => $unit,
            'sort_order' => 0,
            'match_status' => $ingredient ? 'auto' : 'unmatched',
        ]);

        return $recipe;
    }

    public function test_generates_items_from_meal_plan_within_date_range(): void
    {
        $user = User::factory()->create();
        $chicken = Ingredient::factory()->create(['name' => 'Chicken breast']);
        $recipe = $this->recipeWithIngredient($user, 'Grilled chicken', $chicken, '1 lb chicken breast', 1, 'lb');

        MealPlanEntry::query()->create([
            'user_id' => $user->id,
            'recipe_id' => $recipe->id,
            'planned_date' => now()->toDateString(),
            'meal_slot' => 'dinner',
        ]);

        // Outside the range — must not be included.
        $otherRecipe = $this->recipeWithIngredient($user, 'Salad', $chicken, '1 lb chicken breast', 1, 'lb');
        MealPlanEntry::query()->create([
            'user_id' => $user->id,
            'recipe_id' => $otherRecipe->id,
            'planned_date' => now()->addMonth()->toDateString(),
            'meal_slot' => 'lunch',
        ]);

        $items = $this->generator->generateFromMealPlan($user, now()->startOfWeek(), now()->endOfWeek());

        $this->assertCount(1, $items);
        $this->assertSame('Chicken breast', $items->first()->label);
        $this->assertEquals(1.0, (float) $items->first()->quantity);
    }

    public function test_aggregates_same_ingredient_and_unit_across_multiple_recipes(): void
    {
        $user = User::factory()->create();
        $chicken = Ingredient::factory()->create(['name' => 'Chicken breast']);

        $recipeA = $this->recipeWithIngredient($user, 'Recipe A', $chicken, '1 lb chicken breast', 1, 'lb');
        $recipeB = $this->recipeWithIngredient($user, 'Recipe B', $chicken, '2 lb chicken breast', 2, 'lb');

        MealPlanEntry::query()->create(['user_id' => $user->id, 'recipe_id' => $recipeA->id, 'planned_date' => now()->toDateString(), 'meal_slot' => 'dinner']);
        MealPlanEntry::query()->create(['user_id' => $user->id, 'recipe_id' => $recipeB->id, 'planned_date' => now()->toDateString(), 'meal_slot' => 'lunch']);

        $items = $this->generator->generateFromMealPlan($user, now()->startOfWeek(), now()->endOfWeek());

        $this->assertCount(1, $items);
        $this->assertEquals(3.0, (float) $items->first()->quantity);
    }

    public function test_different_units_for_same_ingredient_stay_separate_rows(): void
    {
        $user = User::factory()->create();
        $flour = Ingredient::factory()->create(['name' => 'Flour']);

        $recipeA = $this->recipeWithIngredient($user, 'Bread', $flour, '500 g flour', 500, 'g');
        $recipeB = $this->recipeWithIngredient($user, 'Cake', $flour, '2 cups flour', 2, 'cup');

        MealPlanEntry::query()->create(['user_id' => $user->id, 'recipe_id' => $recipeA->id, 'planned_date' => now()->toDateString(), 'meal_slot' => 'dinner']);
        MealPlanEntry::query()->create(['user_id' => $user->id, 'recipe_id' => $recipeB->id, 'planned_date' => now()->toDateString(), 'meal_slot' => 'lunch']);

        $items = $this->generator->generateFromMealPlan($user, now()->startOfWeek(), now()->endOfWeek());

        $this->assertCount(2, $items);
    }

    public function test_unmatched_ingredient_falls_back_to_raw_text_label(): void
    {
        $user = User::factory()->create();
        $recipe = $this->recipeWithIngredient($user, 'Mystery dish', null, 'a pinch of magic', null, null);

        MealPlanEntry::query()->create(['user_id' => $user->id, 'recipe_id' => $recipe->id, 'planned_date' => now()->toDateString(), 'meal_slot' => 'dinner']);

        $items = $this->generator->generateFromMealPlan($user, now()->startOfWeek(), now()->endOfWeek());

        $this->assertCount(1, $items);
        $this->assertSame('a pinch of magic', $items->first()->label);
        $this->assertNull($items->first()->ingredient_id);
    }

    public function test_generate_from_recipes_does_not_require_a_meal_plan_entry(): void
    {
        $user = User::factory()->create();
        $eggs = Ingredient::factory()->create(['name' => 'Eggs']);
        $recipe = $this->recipeWithIngredient($user, 'Omelette', $eggs, '3 eggs', 3, null);

        $items = $this->generator->generateFromRecipes($user, [$recipe->id]);

        $this->assertCount(1, $items);
        $this->assertNull($items->first()->source_meal_plan_id);
        $this->assertDatabaseHas('shopping_list_items', [
            'user_id' => $user->id,
            'label' => 'Eggs',
        ]);
    }

    public function test_generate_from_recipes_only_touches_the_owning_users_recipes(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $recipe = $this->recipeWithIngredient($owner, 'Not yours', Ingredient::factory()->create(), '1 cup rice', 1, 'cup');

        $items = $this->generator->generateFromRecipes($other, [$recipe->id]);

        $this->assertCount(0, $items);
        $this->assertSame(0, ShoppingListItem::query()->where('user_id', $other->id)->count());
    }
}
