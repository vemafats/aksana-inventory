<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class LaporanLengkapPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Laporan Lengkap';

    protected static ?string $navigationGroup = 'Analytics';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Laporan Lengkap';

    protected static ?string $slug = 'laporan-lengkap';

    protected static string $view = 'filament.pages.laporan-lengkap';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->role->canViewFullReport();
    }

    /**
     * @return list<array{title: string, description: string, icon: string, url: string}>
     */
    public function getReportCards(): array
    {
        return [
            [
                'title' => 'Laporan Stok',
                'description' => 'Ringkasan stok gudang pusat per item dan status.',
                'icon' => 'heroicon-o-archive-box',
                'url' => LaporanStokPage::getUrl(),
            ],
            [
                'title' => 'Laporan Penjualan',
                'description' => 'Penjualan per lokasi dengan filter rentang tanggal.',
                'icon' => 'heroicon-o-shopping-bag',
                'url' => LaporanPenjualanPage::getUrl(),
            ],
            [
                'title' => 'Laporan Gross Profit',
                'description' => 'Ringkasan laba kotor dan margin periode terpilih.',
                'icon' => 'heroicon-o-arrow-trending-up',
                'url' => LaporanGrossProfitPage::getUrl(),
            ],
        ];
    }
}
