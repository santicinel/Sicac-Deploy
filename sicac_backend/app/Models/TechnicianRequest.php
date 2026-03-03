<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TechnicianRequest extends Model
{
    public const TYPE_TECHNICAL_SERVICE = 'technical_service';
    public const TYPE_CLAIM = 'claim';

    public const STATUS_PENDING = 'pending';
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'requesting_user_id',
        'technician_id',
        'category_id',
        'claim_id',
        'repaired_product_id',
        'type',
        'status',
        'subject',
        'description',
        'wanted_date_start',
        'wanted_date_end',
        'time_shift',
        'scheduled_visit_date',
        'scheduled_visit_time',
        'resolution_summary',
        'cancellation_reason',
        'charged_amount',
        'completed_at',
    ];

    use Concerns\UseCachedRelations;

    protected $cachedRelationships = [
        'timeShift' => [
            'class' => TimeShift::class,
            'foreign_key' => 'time_shift',
        ],
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function requestingUser()
    {
        return $this->belongsTo(User::class, 'requesting_user_id');
    }

    public function technician()
    {
        return $this->belongsTo(Technician::class);
    }

    public function claim()
    {
        return $this->belongsTo(Claim::class);
    }

    public function repairedProduct()
    {
        return $this->belongsTo(Product::class, 'repaired_product_id');
    }

    public function rating()
    {
        return $this->hasOne(Rating::class, 'technician_request_id');
    }

    public function clientRatings()
    {
        return $this->hasMany(ClientRating::class);
    }

    public function isUser(User $user): bool
    {
        return (int) $this->requesting_user_id === (int) $user->id;
    }

    public function isTechnician(Technician $technician): bool
    {
        return $this->technician_id !== null
            && (int) $this->technician_id === (int) $technician->id;
    }

    public function hasRating(): bool
    {
        return $this->rating()->exists();
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_ASSIGNED,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
        ];
    }

    public static function types(): array
    {
        return [
            self::TYPE_TECHNICAL_SERVICE,
            self::TYPE_CLAIM,
        ];
    }

    public static function storeRules(): array
    {
        return [
            'type' => 'sometimes|in:' . implode(',', self::types()),
            'technician_id' => 'nullable|exists:technicians,id',
            'category_id' => 'nullable|exists:categories,id',
            'claim_id' => 'nullable|exists:claims,id',
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'wanted_date_start' => 'required|date',
            'wanted_date_end' => 'required|date|after_or_equal:wanted_date_start',
            'time_shift' => 'required|string',
        ];
    }

    public static function statusUpdateRules(): array
    {
        return [
            'status' => 'required|in:' . implode(',', self::statuses()),
            'scheduled_visit_date' => 'nullable|date',
            'scheduled_visit_time' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'resolution_summary' => 'nullable|string|max:4000',
            'cancellation_reason' => 'nullable|string|max:4000',
            'charged_amount' => 'nullable|numeric|min:0',
            'repaired_product_id' => 'nullable|exists:products,id',
        ];
    }

    public static function updateRules(): array
    {
        return [
            'subject' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'category_id' => 'nullable|exists:categories,id',
            'claim_id' => 'nullable|exists:claims,id',
            'type' => 'sometimes|in:' . implode(',', self::types()),
            'status' => 'sometimes|in:' . implode(',', self::statuses()),
            'technician_id' => 'nullable|exists:technicians,id',
            'wanted_date_start' => 'nullable|date',
            'wanted_date_end' => 'nullable|date|after_or_equal:wanted_date_start',
            'time_shift' => 'nullable|string',
            'scheduled_visit_date' => 'nullable|date',
            'scheduled_visit_time' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'resolution_summary' => 'nullable|string|max:4000',
            'cancellation_reason' => 'nullable|string|max:4000',
            'charged_amount' => 'nullable|numeric|min:0',
            'repaired_product_id' => 'nullable|exists:products,id',
            'completed_at' => 'nullable|date',
        ];
    }
}
