<?php

namespace App\Services;

use App\Enums\LocationType;
use App\Enums\MovementType;
use App\Enums\StockStatus;
use App\Models\Location;
use App\Models\SalesItem;
use App\Models\SalesTransaction;
use App\Models\StockInItem;
use App\Models\StockInTransaction;
use App\Models\StockMovement;
use App\Models\StockOpnameItem;
use App\Models\StockOpnameTransaction;
use App\Models\TransferItem;
use App\Models\TransferTransaction;
use App\Models\User;
use Illuminate\Database\QueryException;
use InvalidArgumentException;

class StockMovementService
{
    private const MAX_NUMBER_RETRIES = 5;

    public function generateMovementNumber(): string
    {
        $datePrefix = now()->format('Ymd');
        $prefix = "SM-{$datePrefix}-";

        $lastNumber = StockMovement::query()
            ->where('movement_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('movement_number')
            ->value('movement_number');

        $sequence = 1;

        if ($lastNumber !== null) {
            $sequence = (int) substr($lastNumber, -5) + 1;
        }

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createMovement(array $data): StockMovement
    {
        $this->validateMovementData($data);

        $movementType = $data['movement_type'] instanceof MovementType
            ? $data['movement_type']
            : MovementType::from($data['movement_type']);

        $attempts = 0;

        while ($attempts < self::MAX_NUMBER_RETRIES) {
            try {
                return StockMovement::query()->create([
                    'movement_number' => $this->generateMovementNumber(),
                    'movement_type' => $movementType,
                    'item_id' => $data['item_id'],
                    'from_location_id' => $data['from_location_id'] ?? null,
                    'to_location_id' => $data['to_location_id'] ?? null,
                    'from_stock_status' => $data['from_stock_status'] ?? null,
                    'to_stock_status' => $data['to_stock_status'] ?? null,
                    'qty' => $data['qty'],
                    'reference_type' => $data['reference_type'],
                    'reference_id' => $data['reference_id'],
                    'note' => $data['note'] ?? null,
                    'created_by' => $data['created_by'],
                    'created_at' => now(),
                ]);
            } catch (QueryException $exception) {
                if (! $this->isUniqueMovementNumberViolation($exception)) {
                    throw $exception;
                }

                $attempts++;
            }
        }

        throw new \RuntimeException('Gagal menghasilkan movement_number yang unik.');
    }

    public function createForStockIn(
        StockInTransaction $transaction,
        StockInItem $item,
        StockStatus $status,
        int $qty,
        User $createdBy,
    ): StockMovement {
        $movementType = match ($status) {
            StockStatus::AVAILABLE => MovementType::STOCK_IN_AVAILABLE,
            StockStatus::DAMAGED => MovementType::STOCK_IN_DAMAGED,
            StockStatus::LOST => throw new InvalidArgumentException('Stock in tidak mendukung status lost.'),
        };

        return $this->createMovement([
            'movement_type' => $movementType,
            'item_id' => $item->item_id,
            'qty' => $qty,
            'from_location_id' => null,
            'to_location_id' => $this->centralWarehouseLocationId(),
            'from_stock_status' => null,
            'to_stock_status' => $status->value,
            'reference_type' => 'stock_in',
            'reference_id' => $transaction->id,
            'note' => $item->qc_note,
            'created_by' => $createdBy->id,
        ]);
    }

    public function createForTransfer(
        TransferTransaction $transaction,
        TransferItem $transferItem,
        User $createdBy,
    ): StockMovement {
        return $this->createMovement([
            'movement_type' => MovementType::TRANSFER_AVAILABLE,
            'item_id' => $transferItem->item_id,
            'qty' => $transferItem->qty,
            'from_location_id' => $transaction->from_location_id,
            'to_location_id' => $transaction->to_location_id,
            'from_stock_status' => StockStatus::AVAILABLE->value,
            'to_stock_status' => StockStatus::AVAILABLE->value,
            'reference_type' => 'transfer',
            'reference_id' => $transaction->id,
            'note' => $transferItem->note,
            'created_by' => $createdBy->id,
        ]);
    }

    public function createForSale(
        SalesTransaction $transaction,
        SalesItem $saleItem,
        User $createdBy,
    ): StockMovement {
        return $this->createMovement([
            'movement_type' => MovementType::SALE,
            'item_id' => $saleItem->item_id,
            'qty' => $saleItem->qty,
            'from_location_id' => $transaction->location_id,
            'to_location_id' => null,
            'from_stock_status' => StockStatus::AVAILABLE->value,
            'to_stock_status' => null,
            'reference_type' => 'sale',
            'reference_id' => $transaction->id,
            'created_by' => $createdBy->id,
        ]);
    }

    public function createForReturn(
        TransferTransaction $transaction,
        TransferItem $transferItem,
        StockStatus $toStatus,
        int $qty,
        User $createdBy,
    ): StockMovement {
        return $this->createMovement([
            'movement_type' => MovementType::RETURN_TO_WAREHOUSE,
            'item_id' => $transferItem->item_id,
            'qty' => $qty,
            'from_location_id' => $transaction->from_location_id,
            'to_location_id' => $transaction->to_location_id,
            'from_stock_status' => StockStatus::AVAILABLE->value,
            'to_stock_status' => $toStatus->value,
            'reference_type' => 'transfer',
            'reference_id' => $transaction->id,
            'note' => $transferItem->note,
            'created_by' => $createdBy->id,
        ]);
    }

    public function createForOpnameAdjustment(
        StockOpnameTransaction $transaction,
        StockOpnameItem $opnameItem,
        MovementType $type,
        int $qty,
        User $createdBy,
    ): StockMovement {
        [$fromStatus, $toStatus] = match ($type) {
            MovementType::STOCK_OPNAME_PLUS => [null, StockStatus::AVAILABLE->value],
            MovementType::STOCK_OPNAME_LOST => [StockStatus::AVAILABLE->value, StockStatus::LOST->value],
            MovementType::AVAILABLE_TO_DAMAGED => [StockStatus::AVAILABLE->value, StockStatus::DAMAGED->value],
            default => throw new InvalidArgumentException("Movement type {$type->value} tidak didukung untuk opname."),
        };

        return $this->createMovement([
            'movement_type' => $type,
            'item_id' => $opnameItem->item_id,
            'qty' => $qty,
            'from_location_id' => $transaction->location_id,
            'to_location_id' => $transaction->location_id,
            'from_stock_status' => $fromStatus,
            'to_stock_status' => $toStatus,
            'reference_type' => 'stock_opname',
            'reference_id' => $transaction->id,
            'note' => $opnameItem->note,
            'created_by' => $createdBy->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateMovementData(array $data): void
    {
        $required = [
            'movement_type',
            'item_id',
            'qty',
            'reference_type',
            'reference_id',
            'created_by',
        ];

        foreach ($required as $key) {
            if (! array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
                throw new InvalidArgumentException("Field {$key} wajib diisi untuk stock movement.");
            }
        }

        if ((int) $data['qty'] <= 0) {
            throw new InvalidArgumentException('Qty stock movement harus lebih dari 0.');
        }
    }

    private function centralWarehouseLocationId(): string
    {
        $locationId = Location::query()
            ->where('location_type', LocationType::CENTRAL_WAREHOUSE->value)
            ->where('status', 'active')
            ->value('id');

        if ($locationId === null) {
            throw new \RuntimeException('Lokasi gudang pusat tidak ditemukan.');
        }

        return $locationId;
    }

    private function isUniqueMovementNumberViolation(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'unique')
            || str_contains($message, 'duplicate');
    }
}
