<?php

namespace App\Services;

use App\Enums\BazarAdjustType;
use App\Enums\LocationStatus;
use App\Enums\StockStatus;
use App\Models\Item;
use App\Models\Location;
use App\Models\TransferItem;
use App\Models\TransferTransaction;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

class TransferService
{
    public function __construct(
        private readonly StockBalanceService $stockBalanceService,
        private readonly StockMovementService $stockMovementService,
    ) {}

    public function generateTransferNumber(): string
    {
        $datePrefix = now()->format('Ymd');
        $prefix = "TRF-{$datePrefix}-";

        $lastNumber = TransferTransaction::query()
            ->where('transfer_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('transfer_number')
            ->value('transfer_number');

        $sequence = 1;

        if ($lastNumber !== null) {
            $sequence = (int) substr($lastNumber, -5) + 1;
        }

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createTransfer(array $data, User $createdBy): TransferTransaction
    {
        $this->validateLocations($data);
        $this->validateSufficientStockForItems($data);

        return DB::transaction(function () use ($data, $createdBy): TransferTransaction {
            $transaction = $this->createTransferTransaction($data, $createdBy);

            foreach ($data['items'] as $itemData) {
                $item = Item::query()->findOrFail($itemData['item_id']);
                $adjustType = BazarAdjustType::from($itemData['bazar_adjust_type']);
                $baseSellingPrice = (float) $item->latest_base_selling_price;

                $bazarSellingPrice = $this->resolveBazarSellingPrice(
                    $adjustType,
                    $baseSellingPrice,
                    (float) $itemData['bazar_adjust_value'],
                    (float) $itemData['bazar_selling_price'],
                );

                $transferItem = TransferItem::query()->create([
                    'transfer_transaction_id' => $transaction->id,
                    'item_id' => $item->id,
                    'qty' => $itemData['qty'],
                    'supplier_cost_snapshot' => $item->latest_supplier_cost,
                    'base_margin_type_snapshot' => $item->latest_base_margin_type,
                    'base_margin_value_snapshot' => $item->latest_base_margin_value,
                    'base_selling_price_snapshot' => $baseSellingPrice,
                    'bazar_adjust_type' => $adjustType,
                    'bazar_adjust_value' => $itemData['bazar_adjust_value'],
                    'bazar_selling_price' => $bazarSellingPrice,
                    'note' => $itemData['note'] ?? null,
                ]);

                $this->stockBalanceService->move(
                    $item->id,
                    $data['from_location_id'],
                    $data['to_location_id'],
                    StockStatus::AVAILABLE,
                    StockStatus::AVAILABLE,
                    $itemData['qty'],
                );

                $this->stockMovementService->createForTransfer(
                    $transaction,
                    $transferItem,
                    $createdBy,
                );
            }

            return $transaction->fresh([
                'transferItems.item',
                'fromLocation',
                'toLocation',
                'createdBy',
            ]);
        });
    }

    public function completeTransfer(TransferTransaction $transfer): void
    {
        if ($transfer->status === 'completed') {
            throw new LogicException('Transfer sudah selesai.');
        }
    }

    public function cancelTransfer(TransferTransaction $transfer): void
    {
        if ($transfer->status !== 'draft') {
            throw new LogicException('Hanya transfer berstatus draft yang dapat dibatalkan.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateLocations(array $data): void
    {
        if ($data['from_location_id'] === $data['to_location_id']) {
            throw new InvalidArgumentException('Lokasi asal dan tujuan tidak boleh sama.');
        }

        $fromLocation = Location::query()->find($data['from_location_id']);
        $toLocation = Location::query()->find($data['to_location_id']);

        if ($fromLocation === null || $toLocation === null) {
            throw new InvalidArgumentException('Lokasi tidak ditemukan.');
        }

        if ($fromLocation->status !== LocationStatus::ACTIVE) {
            throw new InvalidArgumentException('Lokasi asal tidak aktif.');
        }

        if ($toLocation->status !== LocationStatus::ACTIVE) {
            throw new InvalidArgumentException('Lokasi tujuan tidak aktif.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateSufficientStockForItems(array $data): void
    {
        $messages = [];

        foreach ($data['items'] as $itemData) {
            $item = Item::query()->find($itemData['item_id']);

            if ($item === null) {
                continue;
            }

            $qty = (int) $itemData['qty'];
            $available = $this->stockBalanceService->getBalance(
                $item->id,
                $data['from_location_id'],
                StockStatus::AVAILABLE,
            );

            if (! $this->stockBalanceService->validateEnoughStock(
                $item->id,
                $data['from_location_id'],
                StockStatus::AVAILABLE,
                $qty,
            )) {
                $messages[] = "Stok tidak cukup untuk {$item->item_name}. Tersedia: {$available}, diminta: {$qty}";
            }
        }

        if ($messages !== []) {
            throw new InvalidArgumentException(implode(' ', $messages));
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createTransferTransaction(array $data, User $createdBy): TransferTransaction
    {
        $attempts = 0;

        while ($attempts < 5) {
            try {
                return TransferTransaction::query()->create([
                    'transfer_number' => $this->generateTransferNumber(),
                    'from_location_id' => $data['from_location_id'],
                    'to_location_id' => $data['to_location_id'],
                    'transfer_date' => $data['transfer_date'],
                    'status' => 'completed',
                    'note' => $data['note'] ?? null,
                    'created_by' => $createdBy->id,
                ]);
            } catch (QueryException $exception) {
                if (! $this->isUniqueTransferNumberViolation($exception)) {
                    throw $exception;
                }

                $attempts++;
            }
        }

        throw new \RuntimeException('Gagal menghasilkan transfer_number yang unik.');
    }

    private function resolveBazarSellingPrice(
        BazarAdjustType $adjustType,
        float $baseSellingPrice,
        float $adjustValue,
        float $requestedBazarSellingPrice,
    ): float {
        if ($adjustType === BazarAdjustType::MANUAL) {
            return $requestedBazarSellingPrice;
        }

        return $adjustType->calculateBazarPrice($baseSellingPrice, $adjustValue);
    }

    private function isUniqueTransferNumberViolation(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'unique')
            || str_contains($message, 'duplicate');
    }
}
