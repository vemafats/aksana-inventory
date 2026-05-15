<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockOpnameItem extends Model
{
    use HasUuids;

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'stock_opname_transaction_id',
        'item_id',
        'system_available_qty',
        'physical_available_qty',
        'available_difference_qty',
        'damaged_qty',
        'lost_qty',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'system_available_qty' => 'integer',
            'physical_available_qty' => 'integer',
            'available_difference_qty' => 'integer',
            'damaged_qty' => 'integer',
            'lost_qty' => 'integer',
        ];
    }

    public function stockOpnameTransaction(): BelongsTo
    {
        return $this->belongsTo(StockOpnameTransaction::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
