<?php

namespace App\Filament\Pages;

use App\Helpers\FormatHelper;
use App\Services\ReportService;
use Filament\Pages\Page;

class LaporanGrossProfitPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Laporan Gross Profit';

    protected static ?string $slug = 'laporan/gross-profit';

    protected static string $view = 'filament.pages.laporan-gross-profit';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public function mount(): void
    {
        $this->dateFrom = now()->subDays(29)->toDateString();
        $this->dateTo = now()->toDateString();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->role->canViewFullReport();
    }

    /**
     * @return array<string, mixed>
     */
    public function getProfitSummary(): array
    {
        return app(ReportService::class)->grossProfit(auth()->user(), [
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
        ]);
    }

    public static function formatRupiah(float $amount): string
    {
        return FormatHelper::price($amount);
    }
}
