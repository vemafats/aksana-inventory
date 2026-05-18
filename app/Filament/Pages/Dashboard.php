<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DashboardStatsWidget;
use App\Filament\Widgets\LowStockAlertWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $title = 'Dashboard';

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
}
