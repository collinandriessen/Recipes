<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeAllergenFlag extends Model
{
    use HasFactory;

    protected $fillable = ['recipe_id', 'allergen_id', 'confidence'];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function allergen(): BelongsTo
    {
        return $this->belongsTo(Allergen::class);
    }
}
