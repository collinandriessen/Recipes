<?php

namespace Tests\Feature\Livewire\Recipes;

use App\Livewire\Recipes\RecipeLibrary;
use App\Models\Allergen;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\RecipeNutrition;
use App\Models\User;
use App\Models\UserExclusion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * SAA-18: the combined macro + allergen filter is the point of this whole
 * feature — tests exercise both axes applied TOGETHER, plus the URL
 * query-string persistence, ownership scoping, and the (mandatory,
 * indexed-only) query shape.
 */
class RecipeLibraryTest extends TestCase
{
    use RefreshDatabase;

    private function recipeWithNutritionAndAllergen(
        User $user,
        string $title,
        float $calories,
        float $protein,
        ?Allergen $allergen = null,
    ): Recipe {
        $recipe = Recipe::factory()->for($user)->create([
            'title' => $title,
            'import_status' => 'confirmed',
        ]);

        RecipeNutrition::query()->create([
            'recipe_id' => $recipe->id,
            'calories_kcal' => $calories,
            'protein_g' => $protein,
            'carbs_g' => 20,
            'fat_g' => 10,
            'stale' => false,
        ]);

        if ($allergen !== null) {
            $recipe->allergenFlags()->attach($allergen->id, ['confidence' => 'confirmed']);
        }

        return $recipe;
    }

    public function test_combined_macro_and_allergen_filters_apply_together(): void
    {
        $user = User::factory()->create();
        $peanuts = Allergen::factory()->create(['name' => 'Peanuts', 'is_top9' => true]);

        // Matches both filters: low-cal AND no peanuts.
        $wanted = $this->recipeWithNutritionAndAllergen($user, 'Grilled Chicken Salad', 350, 40);

        // Fails macro filter only (too high calories), no allergen.
        $this->recipeWithNutritionAndAllergen($user, 'Loaded Nachos', 1200, 30);

        // Fails allergen filter only (in range calories, but has peanuts).
        $this->recipeWithNutritionAndAllergen($user, 'Peanut Noodles', 400, 25, $peanuts);

        // Fails both.
        $this->recipeWithNutritionAndAllergen($user, 'Peanut Butter Cake', 1500, 20, $peanuts);

        Livewire::actingAs($user)
            ->test(RecipeLibrary::class)
            ->call('applyFilters', [
                'calorieMin' => null,
                'calorieMax' => 600,
                'excludedAllergenIds' => [$peanuts->id],
            ])
            ->assertSee('Grilled Chicken Salad')
            ->assertDontSee('Loaded Nachos')
            ->assertDontSee('Peanut Noodles')
            ->assertDontSee('Peanut Butter Cake');
    }

    public function test_filter_state_is_reflected_in_the_url_query_string(): void
    {
        $user = User::factory()->create();
        $peanuts = Allergen::factory()->create(['name' => 'Peanuts', 'is_top9' => true]);

        $component = Livewire::actingAs($user)
            ->test(RecipeLibrary::class)
            ->call('applyFilters', [
                'calorieMax' => 500,
                'search' => 'chicken',
                'excludedAllergenIds' => [$peanuts->id],
            ]);

        $this->assertSame(500.0, $component->get('calorieMax'));
        $this->assertSame('chicken', $component->get('search'));
        $this->assertSame([$peanuts->id], $component->get('excludedAllergenIds'));
    }

    public function test_library_only_shows_the_authenticated_users_own_recipes(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Recipe::factory()->for($user)->create(['title' => 'My Soup', 'import_status' => 'confirmed']);
        Recipe::factory()->for($otherUser)->create(['title' => 'Their Stew', 'import_status' => 'confirmed']);

        Livewire::actingAs($user)
            ->test(RecipeLibrary::class)
            ->assertSee('My Soup')
            ->assertDontSee('Their Stew');
    }

    public function test_custom_ingredient_exclusion_removes_matching_recipes(): void
    {
        $user = User::factory()->create();
        $shrimp = Ingredient::factory()->create(['name' => 'Shrimp']);

        $recipeWithShrimp = Recipe::factory()->for($user)->create([
            'title' => 'Shrimp Scampi',
            'import_status' => 'confirmed',
        ]);
        RecipeIngredient::query()->create([
            'recipe_id' => $recipeWithShrimp->id,
            'ingredient_id' => $shrimp->id,
            'raw_text' => '1 lb shrimp',
            'sort_order' => 0,
            'match_status' => 'auto',
        ]);

        Recipe::factory()->for($user)->create(['title' => 'Veggie Stir Fry', 'import_status' => 'confirmed']);

        $exclusion = UserExclusion::query()->create([
            'user_id' => $user->id,
            'label' => 'Shellfish (custom)',
            'matched_ingredient_ids' => [$shrimp->id],
        ]);

        Livewire::actingAs($user)
            ->test(RecipeLibrary::class)
            ->call('applyFilters', ['excludedCustomExclusionIds' => [$exclusion->id]])
            ->assertSee('Veggie Stir Fry')
            ->assertDontSee('Shrimp Scampi');
    }

    public function test_search_and_pagination_reset_when_filters_change(): void
    {
        $user = User::factory()->create();
        Recipe::factory()->count(15)->for($user)->create(['import_status' => 'confirmed']);

        $component = Livewire::actingAs($user)->test(RecipeLibrary::class);

        $component->call('nextPage');
        $this->assertSame(2, $component->instance()->getPage());

        $component->call('applyFilters', ['search' => 'anything']);
        $this->assertSame(1, $component->instance()->getPage());
    }
}
