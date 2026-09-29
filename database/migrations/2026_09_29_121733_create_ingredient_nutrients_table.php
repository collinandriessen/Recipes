<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredient_nutrients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->decimal('calories_kcal', 8, 2)->default(0);
            $table->decimal('protein_g', 8, 2)->default(0);
            $table->decimal('carbs_g', 8, 2)->default(0);
            $table->decimal('fat_g', 8, 2)->default(0);
            $table->decimal('fiber_g', 8, 2)->nullable();
            $table->decimal('sodium_mg', 8, 2)->nullable();
            $table->string('source')->default('usda_fdc'); // usda_fdc | edamam | manual_override
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            $table->unique('ingredient_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredient_nutrients');
    }
};
