<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CASH     = 'cash';
    case QRIS     = 'qris';
    case TRANSFER = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::CASH     => 'Tunai',
            self::QRIS     => 'QRIS',
            self::TRANSFER => 'Transfer Bank',
        };
    }

    public function isNonCash(): bool
    {
        return in_array($this, [self::QRIS, self::TRANSFER], true);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
