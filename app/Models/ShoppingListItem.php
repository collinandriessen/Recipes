<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShoppingListItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'ingredient_id', 'label', 'quantity', 'unit', 'checked', 'source_meal_plan_id',
    ];

    protected $casts = [
        'checked' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function sourceMealPlan(): BelongsTo
    {
        return $this->belongsTo(MealPlanEntry::class, 'source_meal_plan_id');
    }
}
