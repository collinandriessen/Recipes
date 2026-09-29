<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2.3 (SAA-18): the combined macro+allergen filter must never fall
 * back to a live/N+1 lookup. `recipe_nutrition` already has a composite
 * macro index (migration 121737); this adds the remaining indexes the
 * RecipeFilterQuery builder relies on:
 *  - recipes(user_id, title) — library is always scoped to the owning user
 *    and text search filters on title within that scope.
 *  - recipe_ingredients(ingredient_id) — SQLite/Postgres don't auto-index
 *    FK columns the way MySQL's InnoDB does; the "exclude by custom
 *    ingredient" filter runs a whereDoesntHave against this column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->index(['user_id', 'title']);
        });

        Schema::table('recipe_ingredients', function (Blueprint $table) {
            $table->index('ingredient_id');
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'title']);
        });

        Schema::table('recipe_ingredients', function (Blueprint $table) {
            $table->dropIndex(['ingredient_id']);
        });
    }
};
