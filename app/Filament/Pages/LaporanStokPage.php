<?php

namespace App\Filament\Pages;

use App\Services\ReportService;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class LaporanStokPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Laporan Stok';

    protected static ?string $slug = 'laporan/stok';

    protected static string $view = 'filament.pages.laporan-stok';

    public string $search = '';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->role->canViewFullReport();
    }

    public function getStockRows(): Collection
    {
        $filters = [];

        if ($this->search !== '') {
            $filters['search'] = $this->search;
        }

        return app(ReportService::class)->warehouseStock($filters);
    }
}
