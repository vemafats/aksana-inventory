<?php

namespace App\Enums;

enum DiscountType: string
{
    case NONE       = 'none';
    case NOMINAL    = 'nominal';
    case PERCENTAGE = 'percentage';

    public function label(): string
    {
        return match ($this) {
            self::NONE       => 'Tanpa Diskon',
            self::NOMINAL    => 'Nominal (Rp)',
            self::PERCENTAGE => 'Persentase (%)',
        };
    }

    public function hasDiscount(): bool
    {
        return $this !== self::NONE;
    }

    public function calculate(float $baseAmount, float $discountValue): float
    {
        return match ($this) {
            self::NONE       => 0,
            self::NOMINAL    => min($discountValue, $baseAmount),
            self::PERCENTAGE => $baseAmount * ($discountValue / 100),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
