<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasUuids;

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'setting_key',
        'setting_value',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::query()
            ->where('setting_key', $key)
            ->value('setting_value') ?? $default;
    }
}
