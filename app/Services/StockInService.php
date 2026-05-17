<?php

namespace App\Services;

use App\Enums\LocationType;
use App\Enums\StockStatus;
use App\Models\Item;
use App\Models\Location;
use App\Models\StockInItem;
use App\Models\StockInTransaction;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class StockInService
{
    public function __construct(
        private readonly StockBalanceService $stockBalanceService,
        private readonly StockMovementService $stockMovementService,
    ) {}

    /**
     * @param  list<string>  $barcodes
     */
    public function validateBarcodes(array $barcodes): void
    {
        $invalid = [];

        foreach ($barcodes as $barcode) {
            if (! Item::query()->where('barcode', $barcode)->exists()) {
                $invalid[] = $barcode;
            }
        }

        if ($invalid === []) {
            return;
        }

        $list = implode(', ', $invalid);

        throw new InvalidArgumentException(
            "Barcode tidak ditemukan di katalog: {$list}. Buat katalog dulu di web admin."
        );
    }

    public function generateTransactionNumber(): string
    {
        $datePrefix = now()->format('Ymd');
        $prefix = "SIT-{$datePrefix}-";

        $lastNumber = StockInTransaction::query()
            ->where('transaction_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('transaction_number')
            ->value('transaction_number');

        $sequence = 1;

        if ($lastNumber !== null) {
            $sequence = (int) substr($lastNumber, -5) + 1;
        }

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createTransaction(array $data, User $createdBy): StockInTransaction
    {
        return DB::transaction(function () use ($data, $createdBy): StockInTransaction {
            $barcodes = collect($data['items'])->pluck('barcode')->all();
            $this->validateBarcodes($barcodes);
            $this->validateItems($data['items']);

            $warehouse = Location::query()
                ->where('location_type', LocationType::CENTRAL_WAREHOUSE->value)
                ->orderBy('created_at')
                ->first();

            if ($warehouse === null) {
                throw new RuntimeException('Lokasi gudang pusat tidak ditemukan.');
            }

            $transaction = $this->createStockInTransaction($data, $createdBy);

            $totalReceived = 0;
            $totalAvailable = 0;
            $totalDamaged = 0;

            foreach ($data['items'] as $itemData) {
                $item = Item::query()->where('barcode', $itemData['barcode'])->firstOrFail();

                $stockInItem = StockInItem::query()->create([
                    'stock_in_transaction_id' => $transaction->id,
                    'item_id' => $item->id,
                    'qty_received' => $itemData['qty_received'],
                    'qty_available' => $itemData['qty_available'],
                    'qty_damaged' => $itemData['qty_damaged'],
                    'supplier_cost' => $itemData['supplier_cost'],
                    'base_margin_type' => $itemData['base_margin_type'],
                    'base_margin_value' => $itemData['base_margin_value'],
                    'base_selling_price' => $itemData['base_selling_price'],
                    'qc_note' => $itemData['qc_note'] ?? null,
                ]);

                if ($itemData['qty_available'] > 0) {
                    $this->stockBalanceService->increase(
                        $item->id,
                        $warehouse->id,
                        StockStatus::AVAILABLE,
                        $itemData['qty_available'],
                    );

                    $this->stockMovementService->createForStockIn(
                        $transaction,
                        $stockInItem,
                        StockStatus::AVAILABLE,
                        $itemData['qty_available'],
                        $createdBy,
                    );
                }

                if ($itemData['qty_damaged'] > 0) {
                    $this->stockBalanceService->increase(
                        $item->id,
                        $warehouse->id,
                        StockStatus::DAMAGED,
                        $itemData['qty_damaged'],
                    );

                    $this->stockMovementService->createForStockIn(
                        $transaction,
                        $stockInItem,
                        StockStatus::DAMAGED,
                        $itemData['qty_damaged'],
                        $createdBy,
                    );
                }

                $item->update([
                    'latest_supplier_cost' => $itemData['supplier_cost'],
                    'latest_base_margin_type' => $itemData['base_margin_type'],
                    'latest_base_margin_value' => $itemData['base_margin_value'],
                    'latest_base_selling_price' => $itemData['base_selling_price'],
                ]);

                $totalReceived += $itemData['qty_received'];
                $totalAvailable += $itemData['qty_available'];
                $totalDamaged += $itemData['qty_damaged'];
            }

            $transaction->update([
                'total_qty_received' => $totalReceived,
                'total_qty_available' => $totalAvailable,
                'total_qty_damaged' => $totalDamaged,
            ]);

            return $transaction->fresh([
                'stockInItems.item',
                'createdBy',
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createStockInTransaction(array $data, User $createdBy): StockInTransaction
    {
        $attempts = 0;

        while ($attempts < 5) {
            try {
                return StockInTransaction::query()->create([
                    'transaction_number' => $this->generateTransactionNumber(),
                    'supplier_name' => $data['supplier_name'] ?? null,
                    'transaction_date' => $data['transaction_date'],
                    'total_qty_received' => 0,
                    'total_qty_available' => 0,
                    'total_qty_damaged' => 0,
                    'note' => $data['note'] ?? null,
                    'photo_id' => $data['photo_id'] ?? null,
                    'created_by' => $createdBy->id,
                ]);
            } catch (QueryException $exception) {
                if (! $this->isUniqueTransactionNumberViolation($exception)) {
                    throw $exception;
                }

                $attempts++;
            }
        }

        throw new RuntimeException('Gagal menghasilkan transaction_number yang unik.');
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function validateItems(array $items): void
    {
        foreach ($items as $itemData) {
            $barcode = $itemData['barcode'] ?? 'unknown';

            if ($itemData['qty_received'] <= 0) {
                throw new InvalidArgumentException("qty_received harus lebih dari 0 untuk item {$barcode}.");
            }

            if ($itemData['qty_received'] !== $itemData['qty_available'] + $itemData['qty_damaged']) {
                throw new InvalidArgumentException(
                    "qty_received harus sama dengan qty_available + qty_damaged untuk item {$barcode}."
                );
            }

            if ($itemData['supplier_cost'] <= 0) {
                throw new InvalidArgumentException("supplier_cost harus lebih dari 0 untuk item {$barcode}.");
            }
        }
    }

    private function isUniqueTransactionNumberViolation(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'unique')
            || str_contains($message, 'duplicate');
    }
}
