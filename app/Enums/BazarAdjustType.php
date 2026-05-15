<?php

namespace App\Enums;

enum BazarAdjustType: string
{
    case NONE       = 'none';
    case NOMINAL    = 'nominal';
    case PERCENTAGE = 'percentage';
    case MANUAL     = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::NONE       => 'Sama dengan Harga Dasar',
            self::NOMINAL    => 'Tambah Nominal (Rp)',
            self::PERCENTAGE => 'Tambah Persentase (%)',
            self::MANUAL     => 'Input Manual',
        };
    }

    public function usesBasePriceFormula(): bool
    {
        return in_array($this, [self::NONE, self::NOMINAL, self::PERCENTAGE], true);
    }

    public function calculateBazarPrice(float $basePrice, float $adjustValue): float
    {
        return match ($this) {
            self::NONE       => $basePrice,
            self::NOMINAL    => $basePrice + $adjustValue,
            self::PERCENTAGE => $basePrice * (1 + $adjustValue / 100),
            self::MANUAL     => $adjustValue,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
