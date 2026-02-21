<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientRating extends Model
{
    protected $fillable = [
        'technician_request_id',
        'client_user_id',
        'technician_id',
        'score',
        'description',
    ];

    public function technicianRequest(): BelongsTo
    {
        return $this->belongsTo(TechnicianRequest::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }
}
