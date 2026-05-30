<?php

namespace App\Filament\Pages;

use App\Helpers\FormatHelper;
use App\Services\ReportService;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class LaporanPenjualanPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Laporan Penjualan';

    protected static ?string $slug = 'laporan/penjualan';

    protected static string $view = 'filament.pages.laporan-penjualan';

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

    public function getSalesRows(): Collection
    {
        return app(ReportService::class)->salesByLocation(auth()->user(), [
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
        ]);
    }

    public static function formatRupiah(float $amount): string
    {
        return FormatHelper::price($amount);
    }
}
