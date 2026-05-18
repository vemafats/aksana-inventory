<?php

namespace App\Filament\Widgets;

use App\Services\ReportService;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $summary = app(ReportService::class)->dashboardSummary(auth()->user());
        $lowStockCritical = $summary['low_stock_count'] > 0;

        return [
            Stat::make('Total SKU', number_format($summary['total_sku']))
                ->description('item aktif di katalog')
                ->descriptionIcon('heroicon-m-squares-2x2', IconPosition::Before)
                ->icon('heroicon-m-squares-2x2')
                ->extraAttributes(['class' => 'aksana-stat-card']),
            Stat::make('Total Unit Stok', number_format($summary['total_unit_stock']))
                ->description('seluruh unit di sistem')
                ->descriptionIcon('heroicon-m-archive-box', IconPosition::Before)
                ->icon('heroicon-m-archive-box')
                ->extraAttributes(['class' => 'aksana-stat-card']),
            Stat::make('Omzet Hari Ini', $this->formatRupiah($summary['todays_sales']))
                ->description($summary['todays_transactions'].' transaksi hari ini')
                ->descriptionIcon('heroicon-m-banknotes', IconPosition::Before)
                ->icon('heroicon-m-banknotes')
                ->extraAttributes(['class' => 'aksana-stat-card']),
            Stat::make('Stok Kritis', number_format($summary['low_stock_count']))
                ->description('item dengan stok ≤ 1 unit')
                ->descriptionIcon('heroicon-m-exclamation-triangle', IconPosition::Before)
                ->descriptionColor($lowStockCritical ? 'danger' : 'gray')
                ->icon('heroicon-m-exclamation-triangle')
                ->color($lowStockCritical ? 'danger' : 'gray')
                ->extraAttributes([
                    'class' => $lowStockCritical ? 'aksana-stat-card aksana-stat-danger' : 'aksana-stat-card',
                ]),
        ];
    }

    private function formatRupiah(float $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
