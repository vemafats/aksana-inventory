<?php

namespace App\Services;

use App\Enums\LocationStatus;
use App\Enums\StockStatus;
use App\Models\Employee;
use App\Models\Item;
use App\Models\Location;
use App\Models\LocationAssignment;
use App\Models\SalesItem;
use App\Models\SalesTransaction;
use App\Models\TransferItem;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SalesService
{
    public function __construct(
        private readonly StockBalanceService $stockBalanceService,
        private readonly StockMovementService $stockMovementService,
        private readonly PriceCalculationService $priceCalculationService,
    ) {}

    public function generateSalesNumber(): string
    {
        $datePrefix = now()->format('Ymd');
        $prefix = "SAL-{$datePrefix}-";

        $lastNumber = SalesTransaction::query()
            ->where('sales_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('sales_number')
            ->value('sales_number');

        $sequence = 1;

        if ($lastNumber !== null) {
            $sequence = (int) substr($lastNumber, -5) + 1;
        }

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }

    public function validateLocation(string $locationId): Location
    {
        $location = Location::query()->find($locationId);

        if ($location === null) {
            throw new InvalidArgumentException('Lokasi tidak ditemukan.');
        }

        if ($location->status !== LocationStatus::ACTIVE) {
            throw new InvalidArgumentException(
                "Lokasi {$location->location_name} tidak aktif. Transaksi tidak bisa dilakukan."
            );
        }

        return $location;
    }

    public function validateEmployeeAssignment(string $employeeId, string $locationId): void
    {
        $assigned = LocationAssignment::query()
            ->where('employee_id', $employeeId)
            ->where('location_id', $locationId)
            ->where('is_active', true)
            ->exists();

        if (! $assigned) {
            throw new InvalidArgumentException('Karyawan tidak terdaftar di lokasi ini.');
        }
    }

    /**
     * @param  list<array{item_id: string, qty: int}>  $items
     */
    public function validateStock(array $items, string $locationId): void
    {
        $messages = [];

        foreach ($items as $line) {
            $item = Item::query()->find($line['item_id']);

            if ($item === null) {
                continue;
            }

            $qty = (int) $line['qty'];
            $available = $this->stockBalanceService->getBalance(
                $item->id,
                $locationId,
                StockStatus::AVAILABLE,
            );

            if (! $this->stockBalanceService->validateEnoughStock(
                $item->id,
                $locationId,
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
    public function createTransaction(array $data, User $createdBy): SalesTransaction
    {
        $location = $this->validateLocation($data['location_id']);
        $employeeId = $this->resolveEmployeeId($data, $createdBy);
        $data['employee_id'] = $employeeId;

        if ($employeeId !== null) {
            $this->validateEmployeeAssignment($employeeId, $location->id);
        }

        $this->validateStock($data['items'], $location->id);

        return DB::transaction(function () use ($data, $createdBy, $location): SalesTransaction {
            $preparedItems = [];

            foreach ($data['items'] as $line) {
                $preparedItems[] = $this->prepareSalesLine($line, $location->id);
            }

            $transactionTotals = $this->priceCalculationService->calculateTransactionTotals(
                array_column($preparedItems, 'totals'),
                $data['transaction_discount_type'],
                (float) $data['transaction_discount_value'],
            );

            $transaction = $this->createSalesTransactionRecord(
                $data,
                $createdBy,
                $transactionTotals,
            );

            foreach ($preparedItems as $prepared) {
                $salesItem = SalesItem::query()->create([
                    'sales_transaction_id' => $transaction->id,
                    ...$prepared['record'],
                ]);

                $this->stockBalanceService->decrease(
                    $prepared['record']['item_id'],
                    $location->id,
                    StockStatus::AVAILABLE,
                    $prepared['record']['qty'],
                );

                $this->stockMovementService->createForSale(
                    $transaction,
                    $salesItem,
                    $createdBy,
                );
            }

            return $transaction->fresh([
                'salesItems.item',
                'location',
                'employee',
                'createdBy',
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array{record: array<string, mixed>, totals: array<string, float>}
     */
    private function prepareSalesLine(array $line, string $locationId): array
    {
        $item = Item::query()->findOrFail($line['item_id']);
        $sellingPrice = $this->resolveSellingPrice($item, $locationId);
        $supplierCost = (float) $item->latest_supplier_cost;
        $baseSellingPrice = (float) $item->latest_base_selling_price;

        $totals = $this->priceCalculationService->calculateSalesItemTotals([
            'selling_price' => $sellingPrice,
            'qty' => (int) $line['qty'],
            'item_discount_type' => $line['item_discount_type'],
            'item_discount_value' => (float) $line['item_discount_value'],
            'supplier_cost_snapshot' => $supplierCost,
        ]);

        return [
            'record' => [
                'item_id' => $item->id,
                'qty' => (int) $line['qty'],
                'supplier_cost_snapshot' => $supplierCost,
                'base_selling_price_snapshot' => $baseSellingPrice,
                'bazar_selling_price_snapshot' => $sellingPrice,
                'selling_price' => $sellingPrice,
                'subtotal' => $totals['subtotal'],
                'item_discount_type' => $line['item_discount_type'],
                'item_discount_value' => $line['item_discount_value'],
                'item_discount_amount' => $totals['item_discount_amount'],
                'total_after_discount' => $totals['total_after_discount'],
                'gross_profit' => $totals['gross_profit'],
            ],
            'totals' => $totals,
        ];
    }

    private function resolveSellingPrice(Item $item, string $locationId): float
    {
        $transferItem = TransferItem::query()
            ->where('item_id', $item->id)
            ->whereHas('transferTransaction', function ($query) use ($locationId): void {
                $query->where('to_location_id', $locationId)
                    ->where('status', 'completed');
            })
            ->with('transferTransaction')
            ->get()
            ->sortByDesc(fn (TransferItem $transferItem) => $transferItem->transferTransaction?->transfer_date)
            ->first();

        if ($transferItem !== null) {
            return (float) $transferItem->bazar_selling_price;
        }

        return (float) $item->latest_base_selling_price;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveEmployeeId(array $data, User $createdBy): ?string
    {
        if (! empty($data['employee_id'])) {
            return $data['employee_id'];
        }

        return Employee::query()
            ->where('is_active', true)
            ->where(function ($query) use ($createdBy): void {
                $query->where('name', $createdBy->name)
                    ->orWhere('email', $createdBy->email);
            })
            ->value('id');
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, float>  $transactionTotals
     */
    private function createSalesTransactionRecord(
        array $data,
        User $createdBy,
        array $transactionTotals,
    ): SalesTransaction {
        $attempts = 0;

        while ($attempts < 5) {
            try {
                return SalesTransaction::query()->create([
                    'sales_number' => $this->generateSalesNumber(),
                    'location_id' => $data['location_id'],
                    'employee_id' => $data['employee_id'],
                    'transaction_date' => $data['transaction_date'],
                    'subtotal_amount' => $transactionTotals['subtotal_amount'],
                    'item_discount_amount' => $transactionTotals['item_discount_amount'],
                    'total_after_item_discount' => $transactionTotals['total_after_item_discount'],
                    'transaction_discount_type' => $data['transaction_discount_type'],
                    'transaction_discount_value' => $data['transaction_discount_value'],
                    'transaction_discount_amount' => $transactionTotals['transaction_discount_amount'],
                    'grand_total' => $transactionTotals['grand_total'],
                    'payment_method' => $data['payment_method'],
                    'note' => $data['note'] ?? null,
                    'photo_id' => $data['photo_id'] ?? null,
                    'created_by' => $createdBy->id,
                ]);
            } catch (QueryException $exception) {
                if (! $this->isUniqueSalesNumberViolation($exception)) {
                    throw $exception;
                }

                $attempts++;
            }
        }

        throw new \RuntimeException('Gagal menghasilkan sales_number yang unik.');
    }

    private function isUniqueSalesNumberViolation(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'unique')
            || str_contains($message, 'duplicate');
    }
}
