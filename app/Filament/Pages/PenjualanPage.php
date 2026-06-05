<?php

namespace App\Filament\Pages;

use App\Helpers\FormatHelper;

use App\Enums\PaymentMethod;
use App\Models\SalesItem;
use App\Models\SalesTransaction;
use App\Services\ReportService;
use App\Support\TimezoneQuery;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\FontFamily;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PenjualanPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationLabel = 'Penjualan';

    protected static ?int $navigationSort = 6;

    protected static ?string $title = 'Penjualan';

    protected static ?string $slug = 'penjualan';

    protected static string $view = 'filament.pages.penjualan';

    public string $activeTab = 'ringkasan';

    public ?string $historyDate = null;

    public function mount(): void
    {
        $this->historyDate = TimezoneQuery::todayDateString();
    }

    /**
     * @return array<string, mixed>
     */
    public function getRingkasanStats(): array
    {
        $today = TimezoneQuery::todayDateString();
        $sevenDaysAgo = now(TimezoneQuery::TIMEZONE)->subDays(6)->startOfDay();

        $todaySalesQuery = SalesTransaction::query();
        TimezoneQuery::whereTimestampEquals($todaySalesQuery, 'transaction_date', $today);
        $todaySales = (float) $todaySalesQuery->sum('grand_total');

        $sevenDaySales = (float) SalesTransaction::query()
            ->where('transaction_date', '>=', $sevenDaysAgo)
            ->sum('grand_total');

        $itemsSold24h = (int) SalesItem::query()
            ->whereHas(
                'salesTransaction',
                fn (Builder $q) => $q->where('transaction_date', '>=', now(TimezoneQuery::TIMEZONE)->subDay()),
            )
            ->sum('qty');

        $transactionCountQuery = SalesTransaction::query();
        TimezoneQuery::whereTimestampEquals($transactionCountQuery, 'transaction_date', $today);
        $transactionCount = $transactionCountQuery->count();

        $avgBasket = $transactionCount > 0 ? $todaySales / $transactionCount : 0.0;

        return [
            'today_sales' => $todaySales,
            'seven_day_sales' => $sevenDaySales,
            'items_sold_24h' => $itemsSold24h,
            'avg_basket' => $avgBasket,
        ];
    }

    public function getSalesByLocation(): Collection
    {
        return app(ReportService::class)->salesByLocation(auth()->user(), [
            'date_from' => TimezoneQuery::todayDateString(),
            'date_to' => TimezoneQuery::todayDateString(),
        ]);
    }

    /**
     * @return array<string, array{total: float, count: int}>
     */
    public function getPaymentBreakdown(): array
    {
        $today = TimezoneQuery::todayDateString();

        $rowsQuery = SalesTransaction::query();
        TimezoneQuery::whereTimestampEquals($rowsQuery, 'transaction_date', $today);
        $rows = $rowsQuery
            ->selectRaw('payment_method, SUM(grand_total) as total, COUNT(*) as trx_count')
            ->groupBy('payment_method')
            ->get();

        $breakdown = [
            'qris' => ['total' => 0.0, 'count' => 0],
            'cash' => ['total' => 0.0, 'count' => 0],
            'transfer' => ['total' => 0.0, 'count' => 0],
        ];

        foreach ($rows as $row) {
            $method = $row->payment_method instanceof PaymentMethod
                ? $row->payment_method->value
                : (string) $row->payment_method;

            if ($method === PaymentMethod::QRIS->value) {
                $breakdown['qris']['total'] = (float) $row->total;
                $breakdown['qris']['count'] = (int) $row->trx_count;
            } elseif ($method === PaymentMethod::CASH->value) {
                $breakdown['cash']['total'] = (float) $row->total;
                $breakdown['cash']['count'] = (int) $row->trx_count;
            } elseif ($method === PaymentMethod::TRANSFER->value) {
                $breakdown['transfer']['total'] = (float) $row->total;
                $breakdown['transfer']['count'] = (int) $row->trx_count;
            }
        }

        return $breakdown;
    }

    public function getRecentTransactions(): Collection
    {
        return SalesTransaction::query()
            ->with(['location', 'salesUser', 'salesItems'])
            ->latest('transaction_date')
            ->limit(5)
            ->get();
    }

    public function getTopProducts(): Collection
    {
        return app(ReportService::class)->bestSellingProducts(auth()->user(), [
            'date_from' => now(TimezoneQuery::TIMEZONE)->subDays(30)->toDateString(),
            'date_to' => TimezoneQuery::todayDateString(),
            'limit' => 20,
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => SalesTransaction::query()
                ->with(['location', 'salesUser', 'salesItems'])
                ->when(
                    $this->historyDate,
                    fn (Builder $query) => TimezoneQuery::whereTimestampEquals(
                        $query,
                        'transaction_date',
                        $this->historyDate,
                    ),
                )
                ->latest('transaction_date'))
            ->columns([
                Tables\Columns\TextColumn::make('sales_number')
                    ->label('Kode')
                    ->searchable()
                    ->fontFamily(FontFamily::Mono)
                    ->extraAttributes(['class' => 'aksana-mono']),
                Tables\Columns\TextColumn::make('transaction_date')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('location.location_name')
                    ->label('Lokasi')
                    ->searchable(),
                Tables\Columns\TextColumn::make('salesUser.name')
                    ->label('Kasir')
                    ->icon('heroicon-m-user'),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Item')
                    ->counts('salesItems')
                    ->fontFamily(FontFamily::Mono),
                Tables\Columns\TextColumn::make('grand_total')
                    ->label('Total')
                    ->formatStateUsing(fn (float|int|string|null $state): string => FormatHelper::price($state))
                    ->weight('bold')
                    ->fontFamily(FontFamily::Mono),
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Bayar')
                    ->formatStateUsing(fn (PaymentMethod $state): string => $state->label())
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->state(fn (): string => 'LUNAS')
                    ->badge()
                    ->color('success'),
            ])
            ->actions([
                Tables\Actions\Action::make('detail')
                    ->label('Detail')
                    ->modalHeading(fn (SalesTransaction $record): string => $record->sales_number)
                    ->modalContent(fn (SalesTransaction $record) => view('filament.pages.sale-detail-modal', [
                        'transaction' => $record->load(['location', 'salesUser', 'salesItems.item']),
                    ])),
            ])
            ->defaultSort('transaction_date', 'desc')
            ->paginated([10, 25, 50]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => Notification::make()
                    ->title('Export sedang disiapkan')
                    ->info()
                    ->send()),
        ];
    }

    public static function formatRupiah(float $amount, bool $compact = false): string
    {
        return FormatHelper::price($amount);
    }
}
