<?php

namespace App\Livewire\ShoppingList;

use App\Models\MealPlanEntry;
use App\Models\Recipe;
use App\Models\ShoppingListItem;
use App\Services\ShoppingList\ShoppingListGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Architecture §4/§6: shopping list page. Generates shopping_list_items
 * either from the current week's meal plan (the common path, reachable
 * straight off the meal-plan calendar built in Phase 2.3) or from a set of
 * recipes picked directly, then lets the user check items off / clear
 * checked / remove individual rows while shopping.
 *
 * Not FeatureGate'd: the shopping list itself ships to every tier. Only the
 * combined macro/allergen filter is the paid hook (see FeatureGate).
 */
#[Layout('layouts.app')]
class ShoppingList extends Component
{
    /** Week start (YYYY-MM-DD) used for the "generate from this week's meal plan" action. */
    public string $weekStart;

    public function mount(): void
    {
        $this->weekStart = now()->startOfWeek()->toDateString();
    }

    public function generateFromMealPlan(): void
    {
        $from = Carbon::parse($this->weekStart);

        app(ShoppingListGenerator::class)->generateFromMealPlan(
            Auth::user(),
            $from,
            $from->copy()->addDays(6)
        );
    }

    public function generateFromRecipe(int $recipeId): void
    {
        $recipe = Recipe::query()->where('user_id', Auth::id())->findOrFail($recipeId);

        app(ShoppingListGenerator::class)->generateFromRecipes(Auth::user(), [$recipe->id]);
    }

    public function toggleChecked(int $itemId): void
    {
        $item = ShoppingListItem::query()->where('user_id', Auth::id())->findOrFail($itemId);
        $item->update(['checked' => ! $item->checked]);
    }

    public function removeItem(int $itemId): void
    {
        ShoppingListItem::query()->where('user_id', Auth::id())->whereKey($itemId)->delete();
    }

    public function clearChecked(): void
    {
        ShoppingListItem::query()->where('user_id', Auth::id())->where('checked', true)->delete();
    }

    public function render()
    {
        $userId = Auth::id();

        $items = ShoppingListItem::query()
            ->where('user_id', $userId)
            ->with('sourceMealPlan.recipe')
            ->orderBy('checked')
            ->orderBy('label')
            ->get();

        $weekStart = Carbon::parse($this->weekStart);

        $plannedRecipeCount = MealPlanEntry::query()
            ->where('user_id', $userId)
            ->whereBetween('planned_date', [$weekStart->toDateString(), $weekStart->copy()->addDays(6)->toDateString()])
            ->count();

        return view('livewire.shopping-list.index', [
            'items' => $items,
            'plannedRecipeCount' => $plannedRecipeCount,
        ]);
    }
}
