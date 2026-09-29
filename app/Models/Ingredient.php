<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Ingredient extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'external_source', 'external_food_id', 'default_unit', 'density_g_per_ml',
    ];

    public function nutrients(): HasOne
    {
        return $this->hasOne(IngredientNutrient::class);
    }

    public function allergens(): BelongsToMany
    {
        return $this->belongsToMany(Allergen::class, 'ingredient_allergens')
            ->withPivot('confidence', 'mapping_source');
    }

    public function recipeIngredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class);
    }
}
