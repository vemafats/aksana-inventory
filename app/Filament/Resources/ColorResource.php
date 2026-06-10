<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ColorResource\Pages;
use App\Models\Color;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ColorResource extends Resource
{
    protected static ?string $model = Color::class;

    protected static ?string $navigationIcon = 'heroicon-o-swatch';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'Warna';

    protected static ?string $pluralModelLabel = 'Warna';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('item_code')
                    ->label('Kode')
                    ->nullable()
                    ->maxLength(50)
                    ->helperText('Diisi manual oleh user'),
                Forms\Components\ColorPicker::make('code')
                    ->label('Kode Warna'),
                Forms\Components\Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('item_code')
                    ->label('Kode')
                    ->searchable()
                    ->fontFamily(\Filament\Support\Enums\FontFamily::Mono),
                Tables\Columns\TextColumn::make('swatch')
                    ->label('Sampel')
                    ->state(fn (Color $record): string => $record->code ?? '#cccccc')
                    ->formatStateUsing(fn (string $state): string => sprintf(
                        '<div style="width:28px;height:28px;border-radius:4px;background-color:%s;border:1px solid rgba(0,0,0,.15)"></div>',
                        e($state)
                    ))
                    ->html(),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Aktif'),
            ])
            ->filters([
                //
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
            'index' => Pages\ListColors::route('/'),
            'create' => Pages\CreateColor::route('/create'),
            'edit' => Pages\EditColor::route('/{record}/edit'),
        ];
    }
}
