<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Recipe extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'title', 'source_url', 'source_type', 'servings',
        'photo_path', 'instructions', 'import_status',
    ];

    protected $casts = [
        'instructions' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class)->orderBy('sort_order');
    }

    public function nutrition(): HasOne
    {
        return $this->hasOne(RecipeNutrition::class);
    }

    public function allergenFlags(): BelongsToMany
    {
        return $this->belongsToMany(Allergen::class, 'recipe_allergen_flags')
            ->withPivot('confidence');
    }

    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class, 'recipe_collection');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'recipe_tag');
    }
}
