<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class StokPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationLabel = 'Stok';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Stok';

    protected static ?string $slug = 'stok';

    protected static string $view = 'filament.pages.stok';

    public string $activeTab = 'ringkasan';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->role->canManageCatalog();
    }

    public function selectTab(string $tab): void
    {
        if (! in_array($tab, ['ringkasan', 'tambah-stok', 'riwayat-pergerakan', 'harga-jual'], true)) {
            return;
        }

        $this->activeTab = $tab;
    }

    public function getActiveTabLabel(): string
    {
        return match ($this->activeTab) {
            'ringkasan' => 'Ringkasan',
            'tambah-stok' => 'Tambah Stok',
            'riwayat-pergerakan' => 'Riwayat Pergerakan',
            'harga-jual' => 'Harga Jual',
            default => 'Stok',
        };
    }
}
