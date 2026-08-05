<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'customer_id',
        'total',
        'payment_method',
        'payment_status',
        'payment_reference',
        'cash_received',
        'change_due',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'cash_received' => 'decimal:2',
            'change_due' => 'decimal:2',
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

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
