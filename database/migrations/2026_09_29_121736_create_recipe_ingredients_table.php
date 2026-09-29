<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->nullable()->constrained()->nullOnDelete();
            $table->string('raw_text');
            $table->decimal('quantity', 8, 3)->nullable();
            $table->string('unit')->nullable();
            $table->string('preparation_note')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->decimal('match_confidence', 5, 4)->nullable();
            $table->string('match_status')->default('unmatched'); // auto | manual | unmatched
            $table->timestamps();

            $table->index(['recipe_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_ingredients');
    }
};
