<?php

namespace App\Services;

use App\Enums\LocationStatus;
use App\Enums\LocationType;
use App\Enums\StockStatus;
use App\Enums\UserRole;
use App\Models\Item;
use App\Models\Location;
use App\Models\SalesItem;
use App\Models\SalesTransaction;
use App\Models\StockBalance;
use App\Models\User;
use App\Support\StockReportCache;
use App\Support\TimezoneQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
        $cacheKey = StockReportCache::dashboardCacheKey($user->id, $user->role);

        return Cache::remember(
            $cacheKey,
            now()->addMinutes(5),
            function () use ($user): array {
                $locationIds = $this->resolveAccessibleLocationIds($user);

                $stockQuery = StockBalance::query();
                $this->applyLocationFilter($stockQuery, $locationIds);

                $totalUnitStock = (int) (clone $stockQuery)->sum('qty');

                $activeLocationsQuery = Location::query()
                    ->where('status', LocationStatus::ACTIVE->value);
                $this->applyLocationFilter($activeLocationsQuery, $locationIds, 'id');

                $lowStockCount = $this->countLowStockItems($locationIds);

                $salesQuery = SalesTransaction::query();
                TimezoneQuery::whereTimestampEquals($salesQuery, 'transaction_date', TimezoneQuery::dateForDaysAgo());
                $this->applyLocationFilter($salesQuery, $locationIds);

                return [
                    'total_sku' => Item::query()->where('is_active', true)->count(),
                    'total_unit_stock' => $totalUnitStock,
                    'active_locations' => $activeLocationsQuery->count(),
                    'low_stock_count' => $lowStockCount,
                    'todays_sales' => (float) (clone $salesQuery)->sum('grand_total'),
                    'todays_transactions' => (clone $salesQuery)->count(),
                ];
            },
        );
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

    public function lowStockBalancesQuery(User $user): Builder
    {
        $query = StockBalance::query()
            ->where('stock_status', StockStatus::AVAILABLE->value)
            ->where('qty', '<=', 1)
            ->with(['item', 'location']);

        $this->applyLocationFilter($query, $this->resolveAccessibleLocationIds($user));

        return $query;
    }

    public function lowStockItems(User $user): Collection
    {
        $cacheKey = StockReportCache::lowStockCacheKey($user->role);

        return Cache::remember(
            $cacheKey,
            now()->addMinutes(2),
            function () use ($user): Collection {
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
                            ->select(['id', 'item_id', 'location_id', 'stock_status', 'qty'])
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
            },
        );
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
     * @return array{
     *   total_sales: float,
     *   total_cogs: float,
     *   gross_profit: float,
     *   gross_margin_pct: float,
     *   transaction_count: int,
     *   period: array{from: string, to: string}
     * }
     */
    public function grossProfit(User $user, array $filters = []): array
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($filters);

        $transactionQuery = $this->salesTransactionQuery($filters, $user);
        TimezoneQuery::whereTimestampFrom($transactionQuery, 'transaction_date', $dateFrom);
        TimezoneQuery::whereTimestampTo($transactionQuery, 'transaction_date', $dateTo);

        $totalSales = (float) (clone $transactionQuery)->sum('grand_total');
        $transactionCount = (clone $transactionQuery)->count();

        $cogsQuery = SalesItem::query()
            ->join('sales_transactions', 'sales_items.sales_transaction_id', '=', 'sales_transactions.id');

        $this->applySalesTransactionFilters($cogsQuery, $filters, $user, 'sales_transactions');
        TimezoneQuery::whereTimestampFrom($cogsQuery, 'sales_transactions.transaction_date', $dateFrom);
        TimezoneQuery::whereTimestampTo($cogsQuery, 'sales_transactions.transaction_date', $dateTo);

        $totalCogs = (float) $cogsQuery->sum(DB::raw('sales_items.supplier_cost_snapshot * sales_items.qty'));

        $grossProfit = $totalSales - $totalCogs;
        $grossMarginPct = $totalSales > 0
            ? round(($grossProfit / $totalSales) * 100, 2)
            : 0.0;

        return [
            'total_sales' => round($totalSales, 2),
            'total_cogs' => round($totalCogs, 2),
            'gross_profit' => round($grossProfit, 2),
            'gross_margin_pct' => $grossMarginPct,
            'transaction_count' => $transactionCount,
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function bestSellingProducts(User $user, array $filters = []): Collection
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($filters);
        $limit = max(1, (int) ($filters['limit'] ?? 10));

        $query = SalesItem::query()
            ->join('sales_transactions', 'sales_items.sales_transaction_id', '=', 'sales_transactions.id')
            ->join('items', 'sales_items.item_id', '=', 'items.id')
            ->select(
                'items.id as item_id',
                'items.item_name',
                'items.sku',
                'items.barcode',
                DB::raw('SUM(sales_items.qty) as total_qty_sold'),
                DB::raw('SUM(sales_items.total_after_discount) as total_revenue'),
            )
            ->groupBy('items.id', 'items.item_name', 'items.sku', 'items.barcode')
            ->orderByDesc('total_qty_sold');

        TimezoneQuery::whereTimestampFrom($query, 'sales_transactions.transaction_date', $dateFrom);
        TimezoneQuery::whereTimestampTo($query, 'sales_transactions.transaction_date', $dateTo);

        $this->applySalesTransactionFilters($query, $filters, $user, 'sales_transactions');

        $rows = $query->limit($limit)->get();
        $totalRevenue = (float) $rows->sum('total_revenue');

        return $rows->values()->map(function ($row, int $index) use ($totalRevenue) {
            $revenue = (float) $row->total_revenue;

            return [
                'rank' => $index + 1,
                'item_id' => $row->item_id,
                'item_name' => $row->item_name,
                'sku' => $row->sku,
                'barcode' => $row->barcode,
                'total_qty_sold' => (int) $row->total_qty_sold,
                'total_revenue' => round($revenue, 2),
                'volume_pct' => $totalRevenue > 0
                    ? round(($revenue / $totalRevenue) * 100, 2)
                    : 0.0,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function salesByLocation(User $user, array $filters = []): Collection
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($filters);

        $query = SalesTransaction::query()
            ->join('locations', 'sales_transactions.location_id', '=', 'locations.id')
            ->select(
                'locations.id as location_id',
                'locations.location_name',
                'locations.location_type',
                DB::raw('SUM(sales_transactions.grand_total) as total_sales'),
                DB::raw('COUNT(sales_transactions.id) as transaction_count'),
            )
            ->groupBy('locations.id', 'locations.location_name', 'locations.location_type')
            ->orderByDesc('total_sales');

        TimezoneQuery::whereTimestampFrom($query, 'sales_transactions.transaction_date', $dateFrom);
        TimezoneQuery::whereTimestampTo($query, 'sales_transactions.transaction_date', $dateTo);

        $this->applySalesTransactionFilters($query, $filters, $user);

        $rows = $query->get();
        $grandTotal = (float) $rows->sum('total_sales');

        return $rows->map(function ($row) use ($dateFrom, $dateTo, $grandTotal) {
            $itemsSoldQuery = SalesItem::query()
                ->join('sales_transactions', 'sales_items.sales_transaction_id', '=', 'sales_transactions.id')
                ->where('sales_transactions.location_id', $row->location_id);
            TimezoneQuery::whereTimestampFrom($itemsSoldQuery, 'sales_transactions.transaction_date', $dateFrom);
            TimezoneQuery::whereTimestampTo($itemsSoldQuery, 'sales_transactions.transaction_date', $dateTo);
            $itemsSold = (int) $itemsSoldQuery->sum('sales_items.qty');

            $totalSales = (float) $row->total_sales;

            return [
                'location_id' => $row->location_id,
                'location_name' => $row->location_name,
                'location_type' => $row->location_type instanceof LocationType
                    ? $row->location_type->value
                    : (string) $row->location_type,
                'total_sales' => round($totalSales, 2),
                'transaction_count' => (int) $row->transaction_count,
                'items_sold' => $itemsSold,
                'sales_pct' => $grandTotal > 0
                    ? round(($totalSales / $grandTotal) * 100, 2)
                    : 0.0,
            ];
        })->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function salesByEmployee(User $user, array $filters = []): Collection
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($filters);

        $query = SalesTransaction::query()
            ->join('users', 'sales_transactions.user_id', '=', 'users.id')
            ->select(
                'users.id as employee_id',
                'users.name as employee_name',
                DB::raw('SUM(sales_transactions.grand_total) as total_sales'),
                DB::raw('COUNT(sales_transactions.id) as transaction_count'),
            )
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_sales');

        TimezoneQuery::whereTimestampFrom($query, 'sales_transactions.transaction_date', $dateFrom);
        TimezoneQuery::whereTimestampTo($query, 'sales_transactions.transaction_date', $dateTo);

        $this->applySalesTransactionFilters($query, $filters, $user);

        return $query->get()->map(function ($row) {
            $totalSales = (float) $row->total_sales;
            $transactionCount = (int) $row->transaction_count;

            return [
                'employee_id' => $row->employee_id,
                'employee_name' => $row->employee_name,
                'total_sales' => round($totalSales, 2),
                'transaction_count' => $transactionCount,
                'avg_basket' => $transactionCount > 0
                    ? round($totalSales / $transactionCount, 2)
                    : 0.0,
            ];
        })->values();
    }

    /**
     * @return array{
     *   todays_net_sales: float,
     *   todays_transactions: int,
     *   items_sold_today: int,
     *   avg_basket_today: float,
     *   vs_yesterday_pct: float,
     *   seven_day_trend: list<array{date: string, total_sales: float}>,
     *   top_sku_today: array{sku: string, item_name: string, qty_sold: int}|null,
     *   low_stock_count: int
     * }
     */
    public function mobileSummary(User $user): array
    {
        $locationIds = $this->resolveAccessibleLocationIds($user);
        $today = TimezoneQuery::dateForDaysAgo();
        $yesterday = TimezoneQuery::dateForDaysAgo(1);

        $todayQuery = SalesTransaction::query();
        TimezoneQuery::whereTimestampEquals($todayQuery, 'transaction_date', $today);
        $this->applyLocationFilter($todayQuery, $locationIds);

        $todaysNetSales = (float) (clone $todayQuery)->sum('grand_total');
        $todaysTransactions = (clone $todayQuery)->count();

        $itemsSoldToday = (int) SalesItem::query()
            ->whereHas('salesTransaction', function (Builder $query) use ($today, $locationIds): void {
                TimezoneQuery::whereTimestampEquals($query, 'transaction_date', $today);
                $this->applyLocationFilter($query, $locationIds);
            })
            ->sum('qty');

        $avgBasketToday = $todaysTransactions > 0
            ? round($todaysNetSales / $todaysTransactions, 2)
            : 0.0;

        $yesterdayQuery = SalesTransaction::query();
        TimezoneQuery::whereTimestampEquals($yesterdayQuery, 'transaction_date', $yesterday);
        $this->applyLocationFilter($yesterdayQuery, $locationIds);
        $yesterdaySales = (float) $yesterdayQuery->sum('grand_total');

        $vsYesterdayPct = $yesterdaySales > 0
            ? round((($todaysNetSales - $yesterdaySales) / $yesterdaySales) * 100, 1)
            : 0.0;

        $sevenDayTrend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = TimezoneQuery::dateForDaysAgo($i);
            $dayQuery = SalesTransaction::query();
            TimezoneQuery::whereTimestampEquals($dayQuery, 'transaction_date', $date);
            $this->applyLocationFilter($dayQuery, $locationIds);
            $sevenDayTrend[] = [
                'date' => $date,
                'total_sales' => (float) $dayQuery->sum('grand_total'),
            ];
        }

        $topSkuQuery = SalesItem::query()
            ->select(
                'items.sku',
                'items.item_name',
                DB::raw('SUM(sales_items.qty) as qty_sold'),
            )
            ->join('items', 'sales_items.item_id', '=', 'items.id')
            ->join('sales_transactions', 'sales_items.sales_transaction_id', '=', 'sales_transactions.id')
            ->groupBy('items.sku', 'items.item_name')
            ->orderByDesc('qty_sold');

        TimezoneQuery::whereTimestampEquals($topSkuQuery, 'sales_transactions.transaction_date', $today);

        $this->applyLocationFilter($topSkuQuery, $locationIds, 'sales_transactions.location_id');

        $topSkuRow = $topSkuQuery->first();

        return [
            'todays_net_sales' => round($todaysNetSales, 2),
            'todays_transactions' => $todaysTransactions,
            'items_sold_today' => $itemsSoldToday,
            'avg_basket_today' => $avgBasketToday,
            'vs_yesterday_pct' => $vsYesterdayPct,
            'seven_day_trend' => $sevenDayTrend,
            'top_sku_today' => $topSkuRow ? [
                'sku' => $topSkuRow->sku,
                'item_name' => $topSkuRow->item_name,
                'qty_sold' => (int) $topSkuRow->qty_sold,
            ] : null,
            'low_stock_count' => $this->countLowStockItems($locationIds),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: string, 1: string}
     */
    private function resolveDateRange(array $filters): array
    {
        $dateFrom = ! empty($filters['date_from'])
            ? (string) $filters['date_from']
            : now(TimezoneQuery::TIMEZONE)->startOfMonth()->toDateString();

        $dateTo = ! empty($filters['date_to'])
            ? (string) $filters['date_to']
            : TimezoneQuery::dateForDaysAgo();

        return [$dateFrom, $dateTo];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function salesTransactionQuery(array $filters, User $user): Builder
    {
        $query = SalesTransaction::query();
        $this->applySalesTransactionFilters($query, $filters, $user);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applySalesTransactionFilters(
        Builder $query,
        array $filters,
        User $user,
        string $tablePrefix = 'sales_transactions',
    ): void {
        $locationIds = $this->resolveAccessibleLocationIds($user);

        if (! empty($filters['location_id'])) {
            $locationId = (string) $filters['location_id'];

            if ($locationIds !== null && ! in_array($locationId, $locationIds, true)) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->where("{$tablePrefix}.location_id", $locationId);
        } else {
            $this->applyLocationFilter($query, $locationIds, "{$tablePrefix}.location_id");
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function buildGroupedStockCollection(string $locationId, array $filters): Collection
    {
        $query = StockBalance::query()
            ->select(['id', 'item_id', 'location_id', 'stock_status', 'qty'])
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
                'item' => fn ($query) => $query->select([
                    'id',
                    'category_id',
                    'brand_id',
                    'model_id',
                    'color_id',
                    'size_id',
                    'sku',
                    'barcode',
                    'item_name',
                    'is_active',
                ]),
                'item.category:id,name,code',
                'item.brand:id,name',
                'item.color:id,name,code',
                'item.size:id,name,size_type',
                'location:id,location_name,location_code,location_type',
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
        return $user->locationAssignments()
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
        return $this->lowStockItemIdsQuery($locationIds)->get()->count();
    }

    /**
     * @param  list<string>|null  $locationIds
     */
    private function lowStockItemIdsQuery(?array $locationIds): Builder
    {
        $query = StockBalance::query()
            ->where('stock_status', StockStatus::AVAILABLE->value);

        $this->applyLocationFilter($query, $locationIds);

        return $query
            ->selectRaw('item_id, SUM(qty) as total_qty')
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
