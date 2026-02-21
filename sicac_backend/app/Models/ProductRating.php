<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductRating extends Model
{
    protected $fillable = [
        'technician_request_id',
        'product_id',
        'user_id',
        'score',
        'description',
    ];

    public function technicianRequest(): BelongsTo
    {
        return $this->belongsTo(TechnicianRequest::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
