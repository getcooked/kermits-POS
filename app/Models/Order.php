<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'customer_id',
        'processed_by',
        'total',
        'payment_method',
        'payment_status',
        'paid_at',
        'payment_reference',
        'paymongo_checkout_id',
        'paymongo_checkout_url',
        'cash_received',
        'change_due',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'cash_received' => 'decimal:2',
            'change_due' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order): void {
            if ($order->customer_id === null && $order->user_id) {
                $creator = User::query()->find($order->user_id);
                if ($creator?->hasRole(User::ROLE_CUSTOMER)) {
                    $order->customer_id = $creator->id;
                }
            }

            if ($order->payment_status === 'paid') {
                $order->paid_at ??= now();

                if ($order->processed_by === null && $order->user_id) {
                    $creator ??= User::query()->find($order->user_id);
                    if ($creator?->hasRole(User::ROLE_CASHIER, User::ROLE_SUPER_ADMIN)) {
                        $order->processed_by = $creator->id;
                    }
                }
            }
        });

        static::updating(function (Order $order): void {
            if ($order->isDirty('payment_status') && $order->payment_status === 'paid' && $order->paid_at === null) {
                $order->paid_at = now();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id')->withTrashed();
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by')->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function reservation(): HasOne
    {
        return $this->hasOne(Reservation::class);
    }

    public function totalDue(): float
    {
        return (float) $this->total + (float) ($this->reservation?->total_amount ?? 0);
    }
}
