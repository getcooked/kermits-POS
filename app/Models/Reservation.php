<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservation extends Model
{
    protected $fillable = [
        'user_id',
        'order_id',
        'reference',
        'type',
        'table_size',
        'customer_name',
        'email',
        'phone',
        'reservation_at',
        'dining_table_id',
        'reservation_end_at',
        'hold_expires_at',
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
            'reservation_end_at' => 'datetime',
            'hold_expires_at' => 'datetime',
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

    public function diningTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class);
    }

    public function getBookingStatusAttribute(): string
    {
        return $this->status === 'pending' && $this->hold_expires_at?->lte(now()) ? 'expired' : $this->status;
    }

    public function getTableLabelAttribute(): string
    {
        return $this->type === 'exclusive' ? 'Exclusive venue (all tables)'
            : ($this->diningTable ? 'Table '.$this->diningTable->number : 'Awaiting table assignment');
    }

    public function getTimeRangeAttribute(): string
    {
        return $this->reservation_at->format('h:i A')
            .($this->reservation_end_at ? ' – '.$this->reservation_end_at->format('h:i A') : '');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
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
