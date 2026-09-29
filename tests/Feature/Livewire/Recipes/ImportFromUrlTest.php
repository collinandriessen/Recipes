<?php

namespace Tests\Feature\Livewire\Recipes;

use App\Jobs\ImportRecipeJob;
use App\Livewire\Recipes\ImportFromUrl;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class ImportFromUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitting_a_url_creates_a_pending_recipe_and_dispatches_the_job(): void
    {
        $user = User::factory()->create();
        Queue::fake();

        Livewire::actingAs($user)
            ->test(ImportFromUrl::class)
            ->set('url', 'https://example.com/some-recipe')
            ->call('import')
            ->assertRedirect();

        $recipe = Recipe::query()->firstOrFail();

        $this->assertSame('pending', $recipe->import_status);
        $this->assertSame($user->id, $recipe->user_id);
        $this->assertSame('https://example.com/some-recipe', $recipe->source_url);

        Queue::assertPushed(ImportRecipeJob::class, fn ($job) => $job->recipeId === $recipe->id
            && $job->sourceUrl === 'https://example.com/some-recipe'
        );
    }

    public function test_invalid_url_is_rejected(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ImportFromUrl::class)
            ->set('url', 'not-a-url')
            ->call('import')
            ->assertHasErrors(['url']);

        $this->assertSame(0, Recipe::query()->count());
    }
}
