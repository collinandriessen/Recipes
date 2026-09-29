<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('external_source')->nullable(); // usda_fdc | edamam | manual
            $table->string('external_food_id')->nullable()->index();
            $table->string('default_unit')->nullable();
            $table->decimal('density_g_per_ml', 8, 4)->nullable();
            $table->timestamps();

            $table->unique(['name', 'external_source', 'external_food_id'], 'ingredients_name_source_food_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredients');
    }
};
