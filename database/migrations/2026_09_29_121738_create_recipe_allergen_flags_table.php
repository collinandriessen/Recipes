<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_allergen_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('allergen_id')->constrained()->cascadeOnDelete();
            $table->string('confidence')->default('confirmed'); // confirmed | may_contain
            $table->timestamps();

            $table->unique(['recipe_id', 'allergen_id']);
            $table->index(['recipe_id', 'allergen_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_allergen_flags');
    }
};
