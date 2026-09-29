<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_plan_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->date('planned_date');
            $table->string('meal_slot')->default('dinner'); // breakfast | lunch | dinner | snack
            $table->timestamps();

            $table->index(['user_id', 'planned_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_plan_entries');
    }
};
