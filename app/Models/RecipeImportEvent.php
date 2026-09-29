<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeImportEvent extends Model
{
    use HasFactory;

    public const OUTCOME_JSON_LD_SUCCESS = 'json_ld_success';

    public const OUTCOME_JSON_LD_ABSENT = 'json_ld_absent';

    public const OUTCOME_JSON_LD_INVALID = 'json_ld_invalid';

    public const OUTCOME_FETCH_FAILED = 'fetch_failed';

    protected $fillable = [
        'recipe_id', 'user_id', 'source_url', 'domain', 'outcome',
        'failure_reason', 'ingredient_lines_found', 'duration_ms',
    ];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
