<?php

namespace App\Enums;

enum StockStatus: string
{
    case AVAILABLE = 'available';
    case DAMAGED   = 'damaged';
    case LOST      = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::AVAILABLE => 'Tersedia',
            self::DAMAGED   => 'Rusak',
            self::LOST      => 'Hilang',
        };
    }

    public function canBeSold(): bool
    {
        return $this === self::AVAILABLE;
    }

    public function canBeTransferred(): bool
    {
        return $this === self::AVAILABLE;
    }

    public function isAvailable(): bool
    {
        return $this === self::AVAILABLE;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
