<?php

namespace App\Filament\Resources;

use App\Enums\LocationType;
use App\Filament\Resources\EventResource\Pages;
use App\Models\Event;
use App\Models\Location;
use App\Models\User;
use App\Services\EventService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'Event';

    protected static ?string $pluralModelLabel = 'Event';

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->role->canManageEvents();
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('location_id')
                    ->label('Lokasi')
                    ->options(
                        Location::query()
                            ->where('location_type', '!=', LocationType::CENTRAL_WAREHOUSE->value)
                            ->orderBy('location_name')
                            ->pluck('location_name', 'id'),
                    )
                    ->searchable()
                    ->required(),
                Forms\Components\TextInput::make('name')
                    ->label('Nama Event')
                    ->required()
                    ->maxLength(255),
                Forms\Components\DatePicker::make('start_date')
                    ->label('Tanggal Mulai')
                    ->default(now())
                    ->required(),
                Forms\Components\DatePicker::make('end_date')
                    ->label('Tanggal Selesai')
                    ->required()
                    ->afterOrEqual('start_date'),
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options(static::statusOptions())
                    ->default('active')
                    ->required(),
                Forms\Components\Textarea::make('notes')
                    ->label('Catatan')
                    ->columnSpanFull(),
                Forms\Components\Repeater::make('assignments')
                    ->label('Petugas')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('User')
                            ->options(
                                User::query()
                                    ->where('is_active', true)
                                    ->orderBy('name')
                                    ->pluck('name', 'id'),
                            )
                            ->searchable()
                            ->required(),
                        Forms\Components\Select::make('role_in_event')
                            ->label('Peran')
                            ->options([
                                'pic_bazar' => 'PIC Bazar',
                                'sales' => 'Sales',
                            ])
                            ->required(),
                    ])
                    ->minItems(1)
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Event')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('location.location_name')
                    ->label('Lokasi')
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Mulai')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->label('Selesai')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => static::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'ended' => 'gray',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('users_count')
                    ->label('Petugas')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(static::statusOptions()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('endEvent')
                    ->label('Akhiri Event')
                    ->icon('heroicon-o-flag')
                    ->requiresConfirmation()
                    ->visible(fn (Event $record): bool => $record->status === 'active')
                    ->action(function (Event $record): void {
                        app(EventService::class)->endEvent($record);

                        Notification::make()
                            ->title('Event diakhiri')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['location'])->withCount('users'));
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            'active' => 'Aktif',
            'ended' => 'Berakhir',
            'cancelled' => 'Dibatalkan',
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEvents::route('/'),
            'create' => Pages\CreateEvent::route('/create'),
            'edit' => Pages\EditEvent::route('/{record}/edit'),
        ];
    }
}
