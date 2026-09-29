<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeNutrition extends Model
{
    use HasFactory;

    protected $table = 'recipe_nutrition';

    protected $fillable = [
        'recipe_id', 'calories_kcal', 'protein_g', 'carbs_g', 'fat_g', 'computed_at', 'stale',
    ];

    protected $casts = [
        'computed_at' => 'datetime',
        'stale' => 'boolean',
    ];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }
}
