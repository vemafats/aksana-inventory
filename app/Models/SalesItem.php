<?php

namespace App\Models;

use App\Enums\DiscountType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesItem extends Model
{
    use HasUuids;

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'sales_transaction_id',
        'item_id',
        'qty',
        'supplier_cost_snapshot',
        'base_selling_price_snapshot',
        'bazar_selling_price_snapshot',
        'selling_price',
        'subtotal',
        'item_discount_type',
        'item_discount_value',
        'item_discount_amount',
        'total_after_discount',
        'gross_profit',
    ];

    protected $hidden = [
        'supplier_cost_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'item_discount_type' => DiscountType::class,
            'supplier_cost_snapshot' => 'decimal:2',
            'base_selling_price_snapshot' => 'decimal:2',
            'bazar_selling_price_snapshot' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'item_discount_value' => 'decimal:2',
            'item_discount_amount' => 'decimal:2',
            'total_after_discount' => 'decimal:2',
            'gross_profit' => 'decimal:2',
        ];
    }

    public function salesTransaction(): BelongsTo
    {
        return $this->belongsTo(SalesTransaction::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
