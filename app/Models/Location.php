<?php

namespace App\Models;

use App\Enums\LocationStatus;
use App\Enums\LocationType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    use HasUuids;

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'location_code',
        'location_name',
        'location_type',
        'address',
        'start_date',
        'end_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'location_type' => LocationType::class,
            'status' => LocationStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function locationAssignments(): HasMany
    {
        return $this->hasMany(LocationAssignment::class);
    }

    public function stockBalances(): HasMany
    {
        return $this->hasMany(StockBalance::class);
    }

    public function salesTransactions(): HasMany
    {
        return $this->hasMany(SalesTransaction::class);
    }

    public function transfersFrom(): HasMany
    {
        return $this->hasMany(TransferTransaction::class, 'from_location_id');
    }

    public function transfersTo(): HasMany
    {
        return $this->hasMany(TransferTransaction::class, 'to_location_id');
    }

    public function stockOpnameTransactions(): HasMany
    {
        return $this->hasMany(StockOpnameTransaction::class);
    }

    public function isActive(): bool
    {
        return $this->status === LocationStatus::ACTIVE;
    }

    public function isCentral(): bool
    {
        return $this->location_type === LocationType::CENTRAL_WAREHOUSE;
    }
}
