<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopping_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label')->nullable();
            $table->decimal('quantity', 8, 3)->nullable();
            $table->string('unit')->nullable();
            $table->boolean('checked')->default(false);
            $table->foreignId('source_meal_plan_id')->nullable()->constrained('meal_plan_entries')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'checked']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopping_list_items');
    }
};
