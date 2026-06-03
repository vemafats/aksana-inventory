<?php

namespace App\Services;

use App\Enums\LocationStatus;
use App\Enums\LocationType;
use App\Enums\MovementType;
use App\Enums\StockStatus;
use App\Models\Location;
use App\Models\LocationAssignment;
use App\Models\SalesItem;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\TransferItem;
use App\Models\TransferTransaction;
use App\Support\TimezoneQuery;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DistribusiService
{
    public function __construct(
        private readonly StockBalanceService $stockBalanceService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function dashboardStats(): array
    {
        $warehouseId = $this->centralWarehouseId();

        $transferAktif = TransferTransaction::query()
            ->where('status', 'completed')
            ->where('from_location_id', $warehouseId)
            ->where('transfer_date', '>=', TimezoneQuery::dateForDaysAgo(30))
            ->count();

        $itemAktif = (int) TransferItem::query()
            ->whereHas('transferTransaction', function ($query) use ($warehouseId): void {
                $query->where('status', 'completed')
                    ->where('from_location_id', $warehouseId)
                    ->where('transfer_date', '>=', TimezoneQuery::dateForDaysAgo(7));
            })
            ->sum('qty');

        $menungguRetur = Location::query()
            ->where('status', LocationStatus::ACTIVE->value)
            ->where('location_type', '!=', LocationType::CENTRAL_WAREHOUSE->value)
            ->whereNotNull('end_date')

            ->where(
                'end_date',
                '<=',
                now(TimezoneQuery::TIMEZONE)->addDays(3)->toDateString(),
            )
            ->whereHas('stockBalances', function ($query): void {
                $query->where('stock_status', StockStatus::AVAILABLE->value)
                    ->where('qty', '>', 0);
            })
            ->count();

        $returDamaged = StockMovement::query()
            ->where('movement_type', MovementType::AVAILABLE_TO_DAMAGED->value)
            ->where('created_at', '>=', now(TimezoneQuery::TIMEZONE)->subDays(30))
            ->count();

        return [
            'transfer_aktif' => $transferAktif,
            'item_aktif' => $itemAktif,
            'menunggu_retur' => $menungguRetur,
            'retur_damaged' => $returDamaged,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function salesLocationRows(): Collection
    {
        $warehouseId = $this->centralWarehouseId();

        return Location::query()
            ->where('location_type', '!=', LocationType::CENTRAL_WAREHOUSE->value)
            ->orderBy('location_name')
            ->get()
            ->map(function (Location $location) use ($warehouseId): array {
                $dikirim = (int) TransferItem::query()
                    ->whereHas('transferTransaction', function ($query) use ($warehouseId, $location): void {
                        $query->where('from_location_id', $warehouseId)
                            ->where('to_location_id', $location->id)
                            ->where('status', 'completed');
                    })
                    ->sum('qty');

                $sisa = (int) StockBalance::query()
                    ->where('location_id', $location->id)
                    ->where('stock_status', StockStatus::AVAILABLE->value)
                    ->sum('qty');

                $terjual = (int) SalesItem::query()
                    ->whereHas('salesTransaction', fn ($q) => $q->where('location_id', $location->id))
                    ->sum('qty');

                $pic = LocationAssignment::query()
                    ->where('location_id', $location->id)
                    ->where('is_active', true)
                    ->with('user')
                    ->first();

                return [
                    'id' => $location->id,
                    'location_name' => $location->location_name,
                    'location_type' => $location->location_type->value,
                    'location_type_label' => $location->location_type->label(),
                    'pic_name' => $pic?->user?->name ?? '—',
                    'dikirim' => $dikirim,
                    'terjual' => $terjual,
                    'sisa' => $sisa,
                    'status' => $this->locationDisplayStatus($location),
                ];
            });
    }

    /**
     * @return array{label: string, color: string}
     */
    public function locationDisplayStatus(Location $location): array
    {
        if ($location->status === LocationStatus::DRAFT) {
            return ['label' => 'PERSIAPAN', 'color' => 'gray'];
        }

        if ($location->status !== LocationStatus::ACTIVE) {
            return ['label' => strtoupper($location->status->label()), 'color' => 'gray'];
        }

        if ($location->end_date === null) {
            return ['label' => 'PERMANEN', 'color' => 'gray'];
        }

        if ($location->end_date->lte(now()->addDays(3))) {
            return ['label' => 'BERAKHIR 3 HARI', 'color' => 'warning'];
        }

        return ['label' => 'AKTIF', 'color' => 'success'];
    }

    /**
     * @return array{label: string, color: string}
     */
    public function transferDisplayStatus(TransferTransaction $transfer): array
    {
        if ($this->isReturnTransfer($transfer)) {
            return ['label' => 'RETUR', 'color' => 'warning'];
        }

        if ($transfer->status === 'draft') {
            return ['label' => 'DISIAPKAN', 'color' => 'gray'];
        }

        $transfer->loadMissing('fromLocation');

        if ($transfer->fromLocation?->isCentral()
            && $transfer->transfer_date?->gte(now()->subDays(3))) {
            return ['label' => 'AKTIF', 'color' => 'info', 'badge_class' => 'border border-accent/40 bg-accent/15 text-accent'];
        }

        return ['label' => 'DITERIMA', 'color' => 'success'];
    }

    public function isReturnTransfer(TransferTransaction $transfer): bool
    {
        $transfer->loadMissing(['fromLocation', 'toLocation']);

        return $transfer->toLocation?->isCentral()
            && ! $transfer->fromLocation?->isCentral();
    }

    /**
     * @return Collection<int, Location>
     */
    public function returnSourceLocations(): Collection
    {
        return Location::query()
            ->where('status', LocationStatus::ACTIVE->value)
            ->whereIn('location_type', [
                LocationType::BAZAR->value,
                LocationType::OUTLET->value,
                LocationType::STORE->value,
                LocationType::EVENT->value,
            ])
            ->orderBy('location_name')
            ->get();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function returnLinesForLocation(string $locationId): array
    {
        $warehouseId = $this->centralWarehouseId();

        $dikirimByItem = TransferItem::query()
            ->select('item_id', DB::raw('SUM(qty) as total'))
            ->whereHas('transferTransaction', function ($query) use ($warehouseId, $locationId): void {
                $query->where('from_location_id', $warehouseId)
                    ->where('to_location_id', $locationId)
                    ->where('status', 'completed');
            })
            ->groupBy('item_id')
            ->pluck('total', 'item_id');

        $terjualByItem = SalesItem::query()
            ->select('item_id', DB::raw('SUM(qty) as total'))
            ->whereHas('salesTransaction', fn ($q) => $q->where('location_id', $locationId))
            ->groupBy('item_id')
            ->pluck('total', 'item_id');

        $balances = StockBalance::query()
            ->where('location_id', $locationId)
            ->where('stock_status', StockStatus::AVAILABLE->value)
            ->where('qty', '>', 0)
            ->with('item')
            ->get();

        $lines = [];

        foreach ($balances as $balance) {
            $item = $balance->item;
            $kirim = (int) ($dikirimByItem[$item->id] ?? 0);
            $terjual = (int) ($terjualByItem[$item->id] ?? 0);
            $sisa = $balance->qty;

            $lines[] = [
                'item_id' => $item->id,
                'item_name' => $item->item_name,
                'sku' => $item->sku,
                'barcode' => $item->barcode,
                'kirim' => $kirim,
                'terjual' => $terjual,
                'sisa' => $sisa,
                'qty_good' => 0,
                'qty_damaged' => 0,
            ];
        }

        return $lines;
    }

    public function picNameForLocation(?string $locationId): ?string
    {
        if ($locationId === null) {
            return null;
        }

        $assignment = LocationAssignment::query()
            ->where('location_id', $locationId)
            ->where('is_active', true)
            ->with('user')
            ->orderByRaw("CASE WHEN role = 'pic_bazar' THEN 0 ELSE 1 END")
            ->first();

        return $assignment?->user?->name;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array{good: int, damaged: int, kirim: int, terjual: int, diretur: int}
     */
    public function summarizeReturnLines(array $lines): array
    {
        $good = 0;
        $damaged = 0;
        $kirim = 0;
        $terjual = 0;

        foreach ($lines as $line) {
            $good += (int) ($line['qty_good'] ?? 0);
            $damaged += (int) ($line['qty_damaged'] ?? 0);
            $kirim += (int) ($line['kirim'] ?? 0);
            $terjual += (int) ($line['terjual'] ?? 0);
        }

        return [
            'good' => $good,
            'damaged' => $damaged,
            'kirim' => $kirim,
            'terjual' => $terjual,
            'diretur' => $good + $damaged,
        ];
    }

    public function centralWarehouseId(): string
    {
        return Location::query()
            ->where('location_type', LocationType::CENTRAL_WAREHOUSE->value)
            ->where('status', LocationStatus::ACTIVE->value)
            ->orderBy('created_at')
            ->value('id')
            ?? throw new \RuntimeException('Gudang pusat tidak ditemukan.');
    }

    /**
     * @return array{good: int, damaged: int}|null
     */
    public function parseReturnItemNote(?string $note): ?array
    {
        if ($note === null || $note === '') {
            return null;
        }

        try {
            $data = json_decode($note, true, 512, JSON_THROW_ON_ERROR);

            return [
                'good' => (int) ($data['qty_good'] ?? 0),
                'damaged' => (int) ($data['qty_damaged'] ?? 0),
            ];
        } catch (\JsonException) {
            return null;
        }
    }
}
