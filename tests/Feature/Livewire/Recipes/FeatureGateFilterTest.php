<?php

namespace Tests\Feature\Livewire\Recipes;

use App\Livewire\Recipes\FilterPanel;
use App\Livewire\Recipes\RecipeLibrary;
use App\Models\Allergen;
use App\Models\Recipe;
use App\Models\RecipeNutrition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * SAA-19: FeatureGate integration with the macro/allergen filter surface.
 * Free-tier users must never have a macro/allergen value take effect —
 * checked at both the FilterPanel input layer and the RecipeLibrary query
 * layer (defense in depth against a bookmarked/shared filtered URL).
 */
class FeatureGateFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_tier_filter_panel_reports_not_paid_and_clears_gated_initial_values(): void
    {
        $user = User::factory()->create(['subscription_tier' => 'free']);
        $this->actingAs($user);

        Livewire::test(FilterPanel::class, ['initial' => ['calorieMin' => 100, 'excludedAllergenIds' => [1]]])
            ->assertSet('isPaidTier', false)
            ->assertSet('calorieMin', null)
            ->assertSet('excludedAllergenIds', []);
    }

    public function test_paid_tier_filter_panel_keeps_initial_values(): void
    {
        $user = User::factory()->create(['subscription_tier' => 'paid']);
        $this->actingAs($user);

        Livewire::test(FilterPanel::class, ['initial' => ['calorieMin' => 100, 'excludedAllergenIds' => [1]]])
            ->assertSet('isPaidTier', true)
            ->assertSet('calorieMin', 100)
            ->assertSet('excludedAllergenIds', [1]);
    }

    public function test_free_tier_updated_snaps_a_gated_property_back_to_cleared(): void
    {
        $user = User::factory()->create(['subscription_tier' => 'free']);
        $this->actingAs($user);

        Livewire::test(FilterPanel::class)
            ->set('calorieMin', 250)
            ->assertSet('calorieMin', null);
    }

    public function test_free_tier_recipe_library_ignores_gated_query_params(): void
    {
        $user = User::factory()->create(['subscription_tier' => 'free']);
        $recipe = Recipe::factory()->for($user)->create(['title' => 'High cal', 'import_status' => 'confirmed']);
        RecipeNutrition::query()->create([
            'recipe_id' => $recipe->id, 'calories_kcal' => 900, 'protein_g' => 10, 'carbs_g' => 10, 'fat_g' => 10, 'stale' => false,
        ]);

        $this->actingAs($user);

        // Free user hits the library with a shared/bookmarked ?cal_max=500 URL —
        // the gate must ignore it, so the high-calorie recipe still shows.
        Livewire::withQueryParams(['cal_max' => 500])
            ->test(RecipeLibrary::class)
            ->assertSet('calorieMax', null)
            ->assertSee('High cal');
    }

    public function test_paid_tier_recipe_library_applies_gated_query_params(): void
    {
        $user = User::factory()->create(['subscription_tier' => 'paid']);
        $recipe = Recipe::factory()->for($user)->create(['title' => 'High cal', 'import_status' => 'confirmed']);
        RecipeNutrition::query()->create([
            'recipe_id' => $recipe->id, 'calories_kcal' => 900, 'protein_g' => 10, 'carbs_g' => 10, 'fat_g' => 10, 'stale' => false,
        ]);

        $this->actingAs($user);

        Livewire::withQueryParams(['cal_max' => 500])
            ->test(RecipeLibrary::class)
            ->assertSet('calorieMax', 500.0)
            ->assertDontSee('High cal');
    }

    public function test_free_tier_filters_updated_event_is_gated_at_the_parent_too(): void
    {
        $user = User::factory()->create(['subscription_tier' => 'free']);
        $allergen = Allergen::factory()->create(['is_top9' => true]);
        $this->actingAs($user);

        Livewire::test(RecipeLibrary::class)
            ->dispatch('filters-updated', filters: ['excludedAllergenIds' => [$allergen->id]])
            ->assertSet('excludedAllergenIds', []);
    }
}
