<?php

namespace App\Filament\Resources;

use App\Enums\StockStatus;
use App\Filament\Pages\PrintQrCodesPage;
use App\Filament\Resources\CatalogResource\Pages;
use App\Models\Color;
use App\Models\Item;
use App\Models\ProductModel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontFamily;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;

class CatalogResource extends Resource
{
    protected static ?string $model = Item::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'Katalog';

    protected static ?string $modelLabel = 'Item Katalog';

    protected static ?string $pluralModelLabel = 'Katalog';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Wizard::make([
                    Forms\Components\Wizard\Step::make('Identitas Item')
                        ->schema(static::identitySchema()),
                    Forms\Components\Wizard\Step::make('Foto Katalog')
                        ->schema(static::photoSchema()),
                ])
                    ->columnSpanFull()
                    ->skippable(false),
            ]);
    }

    /**
     * @return list<Forms\Components\Component>
     */
    public static function identitySchema(): array
    {
        return [
            Forms\Components\Select::make('category_id')
                ->label('Kategori')
                ->relationship('category', 'name', fn (Builder $query) => $query->where('is_active', true))
                ->searchable()
                ->preload()
                ->required()
                ->live()
                ->disabledOn('edit'),
            Forms\Components\Select::make('brand_id')
                ->label('Merk')
                ->relationship('brand', 'name', fn (Builder $query) => $query->where('is_active', true))
                ->searchable()
                ->preload()
                ->required()
                ->live()
                ->disabledOn('edit'),
            Forms\Components\Select::make('model_id')
                ->label('Model')
                ->options(fn (Get $get): array => ProductModel::query()
                    ->where('brand_id', $get('brand_id'))
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->searchable()
                ->required()
                ->disabled(fn (Get $get): bool => blank($get('brand_id')))
                ->live()
                ->disabledOn('edit'),
            Forms\Components\Select::make('color_id')
                ->label('Warna')
                ->options(
                    Color::query()
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (Color $color): array => [
                            $color->id => $color->name,
                        ])
                        ->all()
                )
                ->searchable()
                ->required()
                ->live()
                ->hint(fn (Get $get): ?string => ($color = Color::query()->find($get('color_id')))
                    ? 'Kode warna: '.($color->code ?? '—')
                    : null)
                ->disabledOn('edit'),
            Forms\Components\Select::make('size_id')
                ->label('Ukuran')
                ->relationship('size', 'name', fn (Builder $query) => $query->where('is_active', true))
                ->searchable()
                ->preload()
                ->required()
                ->disabledOn('edit'),
            Forms\Components\TextInput::make('item_name')
                ->label('Nama Item')
                ->placeholder('Auto-generate dari atribut')
                ->maxLength(255)
                ->helperText('Kosongkan untuk auto-generate'),
            Forms\Components\Textarea::make('description')
                ->label('Deskripsi')
                ->rows(3)
                ->columnSpanFull(),
            Forms\Components\Toggle::make('is_active')
                ->label('Aktif')
                ->default(true),
        ];
    }

    /**
     * @return list<Forms\Components\Component>
     */
    public static function photoSchema(): array
    {
        return [
            Forms\Components\FileUpload::make('catalog_photo_path')
                ->label('Foto Katalog')
                ->disk('public')
                ->directory('catalog-photos')
                ->image()
                ->imagePreviewHeight('100')
                ->acceptedFileTypes(['image/jpeg', 'image/png'])
                ->maxSize(5120)
                ->imageEditor()
                ->columnSpanFull(),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ViewColumn::make('thumbnail')
                    ->label('')
                    ->view('filament.tables.columns.catalog-thumbnail'),
                Tables\Columns\TextColumn::make('item_name')
                    ->label('Nama Item')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('barcode')
                    ->label('Barcode')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->icon('heroicon-o-qr-code')
                    ->fontFamily(FontFamily::Mono)
                    ->extraAttributes(['class' => 'aksana-mono']),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Kategori')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('brand.name')
                    ->label('Merk')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('available_stock')
                    ->label('Stok Total')
                    ->badge()
                    ->state(fn (Item $record): string => (string) static::availableStockQty($record))
                    ->formatStateUsing(function (string $state, Item $record): string {
                        $qty = (int) $state;

                        return match (true) {
                            $qty === 0 => 'Habis',
                            $qty === 1 => 'Menipis',
                            default => (string) $qty,
                        };
                    })
                    ->color(fn (Item $record): string => match (static::availableStockQty($record)) {
                        0 => 'danger',
                        1 => 'warning',
                        default => 'success',
                    }),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Aktif'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Kategori')
                    ->relationship('category', 'name'),
                Tables\Filters\SelectFilter::make('brand_id')
                    ->label('Merk')
                    ->relationship('brand', 'name'),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Aktif'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('printQrCode')
                    ->label('Cetak QR Code')
                    ->icon('heroicon-o-printer')
                    ->url(fn (Item $record): string => PrintQrCodesPage::printUrl([$record->id]))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('printQrCodes')
                        ->label('Cetak QR Code')
                        ->icon('heroicon-o-printer')
                        ->action(function (Collection $records): RedirectResponse {
                            return redirect()->to(PrintQrCodesPage::printUrl($records->pluck('id')->all()));
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('item_name')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'category',
                'brand',
                'color',
                'stockBalances',
            ]));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCatalogs::route('/'),
            'create' => Pages\CreateCatalog::route('/create'),
            'edit' => Pages\EditCatalog::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['item_name', 'sku', 'barcode'];
    }

    public static function availableStockQty(Item $record): int
    {
        return (int) $record->stockBalances
            ->where('stock_status', StockStatus::AVAILABLE)
            ->sum('qty');
    }
}
