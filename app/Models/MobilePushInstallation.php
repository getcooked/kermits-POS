<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobilePushInstallation extends Model
{
    protected $fillable = [
        'user_id',
        'mobile_api_token_id',
        'provider',
        'identifier_kind',
        'identifier',
        'identifier_hash',
        'platform',
        'app_version',
        'last_seen_at',
    ];

    protected $hidden = [
        'identifier',
        'identifier_hash',
    ];

    protected function casts(): array
    {
        return [
            'identifier' => 'encrypted',
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mobileApiToken(): BelongsTo
    {
        return $this->belongsTo(MobileApiToken::class);
    }
}
