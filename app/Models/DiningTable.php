<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiningTable extends Model
{
    protected $fillable = ['number', 'seats', 'active'];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'seats' => 'integer',
            'active' => 'boolean',
            'occupied_at' => 'datetime',
            'expected_free_at' => 'datetime',
            'freed_at' => 'datetime',
        ];
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * The reservation seated here, or null for a walk-in or a free table.
     */
    public function occupiedReservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'occupied_reservation_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function isOccupied(): bool
    {
        return $this->occupied_at !== null;
    }

    public function label(): string
    {
        return 'Table '.$this->number;
    }
}
