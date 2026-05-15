<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockInTransaction extends Model
{
    use HasUuids;

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'transaction_number',
        'supplier_name',
        'transaction_date',
        'total_qty_received',
        'total_qty_available',
        'total_qty_damaged',
        'note',
        'photo_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
        ];
    }

    public function stockInItems(): HasMany
    {
        return $this->hasMany(StockInItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
