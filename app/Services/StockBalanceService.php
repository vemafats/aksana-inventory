<?php

namespace App\Services;

use App\Enums\StockStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\StockBalance;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class StockBalanceService
{
    public function getBalance(string $itemId, string $locationId, StockStatus $status): int
    {
        $qty = StockBalance::query()
            ->where('item_id', $itemId)
            ->where('location_id', $locationId)
            ->where('stock_status', $status->value)
            ->value('qty');

        return (int) ($qty ?? 0);
    }

    /**
     * @return Collection<string, Collection<int, StockBalance>>
     */
    public function getBalancesByLocation(string $locationId): Collection
    {
        return StockBalance::query()
            ->where('location_id', $locationId)
            ->with([
                'item.category',
                'item.brand',
                'item.color',
                'item.size',
            ])
            ->get()
            ->groupBy('item_id');
    }

    /**
     * @return Collection<int, StockBalance>
     */
    public function getBalancesByItem(string $itemId): Collection
    {
        return StockBalance::query()
            ->where('item_id', $itemId)
            ->with('location')
            ->get();
    }

    public function increase(
        string $itemId,
        string $locationId,
        StockStatus $status,
        int $qty,
    ): StockBalance {
        $this->assertPositiveQty($qty, 'increase');

        return $this->upsert($itemId, $locationId, $status, $qty);
    }

    public function decrease(
        string $itemId,
        string $locationId,
        StockStatus $status,
        int $qty,
    ): StockBalance {
        $this->assertPositiveQty($qty, 'decrease');

        if (! $this->validateEnoughStock($itemId, $locationId, $status, $qty)) {
            throw new InsufficientStockException(
                $itemId,
                $locationId,
                $status,
                $qty,
                $this->getBalance($itemId, $locationId, $status),
            );
        }

        return $this->upsert($itemId, $locationId, $status, -$qty);
    }

    public function move(
        string $itemId,
        string $fromLocationId,
        string $toLocationId,
        StockStatus $fromStatus,
        StockStatus $toStatus,
        int $qty,
    ): void {
        $this->assertPositiveQty($qty, 'move');

        $this->decrease($itemId, $fromLocationId, $fromStatus, $qty);
        $this->increase($itemId, $toLocationId, $toStatus, $qty);
    }

    public function validateEnoughStock(
        string $itemId,
        string $locationId,
        StockStatus $status,
        int $qty,
    ): bool {
        if ($qty <= 0) {
            return true;
        }

        return $this->getBalance($itemId, $locationId, $status) >= $qty;
    }

    public function upsert(
        string $itemId,
        string $locationId,
        StockStatus $status,
        int $qtyChange,
    ): StockBalance {
        if ($qtyChange === 0) {
            return $this->findOrCreateZeroBalance($itemId, $locationId, $status);
        }

        $balance = StockBalance::query()
            ->where('item_id', $itemId)
            ->where('location_id', $locationId)
            ->where('stock_status', $status->value)
            ->lockForUpdate()
            ->first();

        if ($balance === null) {
            if ($qtyChange < 0) {
                throw new InsufficientStockException(
                    $itemId,
                    $locationId,
                    $status,
                    abs($qtyChange),
                    0,
                );
            }

            return StockBalance::query()->create([
                'item_id' => $itemId,
                'location_id' => $locationId,
                'stock_status' => $status,
                'qty' => $qtyChange,
            ]);
        }

        $newQty = $balance->qty + $qtyChange;

        if ($newQty < 0) {
            throw new InsufficientStockException(
                $itemId,
                $locationId,
                $status,
                abs($qtyChange),
                $balance->qty,
            );
        }

        $balance->update(['qty' => $newQty]);

        return $balance->refresh();
    }

    private function findOrCreateZeroBalance(
        string $itemId,
        string $locationId,
        StockStatus $status,
    ): StockBalance {
        return StockBalance::query()->firstOrCreate(
            [
                'item_id' => $itemId,
                'location_id' => $locationId,
                'stock_status' => $status,
            ],
            ['qty' => 0],
        );
    }

    private function assertPositiveQty(int $qty, string $operation): void
    {
        if ($qty <= 0) {
            throw new InvalidArgumentException("Qty untuk {$operation} harus lebih dari 0.");
        }
    }
}
