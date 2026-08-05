<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservation extends Model
{
    protected $fillable = [
        'user_id',
        'reference',
        'type',
        'table_size',
        'customer_name',
        'email',
        'phone',
        'reservation_at',
        'guests',
        'reservation_fee',
        'food_total',
        'total_amount',
        'payment_method',
        'payment_reference',
        'payment_status',
        'payment_proof_path',
        'food_request',
        'notes',
        'status',
        'handled_by',
    ];

    protected function casts(): array
    {
        return [
            'reservation_at' => 'datetime',
            'guests' => 'integer',
            'table_size' => 'integer',
            'reservation_fee' => 'decimal:2',
            'food_total' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReservationItem::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ReservationStatusHistory::class)->oldest();
    }
}
