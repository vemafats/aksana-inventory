<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Enums\StockStatus;
use App\Enums\UserRole;
use App\Exceptions\OpnameSessionActiveException;
use App\Models\StockOpnameItem;
use App\Models\StockOpnameTransaction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

class StockOpnameService
{
    public function __construct(
        private readonly StockBalanceService $stockBalanceService,
        private readonly StockMovementService $stockMovementService,
    ) {}

    public function generateOpnameNumber(): string
    {
        $datePrefix = now()->format('Ymd');
        $prefix = "OPN-{$datePrefix}-";

        $lastNumber = StockOpnameTransaction::query()
            ->where('opname_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('opname_number')
            ->value('opname_number');

        $sequence = 1;

        if ($lastNumber !== null) {
            $sequence = (int) substr($lastNumber, -5) + 1;
        }

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }

    public function checkActiveSession(?string $locationId = null): ?StockOpnameTransaction
    {
        $query = StockOpnameTransaction::query()
            ->whereIn('validation_status', ['draft', 'pending_validation']);

        if ($locationId !== null) {
            $query->where('location_id', $locationId);
        }

        return $query->latest()->first();
    }

    public function createSession(
        string $locationId,
        string $opnameDate,
        User $createdBy,
    ): StockOpnameTransaction {
        $activeSession = $this->checkActiveSession();

        if ($activeSession !== null) {
            throw new OpnameSessionActiveException($activeSession->id);
        }

        return DB::transaction(function () use ($locationId, $opnameDate, $createdBy): StockOpnameTransaction {
            $attempts = 0;

            while ($attempts < 5) {
                try {
                    return StockOpnameTransaction::query()->create([
                        'opname_number' => $this->generateOpnameNumber(),
                        'location_id' => $locationId,
                        'opname_date' => $opnameDate,
                        'status' => 'draft',
                        'validation_status' => 'draft',
                        'validator_id' => null,
                        'created_by' => $createdBy->id,
                    ]);
                } catch (QueryException $exception) {
                    if (! $this->isUniqueOpnameNumberViolation($exception)) {
                        throw $exception;
                    }

                    $attempts++;
                }
            }

            throw new \RuntimeException('Gagal menghasilkan opname_number yang unik.');
        });
    }

    public function addItem(
        StockOpnameTransaction $opname,
        string $itemId,
        int $physicalAvailableQty,
        int $damagedQty,
        ?string $photoId,
        User $createdBy,
    ): StockOpnameItem {
        if ($opname->status !== 'draft' || $opname->validation_status !== 'draft') {
            throw new LogicException('Hanya sesi opname berstatus draft yang dapat diubah.');
        }

        $systemAvailableQty = $this->stockBalanceService->getBalance(
            $itemId,
            $opname->location_id,
            StockStatus::AVAILABLE,
        );

        $availableDifferenceQty = $physicalAvailableQty - $systemAvailableQty;

        $lostQty = $physicalAvailableQty + $damagedQty < $systemAvailableQty
            ? $systemAvailableQty - $physicalAvailableQty - $damagedQty
            : 0;

        if ($photoId !== null) {
            $opname->update(['photo_id' => $photoId]);
        }

        return StockOpnameItem::query()->updateOrCreate(
            [
                'stock_opname_transaction_id' => $opname->id,
                'item_id' => $itemId,
            ],
            [
                'system_available_qty' => $systemAvailableQty,
                'physical_available_qty' => $physicalAvailableQty,
                'available_difference_qty' => $availableDifferenceQty,
                'damaged_qty' => $damagedQty,
                'lost_qty' => $lostQty,
            ],
        );
    }

    public function submitForValidation(
        StockOpnameTransaction $opname,
        User $submittedBy,
    ): StockOpnameTransaction {
        if ($opname->validation_status !== 'draft') {
            throw new LogicException('Sesi opname tidak dalam status draft.');
        }

        if ($opname->stockOpnameItems()->count() < 1) {
            throw new InvalidArgumentException('Sesi opname harus memiliki minimal 1 item.');
        }

        $opname->update([
            'validation_status' => 'pending_validation',
        ]);

        return $opname->fresh(['stockOpnameItems.item', 'location', 'createdBy']);
    }

    public function validateOpname(
        StockOpnameTransaction $opname,
        User $validator,
    ): StockOpnameTransaction {
        $this->assertCanValidateOrReject($validator);

        if ($opname->validation_status !== 'pending_validation') {
            throw new LogicException('Sesi opname harus menunggu validasi.');
        }

        return DB::transaction(function () use ($opname, $validator): StockOpnameTransaction {
            $opname->load('stockOpnameItems');

            foreach ($opname->stockOpnameItems as $opnameItem) {
                $this->applyOpnameItemAdjustments($opname, $opnameItem, $validator);
            }

            $opname->update([
                'validation_status' => 'validated',
                'validator_id' => $validator->id,
                'validated_at' => now(),
                'status' => 'completed',
            ]);

            return $opname->fresh(['stockOpnameItems.item', 'location', 'createdBy', 'validator']);
        });
    }

    public function rejectOpname(
        StockOpnameTransaction $opname,
        User $validator,
        string $rejectionNote,
    ): StockOpnameTransaction {
        $this->assertCanValidateOrReject($validator);

        if ($opname->validation_status !== 'pending_validation') {
            throw new LogicException('Sesi opname harus menunggu validasi.');
        }

        if (trim($rejectionNote) === '') {
            throw new InvalidArgumentException('Catatan penolakan wajib diisi.');
        }

        $opname->update([
            'validation_status' => 'rejected',
            'validator_id' => $validator->id,
            'validated_at' => now(),
            'rejection_note' => $rejectionNote,
            'status' => 'completed',
        ]);

        return $opname->fresh(['stockOpnameItems.item', 'location', 'createdBy', 'validator']);
    }

    private function applyOpnameItemAdjustments(
        StockOpnameTransaction $opname,
        StockOpnameItem $opnameItem,
        User $validator,
    ): void {
        $locationId = $opname->location_id;
        $itemId = $opnameItem->item_id;

        if ($opnameItem->available_difference_qty > 0) {
            $qty = $opnameItem->available_difference_qty;

            $this->stockBalanceService->increase(
                $itemId,
                $locationId,
                StockStatus::AVAILABLE,
                $qty,
            );

            $this->stockMovementService->createForOpnameAdjustment(
                $opname,
                $opnameItem,
                MovementType::STOCK_OPNAME_PLUS,
                $qty,
                $validator,
            );
        }

        if ($opnameItem->damaged_qty > 0) {
            $this->stockBalanceService->decrease(
                $itemId,
                $locationId,
                StockStatus::AVAILABLE,
                $opnameItem->damaged_qty,
            );

            $this->stockBalanceService->increase(
                $itemId,
                $locationId,
                StockStatus::DAMAGED,
                $opnameItem->damaged_qty,
            );

            $this->stockMovementService->createForOpnameAdjustment(
                $opname,
                $opnameItem,
                MovementType::AVAILABLE_TO_DAMAGED,
                $opnameItem->damaged_qty,
                $validator,
            );
        }

        if ($opnameItem->available_difference_qty < 0) {
            $shortageQty = $opnameItem->damaged_qty > 0
                ? $opnameItem->lost_qty
                : abs($opnameItem->available_difference_qty);

            if ($shortageQty > 0) {
                $this->stockBalanceService->decrease(
                    $itemId,
                    $locationId,
                    StockStatus::AVAILABLE,
                    $shortageQty,
                );

                $this->stockMovementService->createForOpnameAdjustment(
                    $opname,
                    $opnameItem,
                    MovementType::STOCK_OPNAME_LOST,
                    $shortageQty,
                    $validator,
                );
            }
        }

        if ($opnameItem->lost_qty > 0) {
            $this->stockBalanceService->increase(
                $itemId,
                $locationId,
                StockStatus::LOST,
                $opnameItem->lost_qty,
            );
        }
    }

    private function assertCanValidateOrReject(User $validator): void
    {
        if (! in_array($validator->role, [UserRole::OWNER, UserRole::ADMIN], true)) {
            throw new AuthorizationException('Hanya Owner atau Admin yang dapat memvalidasi opname.');
        }
    }

    private function isUniqueOpnameNumberViolation(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'unique')
            || str_contains($message, 'duplicate');
    }
}
