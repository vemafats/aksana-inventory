<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesTransaction extends Model
{
    use HasUuids;

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'sales_number',
        'location_id',
        'employee_id',
        'transaction_date',
        'subtotal_amount',
        'item_discount_amount',
        'total_after_item_discount',
        'transaction_discount_type',
        'transaction_discount_value',
        'transaction_discount_amount',
        'grand_total',
        'payment_method',
        'note',
        'photo_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'datetime',
            'payment_method' => PaymentMethod::class,
            'transaction_discount_type' => DiscountType::class,
            'subtotal_amount' => 'decimal:2',
            'item_discount_amount' => 'decimal:2',
            'total_after_item_discount' => 'decimal:2',
            'transaction_discount_value' => 'decimal:2',
            'transaction_discount_amount' => 'decimal:2',
            'grand_total' => 'decimal:2',
        ];
    }

    public function salesItems(): HasMany
    {
        return $this->hasMany(SalesItem::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
