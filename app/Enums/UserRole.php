<?php

namespace App\Enums;

enum UserRole: string
{
    case OWNER          = 'owner';
    case ADMIN          = 'admin';
    case ADMIN_GUDANG   = 'admin_gudang';
    case PIC_BAZAR      = 'pic_bazar';
    case SALES          = 'sales';

    public function label(): string
    {
        return match ($this) {
            self::OWNER        => 'Pemilik',
            self::ADMIN        => 'Administrator',
            self::ADMIN_GUDANG => 'Admin Gudang',
            self::PIC_BAZAR    => 'PIC Bazar',
            self::SALES        => 'Sales',
        };
    }

    public function canManageMasterData(): bool
    {
        return in_array($this, [self::OWNER, self::ADMIN]);
    }

    public function canManageCatalog(): bool
    {
        return in_array($this, [self::OWNER, self::ADMIN, self::ADMIN_GUDANG]);
    }

    public function canStockIn(): bool
    {
        return in_array($this, [self::OWNER, self::ADMIN, self::ADMIN_GUDANG]);
    }

    public function canTransfer(): bool
    {
        return in_array($this, [self::OWNER, self::ADMIN, self::ADMIN_GUDANG]);
    }

    public function canSell(): bool
    {
        return in_array($this, [self::OWNER, self::ADMIN, self::PIC_BAZAR, self::SALES]);
    }

    public function canStockOpname(): bool
    {
        return in_array($this, [self::OWNER, self::ADMIN, self::ADMIN_GUDANG, self::PIC_BAZAR, self::SALES]);
    }

    public function canReturnStock(): bool
    {
        return in_array($this, [self::OWNER, self::ADMIN, self::ADMIN_GUDANG, self::PIC_BAZAR]);
    }

    /**
     * Tutup bazar: Owner, Admin, Admin Gudang, PIC Bazar.
     * Sales TIDAK diizinkan. (Keputusan final: double-confirm tap)
     */
    public function canCloseBazar(): bool
    {
        return in_array($this, [self::OWNER, self::ADMIN, self::ADMIN_GUDANG, self::PIC_BAZAR]);
    }

    public function canViewFullReport(): bool
    {
        return in_array($this, [self::OWNER, self::ADMIN]);
    }

    public function canManageSettings(): bool
    {
        return in_array($this, [self::OWNER, self::ADMIN]);
    }

    public function canPrintBarcode(): bool
    {
        return in_array($this, [self::OWNER, self::ADMIN, self::ADMIN_GUDANG]);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
