<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Helpers\FormatHelper;
use App\Models\Item;
use App\Models\Location;
use App\Services\ReportService;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class LaporanLengkapPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Laporan Lengkap';

    protected static ?string $navigationGroup = 'Analytics';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Laporan Lengkap';

    protected static ?string $slug = 'laporan-lengkap';

    protected static string $view = 'filament.pages.laporan-lengkap';

    public string $activeReport = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $selectedLocationId = '';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->role->canViewFullReport();
    }

    public function mount(): void
    {
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    public function openReport(string $type): void
    {
        if ($type === 'profit' && ! $this->isOwner()) {
            $this->activeReport = 'profit';

            return;
        }

        if (! in_array($type, ['stok', 'penjualan', 'profit'], true)) {
            return;
        }

        $this->activeReport = $type;
    }

    public function closeReport(): void
    {
        $this->activeReport = '';
    }

    public function isOwner(): bool
    {
        return auth()->user()?->role === UserRole::OWNER;
    }

    public static function formatRupiah(float $amount): string
    {
        return FormatHelper::price($amount);
    }

    public function locationShortLabel(Location $location): string
    {
        $parts = preg_split('/\s+/', trim($location->location_name)) ?: [];

        return strtoupper($parts[0] ?? $location->location_name);
    }

    public function itemLocationQty(Item $item, string $locationId): int
    {
        return (int) $item->stockBalances
            ->where('location_id', $locationId)
            ->where('stock_status', 'available')
            ->sum('qty');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $data = [];

        if ($this->activeReport === 'stok') {
            $data = array_merge($data, $this->stockReportData());
        }

        if ($this->activeReport === 'penjualan') {
            $data = array_merge($data, $this->salesReportData());
        }

        if ($this->activeReport === 'profit' && $this->isOwner()) {
            $data = array_merge($data, $this->profitReportData());
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function stockReportData(): array
    {
        $allStockItems = Item::query()
            ->where('is_active', true)
            ->with(['stockBalances.location'])
            ->withSum(['stockBalances as total_available' => fn ($q) => $q->where('stock_status', 'available')], 'qty')
            ->withSum(['stockBalances as total_damaged' => fn ($q) => $q->where('stock_status', 'damaged')], 'qty')
            ->orderBy('item_name')
            ->get();

        $allLocations = Location::query()
            ->where('status', 'active')
            ->orderBy('location_type')
            ->orderBy('location_name')
            ->get();

        return [
            'allStockItems' => $allStockItems,
            'allLocations' => $allLocations,
            'stockTotalUnits' => (int) $allStockItems->sum('total_available'),
            'stockTotalDamaged' => (int) $allStockItems->sum('total_damaged'),
            'stockLowCount' => $allStockItems->filter(
                fn (Item $item): bool => (int) ($item->total_available ?? 0) <= 1
            )->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function salesReportData(): array
    {
        $user = auth()->user();
        $salesByLoc = $user !== null
            ? app(ReportService::class)->salesByLocation($user, [
                'date_from' => $this->dateFrom,
                'date_to' => $this->dateTo,
            ])
            : collect();

        return [
            'salesByLoc' => $salesByLoc,
            'salesTotalSales' => (float) $salesByLoc->sum('total_sales'),
            'salesTotalTrx' => (int) $salesByLoc->sum('transaction_count'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function profitReportData(): array
    {
        $user = auth()->user();
        $filters = [
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
        ];

        $reportService = app(ReportService::class);

        return [
            'profitData' => $user !== null ? $reportService->grossProfit($user, $filters) : [],
            'bestSelling' => $user !== null
                ? $reportService->bestSellingProducts($user, array_merge($filters, ['limit' => 10]))
                : Collection::make(),
        ];
    }
}
