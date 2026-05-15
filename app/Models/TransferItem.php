<?php

namespace App\Models;

use App\Enums\BazarAdjustType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferItem extends Model
{
    use HasUuids;

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'transfer_transaction_id',
        'item_id',
        'qty',
        'supplier_cost_snapshot',
        'base_margin_type_snapshot',
        'base_margin_value_snapshot',
        'base_selling_price_snapshot',
        'bazar_adjust_type',
        'bazar_adjust_value',
        'bazar_selling_price',
        'note',
    ];

    protected $hidden = [
        'supplier_cost_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'bazar_adjust_type' => BazarAdjustType::class,
            'supplier_cost_snapshot' => 'decimal:2',
            'base_margin_value_snapshot' => 'decimal:2',
            'base_selling_price_snapshot' => 'decimal:2',
            'bazar_adjust_value' => 'decimal:2',
            'bazar_selling_price' => 'decimal:2',
        ];
    }

    public function transferTransaction(): BelongsTo
    {
        return $this->belongsTo(TransferTransaction::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
