<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    use HasUuids;

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'category_id',
        'brand_id',
        'model_id',
        'color_id',
        'size_id',
        'sku',
        'barcode',
        'item_name',
        'catalog_photo_path',
        'latest_supplier_cost',
        'latest_base_margin_type',
        'latest_base_margin_value',
        'latest_base_selling_price',
        'description',
        'is_active',
    ];

    protected $hidden = [
        'latest_supplier_cost',
        'latest_base_margin_type',
        'latest_base_margin_value',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'latest_supplier_cost' => 'decimal:2',
            'latest_base_margin_value' => 'decimal:2',
            'latest_base_selling_price' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function productModel(): BelongsTo
    {
        return $this->belongsTo(ProductModel::class, 'model_id');
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class);
    }

    public function stockBalances(): HasMany
    {
        return $this->hasMany(StockBalance::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function stockInItems(): HasMany
    {
        return $this->hasMany(StockInItem::class);
    }

    public function transferItems(): HasMany
    {
        return $this->hasMany(TransferItem::class);
    }

    public function salesItems(): HasMany
    {
        return $this->hasMany(SalesItem::class);
    }

    public function stockOpnameItems(): HasMany
    {
        return $this->hasMany(StockOpnameItem::class);
    }
}
