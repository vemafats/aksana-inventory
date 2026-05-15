<?php

namespace App\Enums;

enum MovementType: string
{
    case STOCK_IN_AVAILABLE       = 'stock_in_available';
    case STOCK_IN_DAMAGED         = 'stock_in_damaged';
    case TRANSFER_AVAILABLE       = 'transfer_available';
    case SALE                     = 'sale';
    case STOCK_OPNAME_PLUS        = 'stock_opname_plus';
    case STOCK_OPNAME_LOST        = 'stock_opname_lost';
    case AVAILABLE_TO_DAMAGED     = 'available_to_damaged';
    case RETURN_TO_WAREHOUSE      = 'return_to_warehouse';

    public function label(): string
    {
        return match ($this) {
            self::STOCK_IN_AVAILABLE   => 'Barang Masuk — Tersedia',
            self::STOCK_IN_DAMAGED     => 'Barang Masuk — Rusak',
            self::TRANSFER_AVAILABLE   => 'Transfer Stok',
            self::SALE                 => 'Penjualan',
            self::STOCK_OPNAME_PLUS    => 'Stok Opname — Koreksi Plus',
            self::STOCK_OPNAME_LOST    => 'Stok Opname — Hilang',
            self::AVAILABLE_TO_DAMAGED => 'Tersedia ke Rusak',
            self::RETURN_TO_WAREHOUSE  => 'Return ke Gudang',
        };
    }

    public function isSale(): bool
    {
        return $this === self::SALE;
    }

    public function isStockIn(): bool
    {
        return in_array($this, [self::STOCK_IN_AVAILABLE, self::STOCK_IN_DAMAGED], true);
    }

    public function increasesStock(): bool
    {
        return in_array($this, [
            self::STOCK_IN_AVAILABLE,
            self::STOCK_IN_DAMAGED,
            self::TRANSFER_AVAILABLE,
            self::STOCK_OPNAME_PLUS,
            self::RETURN_TO_WAREHOUSE,
        ]);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
