<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get(string $key): ?string
    {
        return static::query()->where('key', $key)->value('value');
    }
}
