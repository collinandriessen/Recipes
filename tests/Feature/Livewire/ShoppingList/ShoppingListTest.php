<?php

namespace Tests\Feature\Livewire\ShoppingList;

use App\Livewire\ShoppingList\ShoppingList;
use App\Models\Ingredient;
use App\Models\MealPlanEntry;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\ShoppingListItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShoppingListTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_from_meal_plan_creates_items_for_the_current_week(): void
    {
        $user = User::factory()->create();
        $recipe = Recipe::factory()->for($user)->create(['import_status' => 'confirmed']);
        $ingredient = Ingredient::factory()->create(['name' => 'Rice']);

        RecipeIngredient::query()->create([
            'recipe_id' => $recipe->id,
            'ingredient_id' => $ingredient->id,
            'raw_text' => '2 cups rice',
            'quantity' => 2,
            'unit' => 'cup',
            'sort_order' => 0,
            'match_status' => 'auto',
        ]);

        MealPlanEntry::query()->create([
            'user_id' => $user->id,
            'recipe_id' => $recipe->id,
            'planned_date' => now()->toDateString(),
            'meal_slot' => 'dinner',
        ]);

        $this->actingAs($user);

        Livewire::test(ShoppingList::class)
            ->call('generateFromMealPlan')
            ->assertSet('weekStart', now()->startOfWeek()->toDateString());

        $this->assertDatabaseHas('shopping_list_items', [
            'user_id' => $user->id,
            'label' => 'Rice',
        ]);
    }

    public function test_toggle_checked_flips_the_checked_flag_for_the_owning_user_only(): void
    {
        $user = User::factory()->create();
        $item = ShoppingListItem::query()->create([
            'user_id' => $user->id,
            'label' => 'Milk',
            'checked' => false,
        ]);

        $this->actingAs($user);

        Livewire::test(ShoppingList::class)->call('toggleChecked', $item->id);

        $this->assertTrue($item->fresh()->checked);
    }

    public function test_toggle_checked_cannot_touch_another_users_item(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $item = ShoppingListItem::query()->create([
            'user_id' => $owner->id,
            'label' => 'Milk',
            'checked' => false,
        ]);

        $this->actingAs($intruder);

        Livewire::test(ShoppingList::class)->call('toggleChecked', $item->id)->assertStatus(404);

        $this->assertFalse($item->fresh()->checked);
    }

    public function test_remove_item_deletes_it(): void
    {
        $user = User::factory()->create();
        $item = ShoppingListItem::query()->create(['user_id' => $user->id, 'label' => 'Milk']);

        $this->actingAs($user);

        Livewire::test(ShoppingList::class)->call('removeItem', $item->id);

        $this->assertDatabaseMissing('shopping_list_items', ['id' => $item->id]);
    }

    public function test_clear_checked_only_removes_checked_items(): void
    {
        $user = User::factory()->create();
        $checked = ShoppingListItem::query()->create(['user_id' => $user->id, 'label' => 'Milk', 'checked' => true]);
        $unchecked = ShoppingListItem::query()->create(['user_id' => $user->id, 'label' => 'Eggs', 'checked' => false]);

        $this->actingAs($user);

        Livewire::test(ShoppingList::class)->call('clearChecked');

        $this->assertDatabaseMissing('shopping_list_items', ['id' => $checked->id]);
        $this->assertDatabaseHas('shopping_list_items', ['id' => $unchecked->id]);
    }

    public function test_generate_from_recipe_is_scoped_to_the_owning_user(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $recipe = Recipe::factory()->for($owner)->create(['import_status' => 'confirmed']);

        $this->actingAs($intruder);

        Livewire::test(ShoppingList::class)
            ->call('generateFromRecipe', $recipe->id)
            ->assertStatus(404);
    }
}
