<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IngredientNutrient extends Model
{
    use HasFactory;

    protected $fillable = [
        'ingredient_id', 'calories_kcal', 'protein_g', 'carbs_g', 'fat_g',
        'fiber_g', 'sodium_mg', 'source', 'fetched_at',
    ];

    protected $casts = [
        'fetched_at' => 'datetime',
    ];

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }
}
