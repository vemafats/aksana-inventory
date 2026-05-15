<?php

namespace App\Enums;

enum LocationType: string
{
    case CENTRAL_WAREHOUSE = 'central_warehouse';
    case BAZAR             = 'bazar';
    case OUTLET            = 'outlet';
    case STORE             = 'store';
    case EVENT             = 'event';

    public function label(): string
    {
        return match ($this) {
            self::CENTRAL_WAREHOUSE => 'Gudang Pusat',
            self::BAZAR             => 'Bazar',
            self::OUTLET            => 'Outlet',
            self::STORE             => 'Toko',
            self::EVENT             => 'Event',
        };
    }

    public function isSalesLocation(): bool
    {
        return $this !== self::CENTRAL_WAREHOUSE;
    }

    public function isWarehouse(): bool
    {
        return $this === self::CENTRAL_WAREHOUSE;
    }

    public function canReceiveTransfer(): bool
    {
        return true;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
