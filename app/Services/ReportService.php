<?php

namespace App\Services;

use App\Enums\LocationStatus;
use App\Enums\LocationType;
use App\Enums\StockStatus;
use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\Item;
use App\Models\Location;
use App\Models\SalesItem;
use App\Models\SalesTransaction;
use App\Models\StockBalance;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportService
{
    /**
     * @return array{
     *   total_sku: int,
     *   total_unit_stock: int,
     *   active_locations: int,
     *   low_stock_count: int,
     *   todays_sales: float,
     *   todays_transactions: int
     * }
     */
    public function dashboardSummary(User $user): array
    {
        $locationIds = $this->resolveAccessibleLocationIds($user);

        $stockQuery = StockBalance::query();
        $this->applyLocationFilter($stockQuery, $locationIds);

        $totalUnitStock = (int) (clone $stockQuery)->sum('qty');

        $activeLocationsQuery = Location::query()
            ->where('status', LocationStatus::ACTIVE->value);
        $this->applyLocationFilter($activeLocationsQuery, $locationIds, 'id');

        $lowStockCount = $this->countLowStockItems($locationIds);

        $salesQuery = SalesTransaction::query()
            ->whereDate('transaction_date', now()->toDateString());
        $this->applyLocationFilter($salesQuery, $locationIds);

        return [
            'total_sku' => Item::query()->where('is_active', true)->count(),
            'total_unit_stock' => $totalUnitStock,
            'active_locations' => $activeLocationsQuery->count(),
            'low_stock_count' => $lowStockCount,
            'todays_sales' => (float) (clone $salesQuery)->sum('grand_total'),
            'todays_transactions' => (clone $salesQuery)->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function warehouseStock(array $filters = []): Collection
    {
        $warehouse = $this->getCentralWarehouse();

        return $this->buildGroupedStockCollection($warehouse->id, $filters);
    }

    public function locationStock(string $locationId, User $user): Collection
    {
        $this->assertLocationAccessible($locationId, $user);

        return $this->buildGroupedStockCollection($locationId, []);
    }

    /**
     * @return array{total_capital: float, per_location: list<array{location_name: string, qty: int, capital: float}>}
     */
    public function totalCapital(): array
    {
        $balances = StockBalance::query()
            ->where('stock_status', StockStatus::AVAILABLE->value)
            ->where('qty', '>', 0)
            ->with(['item:id,latest_supplier_cost', 'location:id,location_name'])
            ->get();

        $perLocation = [];
        $totalCapital = 0.0;

        foreach ($balances->groupBy('location_id') as $locationBalances) {
            $location = $locationBalances->first()->location;
            $locationQty = 0;
            $locationCapital = 0.0;

            foreach ($locationBalances as $balance) {
                $cost = (float) $balance->item->latest_supplier_cost;
                $lineCapital = $balance->qty * $cost;
                $locationQty += $balance->qty;
                $locationCapital += $lineCapital;
            }

            $perLocation[] = [
                'location_name' => $location->location_name,
                'qty' => $locationQty,
                'capital' => round($locationCapital, 2),
            ];

            $totalCapital += $locationCapital;
        }

        usort($perLocation, fn (array $a, array $b) => strcmp($a['location_name'], $b['location_name']));

        return [
            'total_capital' => round($totalCapital, 2),
            'per_location' => $perLocation,
        ];
    }

    public function lowStockItems(User $user): Collection
    {
        $locationIds = $this->resolveAccessibleLocationIds($user);

        $itemIds = $this->lowStockItemIdsQuery($locationIds)->pluck('item_id');

        if ($itemIds->isEmpty()) {
            return collect();
        }

        return Item::query()
            ->whereIn('id', $itemIds)
            ->orderBy('item_name')
            ->get()
            ->map(function (Item $item) use ($locationIds) {
                $breakdownQuery = StockBalance::query()
                    ->where('item_id', $item->id)
                    ->where('stock_status', StockStatus::AVAILABLE->value)
                    ->where('qty', '>', 0)
                    ->with('location:id,location_name,location_code');

                $this->applyLocationFilter($breakdownQuery, $locationIds);

                $locations = $breakdownQuery->get()->map(fn (StockBalance $balance) => [
                    'location_id' => $balance->location_id,
                    'location_name' => $balance->location->location_name,
                    'qty_available' => $balance->qty,
                ])->values()->all();

                $totalAvailable = (int) collect($locations)->sum('qty_available');

                return [
                    'item_id' => $item->id,
                    'item_name' => $item->item_name,
                    'sku' => $item->sku,
                    'total_available' => $totalAvailable,
                    'locations' => $locations,
                ];
            });
    }

    public function slowMovingItems(int $days = 60): Collection
    {
        $cutoff = now()->subDays($days)->startOfDay();

        return Item::query()
            ->where('is_active', true)
            ->whereHas('stockBalances', function (Builder $query): void {
                $query->where('stock_status', StockStatus::AVAILABLE->value)
                    ->where('qty', '>', 0);
            })
            ->whereDoesntHave('salesItems', function (Builder $query) use ($cutoff): void {
                $query->whereHas('salesTransaction', function (Builder $transactionQuery) use ($cutoff): void {
                    $transactionQuery->where('transaction_date', '>=', $cutoff);
                });
            })
            ->withSum([
                'stockBalances as qty_remaining' => function (Builder $query): void {
                    $query->where('stock_status', StockStatus::AVAILABLE->value);
                },
            ], 'qty')
            ->orderByRaw('(
        SELECT MAX(sales_transactions.transaction_date)
        FROM sales_items
        INNER JOIN sales_transactions ON sales_transactions.id = sales_items.sales_transaction_id
        WHERE sales_items.item_id = items.id
      ) ASC NULLS FIRST')
            ->get()
            ->map(fn (Item $item): array => [
                'item_id' => $item->id,
                'item_name' => $item->item_name,
                'sku' => $item->sku,
                'last_sold_at' => SalesItem::query()
                    ->where('item_id', $item->id)
                    ->join('sales_transactions', 'sales_items.sales_transaction_id', '=', 'sales_transactions.id')
                    ->max('sales_transactions.transaction_date'),
                'qty_remaining' => (int) $item->qty_remaining,
        ])
            ->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function buildGroupedStockCollection(string $locationId, array $filters): Collection
    {
        $query = StockBalance::query()
            ->where('location_id', $locationId)
            ->whereHas('item', function (Builder $itemQuery) use ($filters): void {
                $itemQuery->where('is_active', true);

                if (! empty($filters['category_id'])) {
                    $itemQuery->where('category_id', $filters['category_id']);
                }

                if (! empty($filters['brand_id'])) {
                    $itemQuery->where('brand_id', $filters['brand_id']);
                }

                if (! empty($filters['search'])) {
                    $search = '%'.$filters['search'].'%';
                    $itemQuery->where(function (Builder $q) use ($search): void {
                        $q->where('item_name', 'like', $search)
                            ->orWhere('sku', 'like', $search);
                    });
                }
            })
            ->with([
                'item.category',
                'item.brand',
                'item.color',
                'item.size',
            ]);

        return $query->get()
            ->groupBy('item_id')
            ->map(function (Collection $itemBalances): array {
                $item = $itemBalances->first()->item;

                return [
                    'item_id' => $item->id,
                    'item_name' => $item->item_name,
                    'sku' => $item->sku,
                    'barcode' => $item->barcode,
                    'category' => $item->category?->only(['id', 'name', 'code']),
                    'brand' => $item->brand?->only(['id', 'name']),
                    'color' => $item->color?->only(['id', 'name']),
                    'size' => $item->size?->only(['id', 'name']),
                    'available' => $itemBalances
                        ->where('stock_status', StockStatus::AVAILABLE)
                        ->sum('qty'),
                    'damaged' => $itemBalances
                        ->where('stock_status', StockStatus::DAMAGED)
                        ->sum('qty'),
                    'lost' => $itemBalances
                        ->where('stock_status', StockStatus::LOST)
                        ->sum('qty'),
                ];
            })
            ->sortBy('item_name')
            ->values();
    }

    /**
     * @return list<string>|null null = all locations
     */
    private function resolveAccessibleLocationIds(User $user): ?array
    {
        return match ($user->role) {
            UserRole::OWNER, UserRole::ADMIN => null,
            UserRole::ADMIN_GUDANG => [$this->getCentralWarehouse()->id],
            UserRole::PIC_BAZAR, UserRole::SALES => $this->getAssignedLocationIds($user),
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private function getAssignedLocationIds(User $user): array
    {
        $employee = Employee::query()
            ->where('name', $user->name)
            ->first();

        if (! $employee) {
            return [];
        }

        return $employee->locationAssignments()
            ->where('is_active', true)
            ->pluck('location_id')
            ->all();
    }

    private function getCentralWarehouse(): Location
    {
        return Location::query()
            ->where('location_type', LocationType::CENTRAL_WAREHOUSE->value)
            ->where('status', LocationStatus::ACTIVE->value)
            ->orderBy('created_at')
            ->firstOrFail();
    }

    /**
     * @param  list<string>|null  $locationIds
     */
    private function applyLocationFilter(Builder $query, ?array $locationIds, string $column = 'location_id'): void
    {
        if ($locationIds === null) {
            return;
        }

        if ($locationIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn($column, $locationIds);
    }

    /**
     * @param  list<string>|null  $locationIds
     */
    private function countLowStockItems(?array $locationIds): int
    {
        return $this->lowStockItemIdsQuery($locationIds)->count();
    }

    /**
     * @param  list<string>|null  $locationIds
     */
    private function lowStockItemIdsQuery(?array $locationIds): Builder
    {
        $query = StockBalance::query()
            ->select('item_id')
            ->where('stock_status', StockStatus::AVAILABLE->value);

        $this->applyLocationFilter($query, $locationIds);

        return $query
            ->groupBy('item_id')
            ->havingRaw('SUM(qty) <= 1');
    }

    private function assertLocationAccessible(string $locationId, User $user): void
    {
        $accessible = $this->resolveAccessibleLocationIds($user);

        if ($accessible === null) {
            return;
        }

        if (! in_array($locationId, $accessible, true)) {
            throw new \InvalidArgumentException('Anda tidak memiliki akses ke lokasi ini.');
        }
    }
}
