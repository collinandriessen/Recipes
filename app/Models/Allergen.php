<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Allergen extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'is_top9'];

    protected $casts = [
        'is_top9' => 'boolean',
    ];

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'ingredient_allergens')
            ->withPivot('confidence');
    }

    public function recipeFlags(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class, 'recipe_allergen_flags')
            ->withPivot('confidence');
    }
}
