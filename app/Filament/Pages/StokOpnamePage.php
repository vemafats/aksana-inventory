<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class StokOpnamePage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Stok Opname';

    protected static ?int $navigationSort = 7;

    protected static ?string $title = 'Stok Opname';

    protected static ?string $slug = 'stok-opname';

    protected static string $view = 'filament.pages.stok-opname';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->role->canStockOpname();
    }
}
