<?php

namespace Tests\Feature\Livewire\Recipes;

use App\Jobs\RecomputeOnEditJob;
use App\Livewire\Recipes\ManualEntry;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class ManualEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_new_recipe_from_scratch(): void
    {
        $user = User::factory()->create();
        Queue::fake();

        Livewire::actingAs($user)
            ->test(ManualEntry::class)
            ->set('title', 'Weeknight Chili')
            ->set('servings', 4)
            ->set('ingredientLines', [
                ['text' => '1 lb ground beef', 'sort_order' => 0],
                ['text' => '1 can black beans', 'sort_order' => 1],
                ['text' => '', 'sort_order' => 2], // blank line should be dropped
            ])
            ->set('instructionsText', "Brown the beef.\nAdd beans and simmer.")
            ->call('save')
            ->assertRedirect();

        $recipe = Recipe::query()->firstOrFail();

        $this->assertSame('Weeknight Chili', $recipe->title);
        $this->assertSame('manual', $recipe->source_type);
        $this->assertSame('confirmed', $recipe->import_status);
        $this->assertCount(2, $recipe->ingredients);
        $this->assertCount(2, $recipe->instructions);

        Queue::assertPushed(RecomputeOnEditJob::class, fn ($job) => $job->recipeId === $recipe->id);
    }

    public function test_finishes_a_manual_stub_created_by_url_import_fallback(): void
    {
        $user = User::factory()->create();
        Queue::fake();

        $stub = Recipe::factory()->for($user)->create([
            'title' => "Someone's Blog Recipe",
            'source_type' => 'url_import',
            'source_url' => 'https://example.com/blog',
            'import_status' => 'manual',
        ]);

        Livewire::actingAs($user)
            ->test(ManualEntry::class, ['recipe' => $stub])
            ->set('ingredientLines', [['text' => '2 eggs', 'sort_order' => 0]])
            ->call('save')
            ->assertRedirect();

        $stub->refresh();

        $this->assertSame('url_import', $stub->source_type); // provenance preserved, not clobbered to 'manual'.
        $this->assertSame('confirmed', $stub->import_status);
        $this->assertCount(1, $stub->ingredients);
    }

    public function test_autocomplete_suggests_local_ingredients_without_fdc_call(): void
    {
        $user = User::factory()->create();
        Ingredient::query()->create(['name' => 'chicken thigh']);

        Livewire::actingAs($user)
            ->test(ManualEntry::class)
            ->call('updateAutocomplete', 'chick')
            ->assertSet('autocompleteSuggestions', ['chicken thigh']);
    }
}
