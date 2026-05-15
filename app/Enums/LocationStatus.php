<?php

namespace App\Enums;

enum LocationStatus: string
{
    case DRAFT     = 'draft';
    case ACTIVE    = 'active';
    case CLOSED    = 'closed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT     => 'Draf',
            self::ACTIVE    => 'Aktif',
            self::CLOSED    => 'Ditutup',
            self::CANCELLED => 'Dibatalkan',
        };
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function canBeClosed(): bool
    {
        return $this === self::ACTIVE;
    }

    public function canReceiveSales(): bool
    {
        return $this === self::ACTIVE;
    }

    public function canReceiveStock(): bool
    {
        return in_array($this, [self::ACTIVE, self::DRAFT]);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
