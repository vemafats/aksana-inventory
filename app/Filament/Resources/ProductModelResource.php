<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductModelResource\Pages;
use App\Models\ProductModel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductModelResource extends Resource
{
    protected static ?string $model = ProductModel::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'Model Produk';

    protected static ?string $pluralModelLabel = 'Model Produk';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('category_id')
                    ->label('Kategori')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Select::make('brand_id')
                    ->label('Merk')
                    ->relationship('brand', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\TextInput::make('name')
                    ->label('Nama Model')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('code')
                    ->label('Kode')
                    ->nullable()
                    ->maxLength(50)
                    ->helperText('Diisi manual oleh user'),
                Forms\Components\Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Kategori')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('brand.name')
                    ->label('Merk')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Model')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->fontFamily(\Filament\Support\Enums\FontFamily::Mono),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
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
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductModels::route('/'),
            'create' => Pages\CreateProductModel::route('/create'),
            'edit' => Pages\EditProductModel::route('/{record}/edit'),
        ];
    }
}
