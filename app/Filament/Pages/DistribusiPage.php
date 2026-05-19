<?php

namespace App\Filament\Pages;

use App\Enums\BazarAdjustType;
use App\Models\Item;
use App\Models\Location;
use App\Models\TransferTransaction;
use App\Services\DistribusiService;
use App\Services\TransferService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\FontFamily;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class DistribusiPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-right-circle';

    protected static ?string $navigationLabel = 'Distribusi';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Distribusi';

    protected static ?string $slug = 'distribusi';

    protected static string $view = 'filament.pages.distribusi';

    public string $activeTab = 'transfer_keluar';

    public ?string $historyDateFrom = null;

    public ?string $historyDateTo = null;

    public ?string $returnLocationId = null;

    public string $returnDate = '';

    public ?string $returnPicName = null;

    public string $returnNote = '';

    /** @var array<int, array<string, mixed>> */
    public array $returnLines = [];

    public string $returnRef = '';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->role->canTransfer();
    }

    public function mount(): void
    {
        $this->historyDateFrom = now()->subDays(6)->toDateString();
        $this->historyDateTo = now()->toDateString();
        $this->returnDate = now()->toDateString();
        $this->returnRef = app(TransferService::class)->generateReturnNumber();
    }

    /**
     * @return array<string, mixed>
     */
    public function getDistribusiStats(): array
    {
        return app(DistribusiService::class)->dashboardStats();
    }

    public function getLocationRows(): Collection
    {
        return app(DistribusiService::class)->salesLocationRows();
    }

    public function getReturnSummary(): array
    {
        return app(DistribusiService::class)->summarizeReturnLines($this->returnLines);
    }

    public function getReturnLocationName(): ?string
    {
        if ($this->returnLocationId === null) {
            return null;
        }

        return Location::query()->find($this->returnLocationId)?->location_name;
    }

    public function updatedReturnLocationId(?string $value): void
    {
        $this->returnPicName = app(DistribusiService::class)->picNameForLocation($value);
        $this->returnLines = $value
            ? app(DistribusiService::class)->returnLinesForLocation($value)
            : [];
    }

    public function saveReturn(): void
    {
        $items = collect($this->returnLines)
            ->filter(fn (array $line): bool => ((int) ($line['qty_good'] ?? 0)) + ((int) ($line['qty_damaged'] ?? 0)) > 0)
            ->map(fn (array $line): array => [
                'item_id' => $line['item_id'],
                'qty_good' => (int) ($line['qty_good'] ?? 0),
                'qty_damaged' => (int) ($line['qty_damaged'] ?? 0),
            ])
            ->values()
            ->all();

        if ($this->returnLocationId === null || $items === []) {
            Notification::make()
                ->title('Lengkapi lokasi dan item retur')
                ->warning()
                ->send();

            return;
        }

        foreach ($this->returnLines as $index => $line) {
            $good = (int) ($line['qty_good'] ?? 0);
            $damaged = (int) ($line['qty_damaged'] ?? 0);
            $sisa = (int) ($line['sisa'] ?? 0);

            if ($good + $damaged > $sisa) {
                Notification::make()
                    ->title('Qty retur melebihi sisa stok')
                    ->body("Item {$line['item_name']}: good + damaged tidak boleh melebihi {$sisa}")
                    ->danger()
                    ->send();

                return;
            }
        }

        try {
            app(TransferService::class)->createReturn([
                'from_location_id' => $this->returnLocationId,
                'transfer_date' => $this->returnDate,
                'note' => $this->returnNote,
                'items' => $items,
            ], auth()->user());
        } catch (InvalidArgumentException $exception) {
            Notification::make()
                ->title('Retur gagal')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Retur berhasil disimpan')
            ->success()
            ->send();

        $this->returnRef = app(TransferService::class)->generateReturnNumber();
        $this->returnNote = '';
        $this->returnLocationId = null;
        $this->returnLines = [];
        $this->returnPicName = null;
        $this->activeTab = 'riwayat';
    }

    public function goToRetur(string $locationId): void
    {
        $this->activeTab = 'retur_masuk';
        $this->returnLocationId = $locationId;
        $this->updatedReturnLocationId($locationId);
    }

    public function resetReturnForm(): void
    {
        $this->returnLocationId = null;
        $this->returnLines = [];
        $this->returnNote = '';
        $this->returnPicName = null;
        $this->returnDate = now()->toDateString();
        $this->returnRef = app(TransferService::class)->generateReturnNumber();
    }

    public function table(Table $table): Table
    {
        $distribusi = app(DistribusiService::class);

        if ($this->activeTab === 'riwayat') {
            return $table
                ->query(fn (): Builder => TransferTransaction::query()
                    ->with(['fromLocation', 'toLocation', 'transferItems'])
                    ->when($this->historyDateFrom, fn (Builder $q) => $q->whereDate('transfer_date', '>=', $this->historyDateFrom))
                    ->when($this->historyDateTo, fn (Builder $q) => $q->whereDate('transfer_date', '<=', $this->historyDateTo))
                    ->latest('transfer_date'))
                ->columns([
                    Tables\Columns\TextColumn::make('transfer_date')
                        ->label('Tanggal')
                        ->dateTime('d M Y H:i')
                        ->sortable(),
                    Tables\Columns\TextColumn::make('transfer_number')
                        ->label('Kode')
                        ->fontFamily(FontFamily::Mono)
                        ->copyable(),
                    Tables\Columns\TextColumn::make('type')
                        ->label('Tipe')
                        ->badge()
                        ->state(function (TransferTransaction $record) use ($distribusi): string {
                            return $distribusi->isReturnTransfer($record) ? 'RETUR' : 'TRANSFER';
                        })
                        ->color(fn (TransferTransaction $record) => $distribusi->isReturnTransfer($record) ? 'warning' : 'info'),
                    Tables\Columns\TextColumn::make('toLocation.location_name')
                        ->label('Lokasi'),
                    Tables\Columns\TextColumn::make('total_qty')
                        ->label('Qty')
                        ->state(fn (TransferTransaction $record): int => (int) $record->transferItems->sum('qty'))
                        ->weight('bold')
                        ->fontFamily(FontFamily::Mono),
                    Tables\Columns\TextColumn::make('detail')
                        ->label('Detail')
                        ->state(function (TransferTransaction $record) use ($distribusi): string {
                            if (! $distribusi->isReturnTransfer($record)) {
                                return '—';
                            }

                            $good = 0;
                            $damaged = 0;

                            foreach ($record->transferItems as $item) {
                                $parsed = $distribusi->parseReturnItemNote($item->note);
                                if ($parsed) {
                                    $good += $parsed['good'];
                                    $damaged += $parsed['damaged'];
                                }
                            }

                            return "Good {$good} · Damaged {$damaged}";
                        })
                        ->color('gray'),
                ])
                ->defaultSort('transfer_date', 'desc')
                ->paginated([10, 25, 50]);
        }

        return $table
            ->query(fn (): Builder => TransferTransaction::query()
                ->with(['fromLocation', 'toLocation', 'transferItems'])
                ->whereHas('fromLocation', fn (Builder $q) => $q->where('location_type', 'central_warehouse'))
                ->latest('transfer_date'))
            ->columns([
                Tables\Columns\TextColumn::make('transfer_number')
                    ->label('Kode')
                    ->searchable()
                    ->copyable()
                    ->weight('bold')
                    ->fontFamily(FontFamily::Mono),
                Tables\Columns\TextColumn::make('transfer_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('fromLocation.location_name')
                    ->label('Asal'),
                Tables\Columns\TextColumn::make('toLocation.location_name')
                    ->label('Tujuan')
                    ->weight('semibold'),
                Tables\Columns\TextColumn::make('total_qty')
                    ->label('Qty')
                    ->state(fn (TransferTransaction $record): int => (int) $record->transferItems->sum('qty'))
                    ->weight('bold')
                    ->fontFamily(FontFamily::Mono),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (TransferTransaction $record): string => $distribusi->transferDisplayStatus($record)['label'])
                    ->color(fn (TransferTransaction $record): string => $distribusi->transferDisplayStatus($record)['color']),
            ])
            ->actions([
                Tables\Actions\Action::make('detail')
                    ->label('Detail')
                    ->modalHeading(fn (TransferTransaction $record): string => $record->transfer_number)
                    ->modalContent(fn (TransferTransaction $record) => view('filament.pages.transfer-detail-modal', [
                        'transfer' => $record->load(['fromLocation', 'toLocation', 'transferItems.item']),
                    ])),
            ])
            ->defaultSort('transfer_date', 'desc')
            ->paginated([10, 25, 50]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    protected function getHeaderActions(): array
    {
        $warehouseId = app(DistribusiService::class)->centralWarehouseId();

        return [
            Action::make('export')
                ->label('Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->outlined()
                ->action(fn () => Notification::make()->title('Export sedang disiapkan')->info()->send()),
            Action::make('returBaru')
                ->label('Retur Baru')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->outlined()
                ->action(fn () => $this->activeTab = 'retur_masuk'),
            Action::make('transferBaru')
                ->label('Transfer Baru')
                ->icon('heroicon-o-arrow-up-right')
                ->modalHeading('Transfer Stok Baru')
                ->modalSubmitActionLabel('Simpan Transfer')
                ->form([
                    Forms\Components\Select::make('to_location_id')
                        ->label('Lokasi Tujuan')
                        ->options(
                            Location::query()
                                ->where('status', 'active')
                                ->where('location_type', '!=', 'central_warehouse')
                                ->orderBy('location_name')
                                ->pluck('location_name', 'id'),
                        )
                        ->searchable()
                        ->required(),
                    Forms\Components\DatePicker::make('transfer_date')
                        ->label('Tanggal Transfer')
                        ->default(now())
                        ->required(),
                    Forms\Components\Repeater::make('items')
                        ->label('Item')
                        ->schema([
                            Forms\Components\Select::make('item_id')
                                ->label('Item')
                                ->options(Item::query()->where('is_active', true)->orderBy('item_name')->pluck('item_name', 'id'))
                                ->searchable()
                                ->required(),
                            Forms\Components\TextInput::make('qty')
                                ->label('Qty')
                                ->numeric()
                                ->minValue(1)
                                ->required(),
                            Forms\Components\Select::make('bazar_adjust_type')
                                ->label('Penyesuaian Harga')
                                ->options(collect(BazarAdjustType::cases())->mapWithKeys(
                                    fn (BazarAdjustType $type) => [$type->value => $type->label()],
                                ))
                                ->default('manual')
                                ->required(),
                            Forms\Components\TextInput::make('bazar_adjust_value')
                                ->label('Nilai Penyesuaian')
                                ->numeric()
                                ->default(0),
                            Forms\Components\TextInput::make('bazar_selling_price')
                                ->label('Harga Jual Bazar')
                                ->numeric()
                                ->required(),
                        ])
                        ->minItems(1)
                        ->columns(2),
                ])
                ->action(function (array $data) use ($warehouseId): void {
                    try {
                        app(TransferService::class)->createTransfer([
                            'from_location_id' => $warehouseId,
                            'to_location_id' => $data['to_location_id'],
                            'transfer_date' => $data['transfer_date'],
                            'items' => $data['items'],
                        ], auth()->user());
                    } catch (InvalidArgumentException $exception) {
                        Notification::make()
                            ->title('Transfer gagal')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Transfer berhasil')
                        ->success()
                        ->send();

                    $this->activeTab = 'transfer_keluar';
                }),
        ];
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Transfer stok dari gudang pusat ke outlet, bazar & lokasi penjualan';
    }
}
