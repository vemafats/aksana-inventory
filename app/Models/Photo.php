<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Photo extends Model
{
    use HasUuids;

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'related_type',
        'related_id',
        'photo_path',
        'photo_timestamp',
        'latitude',
        'longitude',
        'watermark_text',
        'taken_by',
    ];

    protected function casts(): array
    {
        return [
            'photo_timestamp' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function takenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'taken_by');
    }
}
