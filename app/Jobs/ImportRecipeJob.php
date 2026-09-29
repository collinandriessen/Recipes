<?php

namespace App\Jobs;

use App\Models\Recipe;
use App\Models\RecipeImportEvent;
use App\Models\User;
use App\Services\Import\RecipeFetchException;
use App\Services\Import\RecipeImportResult;
use App\Services\Import\RecipeUrlImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Architecture §5: first stage of the import pipeline. Fetches the source
 * URL, tries schema.org Recipe JSON-LD (RecipeUrlImporter). On success,
 * creates the Recipe + RecipeIngredient rows with import_status =
 * needs_review (never auto-trusted — user must confirm/edit, especially for
 * allergen-relevant ingredient data). On JSON-LD absence/failure, creates a
 * Recipe with import_status = manual, pre-filled from <title>/OG image only,
 * with zero ingredient rows (manual entry UI owns filling those in).
 *
 * Records one RecipeImportEvent per attempt (per-domain success/failure
 * instrumentation, architecture Rev 2 addition to §3) regardless of outcome.
 *
 * Chains MatchIngredientsJob on JSON-LD success (needs_review recipes still
 * get ingredients auto-matched so the review screen shows suggested matches,
 * not blank dropdowns — user confirms/edits from there).
 */
class ImportRecipeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public readonly int $userId,
        public readonly string $sourceUrl,
        /**
         * Id of a placeholder Recipe row (import_status = pending) the
         * caller already created so it has something to redirect/link to
         * immediately. This job fills that row in rather than creating a
         * second one. Nullable so the job also works when dispatched
         * without a UI round-trip (tests, console, future API).
         */
        public readonly ?int $recipeId = null,
    ) {}

    public function handle(RecipeUrlImporter $importer): void
    {
        $startedAt = microtime(true);
        $domain = parse_url($this->sourceUrl, PHP_URL_HOST) ?: 'unknown';

        try {
            $result = $importer->import($this->sourceUrl);
        } catch (RecipeFetchException $e) {
            if ($this->recipeId !== null) {
                Recipe::query()->whereKey($this->recipeId)->update(['import_status' => 'fetch_failed']);
            }

            $this->recordEvent(
                recipeId: $this->recipeId,
                domain: $domain,
                outcome: RecipeImportEvent::OUTCOME_FETCH_FAILED,
                failureReason: $e->getMessage(),
                ingredientLines: 0,
                startedAt: $startedAt,
            );

            Log::warning('recipe_import.fetch_failed', ['url' => $this->sourceUrl, 'error' => $e->getMessage()]);

            throw $e; // let the queue's retry/backoff policy handle transient fetch failures.
        }

        $recipe = $result->jsonLdFound
            ? $this->createFromJsonLd($result)
            : $this->createManualStub($result);

        $this->recordEvent(
            recipeId: $recipe->id,
            domain: $domain,
            outcome: $result->jsonLdFound
                ? RecipeImportEvent::OUTCOME_JSON_LD_SUCCESS
                : ($result->failureReason === 'json_ld_present_but_unparseable'
                    ? RecipeImportEvent::OUTCOME_JSON_LD_INVALID
                    : RecipeImportEvent::OUTCOME_JSON_LD_ABSENT),
            failureReason: $result->failureReason,
            ingredientLines: count($result->ingredientLines),
            startedAt: $startedAt,
        );

        if ($result->jsonLdFound) {
            MatchIngredientsJob::dispatch($recipe->id)->onQueue('imports');
        }
    }

    private function createFromJsonLd(RecipeImportResult $result): Recipe
    {
        return DB::transaction(function () use ($result) {
            $attributes = [
                'user_id' => $this->userId,
                'title' => $result->title,
                'source_url' => $this->sourceUrl,
                'source_type' => 'url_import',
                'servings' => $result->servings ?? 1,
                'photo_path' => $result->imageUrl,
                'instructions' => $result->instructions,
                'import_status' => 'needs_review',
            ];

            $recipe = $this->recipeId !== null
                ? tap(Recipe::query()->findOrFail($this->recipeId))->update($attributes)
                : Recipe::query()->create($attributes);

            foreach ($result->ingredientLines as $index => $rawLine) {
                $recipe->ingredients()->create([
                    'raw_text' => $rawLine,
                    'sort_order' => $index,
                    'match_status' => 'unmatched',
                ]);
            }

            return $recipe;
        });
    }

    private function createManualStub(RecipeImportResult $result): Recipe
    {
        $attributes = [
            'user_id' => $this->userId,
            'title' => $result->title ?? 'Untitled recipe',
            'source_url' => $this->sourceUrl,
            'source_type' => 'url_import',
            'servings' => 1,
            'photo_path' => $result->imageUrl,
            'import_status' => 'manual',
        ];

        return $this->recipeId !== null
            ? tap(Recipe::query()->findOrFail($this->recipeId))->update($attributes)
            : Recipe::query()->create($attributes);
    }

    private function recordEvent(
        ?int $recipeId,
        string $domain,
        string $outcome,
        ?string $failureReason,
        int $ingredientLines,
        float $startedAt,
    ): void {
        RecipeImportEvent::query()->create([
            'recipe_id' => $recipeId,
            'user_id' => $this->userId,
            'source_url' => $this->sourceUrl,
            'domain' => $domain,
            'outcome' => $outcome,
            'failure_reason' => $failureReason,
            'ingredient_lines_found' => $ingredientLines,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        $domain = parse_url($this->sourceUrl, PHP_URL_HOST) ?: 'unknown';

        if ($this->recipeId !== null) {
            Recipe::query()->whereKey($this->recipeId)->update(['import_status' => 'fetch_failed']);
        }

        RecipeImportEvent::query()->create([
            'recipe_id' => $this->recipeId,
            'user_id' => $this->userId,
            'source_url' => $this->sourceUrl,
            'domain' => $domain,
            'outcome' => RecipeImportEvent::OUTCOME_FETCH_FAILED,
            'failure_reason' => 'exhausted_retries: '.$e->getMessage(),
            'ingredient_lines_found' => 0,
        ]);
    }
}
