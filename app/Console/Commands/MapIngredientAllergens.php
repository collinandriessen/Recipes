<?php

namespace App\Console\Commands;

use App\Models\Ingredient;
use App\Services\Allergens\AllergenMapper;
use Illuminate\Console\Command;

/**
 * Applies the keyword/category allergen ruleset to every ingredient in the
 * `ingredients` table, writing/refreshing `ingredient_allergens` rows.
 *
 * Used for:
 *  - initial backfill after seeding/importing ingredients outside the
 *    normal MatchIngredientsJob path (Phase 2.2);
 *  - re-running after a ruleset change (architecture §7's recommended QA
 *    review pass before beta will likely want this rerun after edits).
 *
 * Never touches manually-curated (`manual_curation`) mappings.
 */
class MapIngredientAllergens extends Command
{
    protected $signature = 'allergens:map {--ingredient= : Only map a single ingredient by id}';

    protected $description = 'Apply the keyword/category allergen mapping ruleset to ingredients.';

    public function handle(AllergenMapper $mapper): int
    {
        $query = Ingredient::query();

        if ($id = $this->option('ingredient')) {
            $query->where('id', $id);
        }

        $count = 0;
        $flagged = 0;

        $query->chunkById(200, function ($ingredients) use ($mapper, &$count, &$flagged) {
            foreach ($ingredients as $ingredient) {
                $matches = $mapper->mapIngredient($ingredient);
                $count++;
                if ($matches->isNotEmpty()) {
                    $flagged++;
                }
            }
        });

        $this->info("Mapped {$count} ingredient(s); {$flagged} matched at least one allergen rule.");

        return self::SUCCESS;
    }
}
