<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'actor_name',
        'actor_role',
        'action',
        'description',
        'route_name',
        'method',
        'path',
        'status_code',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }
}
