<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetDocument extends Model
{
    protected $fillable = [
        'user_id',
        'file_name',
        'file_path',
        'total_amount',
        'items_count',
        'metadata',
        'generated_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'metadata' => 'array',
        'generated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
