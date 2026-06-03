<?php

namespace App\Filament\Resources;

use App\Enums\MovementType;
use App\Filament\Resources\StockMovementResource\Pages;
use App\Models\Item;
use App\Models\StockMovement;
use App\Support\TimezoneQuery;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontFamily;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationLabel = 'Riwayat Pergerakan';

    protected static ?string $navigationGroup = 'Analytics';

    protected static ?string $modelLabel = 'Pergerakan Stok';

    protected static ?string $pluralModelLabel = 'Riwayat Pergerakan';

    protected static ?int $navigationSort = 3;

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
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('movement_number')
                    ->label('No. Mutasi')
                    ->searchable()
                    ->copyable()
                    ->fontFamily(FontFamily::Mono)
                    ->extraAttributes(['class' => 'aksana-mono']),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y · H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('movement_type')
                    ->label('Tipe')
                    ->badge()
                    ->formatStateUsing(fn (MovementType $state): string => $state->filamentBadgeLabel())
                    ->color(fn (MovementType $state): string => $state->filamentBadgeColor()),
                Tables\Columns\TextColumn::make('item.item_name')
                    ->label('Item')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('route')
                    ->label('Dari → Ke')
                    ->state(function (StockMovement $record): string {
                        $from = $record->fromLocation?->location_name ?? '—';
                        $to = $record->toLocation?->location_name ?? '—';

                        return "{$from} → {$to}";
                    })
                    ->icon('heroicon-m-arrows-right-left')
                    ->wrap(),
                Tables\Columns\TextColumn::make('qty')
                    ->label('Qty')
                    ->formatStateUsing(fn (int $state, StockMovement $record): string => $record->movement_type->qtyDisplayPrefix().$state)
                    ->fontFamily(FontFamily::Mono)
                    ->weight('bold')
                    ->color(fn (StockMovement $record): string => match ($record->movement_type->qtyDisplayPrefix()) {
                        '+' => 'success',
                        '-' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('reference')
                    ->label('Referensi')
                    ->state(fn (StockMovement $record): string => strtoupper($record->reference_type).' · '.substr($record->reference_id, 0, 8))
                    ->fontFamily(FontFamily::Mono)
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('movement_type')
                    ->label('Tipe')
                    ->options(collect(MovementType::cases())->mapWithKeys(
                        fn (MovementType $type) => [$type->value => $type->filamentBadgeLabel()]
                    )->all()),
                Tables\Filters\SelectFilter::make('item_id')
                    ->label('Item')
                    ->options(fn (): array => Item::query()
                        ->where('is_active', true)
                        ->orderBy('item_name')
                        ->pluck('item_name', 'id')
                        ->all())
                    ->searchable(),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        DatePicker::make('from')
                            ->label('Dari'),
                        DatePicker::make('until')
                            ->label('Sampai'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['from'] ?? null),
                                fn (Builder $q) => TimezoneQuery::whereDateFrom($q, 'created_at', $data['from']),
                            )
                            ->when(
                                filled($data['until'] ?? null),
                                fn (Builder $q) => TimezoneQuery::whereDateTo($q, 'created_at', $data['until']),
                            );
                    }),
            ])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'item',
                'fromLocation',
                'toLocation',
            ]));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockMovements::route('/'),
        ];
    }
}
