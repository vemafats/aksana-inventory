<?php

namespace App\Models;

use App\Enums\MovementType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class StockMovement extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'movement_number',
        'movement_type',
        'item_id',
        'from_location_id',
        'to_location_id',
        'from_stock_status',
        'to_stock_status',
        'qty',
        'reference_type',
        'reference_id',
        'note',
        'created_by',
        'created_at',
    ];

    protected $dates = [
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'movement_type' => MovementType::class,
            'qty' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function delete(): ?bool
    {
        throw new RuntimeException('Stock movements are immutable audit records');
    }
}
