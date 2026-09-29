<?php

namespace Tests\Feature\Livewire\Recipes;

use App\Jobs\RecomputeOnEditJob;
use App\Livewire\Recipes\ShowRecipe;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class ShowRecipeTest extends TestCase
{
    use RefreshDatabase;

    public function test_other_users_cannot_view_a_recipe(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $recipe = Recipe::factory()->for($owner)->create();

        Livewire::actingAs($other)
            ->test(ShowRecipe::class, ['recipe' => $recipe])
            ->assertForbidden();
    }

    public function test_confirming_needs_review_updates_status_and_dispatches_recompute(): void
    {
        $user = User::factory()->create();
        Queue::fake();

        $recipe = Recipe::factory()->for($user)->create(['import_status' => 'needs_review']);
        $line = $recipe->ingredients()->create(['raw_text' => '2 cups flour', 'sort_order' => 0]);

        Livewire::actingAs($user)
            ->test(ShowRecipe::class, ['recipe' => $recipe])
            ->set("editedIngredientText.{$line->id}", '2 cups all-purpose flour')
            ->call('confirmImport');

        $recipe->refresh();
        $line->refresh();

        $this->assertSame('confirmed', $recipe->import_status);
        $this->assertSame('2 cups all-purpose flour', $line->raw_text);

        Queue::assertPushed(RecomputeOnEditJob::class, fn ($job) => $job->recipeId === $recipe->id);
    }
}
