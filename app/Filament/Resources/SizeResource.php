<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SizeResource\Pages;
use App\Models\Size;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SizeResource extends Resource
{
    protected static ?string $model = Size::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-up-down';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'Ukuran';

    protected static ?string $pluralModelLabel = 'Ukuran';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('code')
                    ->label('Kode')
                    ->nullable()
                    ->maxLength(50)
                    ->helperText('Diisi manual oleh user'),
                Forms\Components\Select::make('category_id')
                    ->label('Kategori')
                    ->relationship('category', 'name', fn ($query) => $query->where('is_active', true))
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Forms\Components\TextInput::make('sort_order')
                    ->label('Urutan')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Forms\Components\Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->fontFamily(\Filament\Support\Enums\FontFamily::Mono),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Kategori')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Urutan')
                    ->sortable(),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Aktif'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Kategori')
                    ->relationship('category', 'name'),
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
            'index' => Pages\ListSizes::route('/'),
            'create' => Pages\CreateSize::route('/create'),
            'edit' => Pages\EditSize::route('/{record}/edit'),
        ];
    }
}
