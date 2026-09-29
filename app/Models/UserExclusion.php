<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserExclusion extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'label', 'matched_ingredient_ids'];

    protected $casts = [
        'matched_ingredient_ids' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
