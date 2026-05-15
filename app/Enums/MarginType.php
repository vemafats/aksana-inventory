<?php

namespace App\Enums;

enum MarginType: string
{
    case NOMINAL    = 'nominal';
    case PERCENTAGE = 'percentage';

    public function label(): string
    {
        return match ($this) {
            self::NOMINAL    => 'Nominal (Rp)',
            self::PERCENTAGE => 'Persentase (%)',
        };
    }

    public function isPercentage(): bool
    {
        return $this === self::PERCENTAGE;
    }

    public function calculateSellingPrice(float $cost, float $marginValue): float
    {
        return match ($this) {
            self::NOMINAL    => $cost + $marginValue,
            self::PERCENTAGE => $cost * (1 + $marginValue / 100),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
