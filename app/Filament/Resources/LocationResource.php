<?php

namespace App\Filament\Resources;

use App\Enums\LocationStatus;
use App\Enums\LocationType;
use App\Filament\Resources\LocationResource\Pages;
use App\Models\Location;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LocationResource extends Resource
{
    protected static ?string $model = Location::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'Lokasi';

    protected static ?string $pluralModelLabel = 'Lokasi';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('location_code')
                    ->label('Kode Lokasi')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('location_name')
                    ->label('Nama Lokasi')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('location_type')
                    ->label('Tipe Lokasi')
                    ->options(self::locationTypeOptions())
                    ->required(),
                Forms\Components\Textarea::make('address')
                    ->label('Alamat')
                    ->columnSpanFull(),
                Forms\Components\DatePicker::make('start_date')
                    ->label('Tanggal Mulai'),
                Forms\Components\DatePicker::make('end_date')
                    ->label('Tanggal Selesai'),
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options(self::locationStatusOptions())
                    ->default(LocationStatus::DRAFT->value)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('location_code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('location_name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('location_type')
                    ->label('Tipe')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof LocationType
                        ? $state->label()
                        : (LocationType::tryFrom((string) $state)?->label() ?? (string) $state)),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof LocationStatus
                        ? $state->label()
                        : (LocationStatus::tryFrom((string) $state)?->label() ?? (string) $state))
                    ->color(fn ($state): string => match ($state instanceof LocationStatus ? $state->value : (string) $state) {
                        'draft' => 'gray',
                        'active' => 'success',
                        'closed' => 'danger',
                        'cancelled' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Mulai')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->label('Selesai')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('location_type')
                    ->label('Tipe Lokasi')
                    ->options(self::locationTypeOptions()),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(self::locationStatusOptions()),
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

    /**
     * @return array<string, string>
     */
    protected static function locationTypeOptions(): array
    {
        return collect(LocationType::cases())
            ->mapWithKeys(fn (LocationType $type) => [$type->value => $type->label()])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected static function locationStatusOptions(): array
    {
        return collect(LocationStatus::cases())
            ->mapWithKeys(fn (LocationStatus $status) => [$status->value => $status->label()])
            ->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLocations::route('/'),
            'create' => Pages\CreateLocation::route('/create'),
            'edit' => Pages\EditLocation::route('/{record}/edit'),
        ];
    }
}
