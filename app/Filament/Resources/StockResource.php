<?php

namespace App\Filament\Resources;

use App\Enums\StockStatus;
use App\Filament\Resources\StockResource\Pages;
use App\Models\StockBalance;
use App\Services\PasswordVerificationService;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontFamily;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StockResource extends Resource
{
    protected static ?string $model = StockBalance::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationLabel = 'Stok';

    protected static ?string $modelLabel = 'Stok';

    protected static ?string $pluralModelLabel = 'Stok';

    protected static bool $shouldRegisterNavigation = false;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        $showCost = static::shouldShowCostColumns();

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('item.item_name')
                    ->label('Nama Item')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('item.sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable()
                    ->fontFamily(FontFamily::Mono)
                    ->extraAttributes(['class' => 'aksana-mono']),
                Tables\Columns\TextColumn::make('location.location_name')
                    ->label('Lokasi')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('stock_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (StockStatus $state): string => $state->label())
                    ->color(fn (StockStatus $state): string => match ($state) {
                        StockStatus::AVAILABLE => 'success',
                        StockStatus::DAMAGED => 'warning',
                        StockStatus::LOST => 'danger',
                    }),
                Tables\Columns\TextColumn::make('qty')
                    ->label('Qty')
                    ->numeric()
                    ->sortable()
                    ->weight('bold')
                    ->fontFamily(FontFamily::Mono)
                    ->color(fn (int $state): string => match (true) {
                        $state === 0 => 'danger',
                        $state === 1 => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('supplier_cost')
                    ->label('Harga Modal')
                    ->money('IDR')
                    ->state(fn (StockBalance $record): float => (float) $record->item->latest_supplier_cost)
                    ->fontFamily(FontFamily::Mono)
                    ->visible($showCost),
                Tables\Columns\TextColumn::make('total_supplier_cost')
                    ->label('Total Modal')
                    ->money('IDR')
                    ->state(fn (StockBalance $record): float => $record->qty * (float) $record->item->latest_supplier_cost)
                    ->fontFamily(FontFamily::Mono)
                    ->visible($showCost),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('location_id')
                    ->label('Lokasi')
                    ->relationship('location', 'location_name'),
                Tables\Filters\SelectFilter::make('stock_status')
                    ->label('Status')
                    ->options(collect(StockStatus::cases())->mapWithKeys(
                        fn (StockStatus $status) => [$status->value => $status->label()]
                    )->all()),
            ])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('item.item_name')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['item', 'location']));
    }

    public static function shouldShowCostColumns(): bool
    {
        if (! request()->boolean('show_cost')) {
            return false;
        }

        $user = auth()->user();

        if (! $user?->isOwner()) {
            return false;
        }

        return app(PasswordVerificationService::class)->validateCostViewToken(
            session('cost_view_token'),
            $user,
        );
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStocks::route('/'),
        ];
    }
}
