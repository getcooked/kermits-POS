<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'name',
        'category',
        'category_order',
        'description',
        'image_path',
        'price',
        'stock',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock' => 'integer',
            'category_order' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function reservationItems(): HasMany
    {
        return $this->hasMany(ReservationItem::class);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeMenuOrder(Builder $query): Builder
    {
        return $query->orderBy('category_order')->orderBy('category')->orderBy('name');
    }

    public function scopeLowStock(Builder $query, int $threshold = 5): Builder
    {
        return $query->where('stock', '<=', $threshold);
    }
}
