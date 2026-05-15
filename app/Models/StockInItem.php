<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockInItem extends Model
{
    use HasUuids;

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'stock_in_transaction_id',
        'item_id',
        'qty_received',
        'qty_available',
        'qty_damaged',
        'supplier_cost',
        'base_margin_type',
        'base_margin_value',
        'base_selling_price',
        'qc_note',
    ];

    protected $hidden = [
        'supplier_cost',
    ];

    protected function casts(): array
    {
        return [
            'supplier_cost' => 'decimal:2',
            'base_margin_value' => 'decimal:2',
            'base_selling_price' => 'decimal:2',
        ];
    }

    public function stockInTransaction(): BelongsTo
    {
        return $this->belongsTo(StockInTransaction::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
