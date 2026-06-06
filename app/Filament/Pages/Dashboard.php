<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DashboardStatsWidget;
use App\Filament\Widgets\LowStockAlertWidget;
use App\Models\SalesTransaction;
use App\Models\StockBalance;
use App\Support\TimezoneQuery;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Dashboard';

    protected static string $view = 'filament.pages.dashboard';

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            DashboardStatsWidget::class,
            LowStockAlertWidget::class,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return $this->getChartData();
    }

    /**
     * @return array<string, mixed>
     */
    public function getChartData(): array
    {
        $stockByStatus = StockBalance::query()
            ->selectRaw('stock_status, COALESCE(SUM(qty), 0) as total')
            ->groupBy('stock_status')
            ->pluck('total', 'stock_status');

        $today = TimezoneQuery::todayDateString();
        $salesQuery = SalesTransaction::query();
        TimezoneQuery::whereTimestampEquals($salesQuery, 'transaction_date', $today);

        $salesByPayment = (clone $salesQuery)
            ->selectRaw('payment_method, COALESCE(SUM(grand_total), 0) as total')
            ->groupBy('payment_method')
            ->pluck('total', 'payment_method');

        $stockByLocation = StockBalance::query()
            ->where('stock_status', 'available')
            ->where('qty', '>', 0)
            ->join('locations', 'stock_balances.location_id', '=', 'locations.id')
            ->selectRaw('stock_balances.location_id, locations.location_name, COALESCE(SUM(stock_balances.qty), 0) as total')
            ->groupBy('stock_balances.location_id', 'locations.location_name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'label' => $row->location_name,
                'value' => (int) $row->total,
                'color' => $this->locationColor((string) $row->location_id),
            ])
            ->values()
            ->all();

        return [
            'stockByStatus' => [
                ['label' => 'Available', 'value' => (int) ($stockByStatus['available'] ?? 0), 'color' => '#22c55e'],
                ['label' => 'Damaged', 'value' => (int) ($stockByStatus['damaged'] ?? 0), 'color' => '#f59e0b'],
                ['label' => 'Lost', 'value' => (int) ($stockByStatus['lost'] ?? 0), 'color' => '#ef4444'],
            ],
            'salesByPayment' => [
                ['label' => 'Tunai', 'value' => (float) ($salesByPayment['cash'] ?? 0), 'color' => '#3b82f6'],
                ['label' => 'QRIS', 'value' => (float) ($salesByPayment['qris'] ?? 0), 'color' => '#8b5cf6'],
                ['label' => 'Transfer', 'value' => (float) ($salesByPayment['transfer'] ?? 0), 'color' => '#14b8a6'],
            ],
            'stockByLocation' => $stockByLocation,
        ];
    }

    private function locationColor(string $locationId): string
    {
        $colors = ['#3b82f6', '#8b5cf6', '#14b8a6', '#f59e0b', '#ef4444', '#ec4899', '#6366f1', '#84cc16'];
        $index = abs(crc32($locationId)) % count($colors);

        return $colors[$index];
    }
}
