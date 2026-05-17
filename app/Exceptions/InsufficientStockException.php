<?php

namespace App\Exceptions;

use App\Enums\StockStatus;
use Exception;

class InsufficientStockException extends Exception
{
    public function __construct(
        string $itemId,
        string $locationId,
        StockStatus $status,
        int $requested,
        int $available,
    ) {
        parent::__construct(sprintf(
            'Stok tidak mencukupi untuk item %s di lokasi %s (status: %s). Diminta: %d, tersedia: %d',
            $itemId,
            $locationId,
            $status->value,
            $requested,
            $available,
        ));
    }
}
