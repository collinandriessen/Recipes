<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_nutrition', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->decimal('calories_kcal', 8, 2)->nullable();
            $table->decimal('protein_g', 8, 2)->nullable();
            $table->decimal('carbs_g', 8, 2)->nullable();
            $table->decimal('fat_g', 8, 2)->nullable();
            $table->timestamp('computed_at')->nullable();
            $table->boolean('stale')->default(true);
            $table->timestamps();

            $table->unique('recipe_id');
            $table->index(['calories_kcal', 'protein_g', 'carbs_g', 'fat_g']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_nutrition');
    }
};
