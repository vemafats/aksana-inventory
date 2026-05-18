<?php

namespace App\Filament\Widgets;

use App\Services\ReportService;
use Filament\Support\Enums\FontFamily;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class LowStockAlertWidget extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Peringatan Stok Menipis';

    public static function canView(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return app(ReportService::class)->dashboardSummary($user)['low_stock_count'] > 0;
    }

    public function getHeading(): string|Htmlable|null
    {
        $count = app(ReportService::class)->dashboardSummary(auth()->user())['low_stock_count'];

        return new HtmlString(
            'Peringatan Stok Menipis <span class="aksana-count-badge">'.$count.'</span>'
        );
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => app(ReportService::class)
                ->lowStockBalancesQuery(auth()->user())
                ->orderBy('qty')
                ->orderBy('item_id'))
            ->columns([
                Tables\Columns\TextColumn::make('item.item_name')
                    ->label('Nama Item')
                    ->searchable()
                    ->weight('semibold'),
                Tables\Columns\TextColumn::make('item.sku')
                    ->label('SKU')
                    ->fontFamily(FontFamily::Mono)
                    ->extraAttributes(['class' => 'aksana-mono']),
                Tables\Columns\TextColumn::make('location.location_name')
                    ->label('Lokasi')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('qty')
                    ->label('Qty Tersedia')
                    ->badge()
                    ->formatStateUsing(fn (int $state): string => $state === 0 ? 'HABIS' : 'MENIPIS')
                    ->color(fn (int $state): string => $state === 0 ? 'danger' : 'warning')
                    ->fontFamily(FontFamily::Mono),
            ])
            ->paginated(false)
            ->defaultPaginationPageOption(10);
    }
}
