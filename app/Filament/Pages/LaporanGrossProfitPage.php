<?php

namespace App\Filament\Pages;

use App\Helpers\FormatHelper;
use App\Services\ReportService;
use App\Models\SalesItem;
use App\Support\TimezoneQuery;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class LaporanGrossProfitPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Laporan Gross Profit';

    protected static ?string $slug = 'laporan/gross-profit';

    protected static string $view = 'filament.pages.laporan-gross-profit';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public function mount(): void
    {
        $this->dateFrom = now()->subDays(29)->toDateString();
        $this->dateTo = now()->toDateString();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->role->canViewFullReport();
    }

    /**
     * @return array<string, mixed>
     */
    public function getProfitSummary(): array
    {
        return app(ReportService::class)->grossProfit(auth()->user(), [
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
        ]);
    }

    /**
     * @return Collection<int, object>
     */
    public function getPerItemBreakdown(): Collection
    {
        $query = SalesItem::query()
            ->join('items', 'sales_items.item_id', '=', 'items.id')
            ->join('sales_transactions', 'sales_items.sales_transaction_id', '=', 'sales_transactions.id');

        if ($this->dateFrom) {
            TimezoneQuery::whereTimestampFrom($query, 'sales_transactions.transaction_date', $this->dateFrom);
        }

        if ($this->dateTo) {
            TimezoneQuery::whereTimestampTo($query, 'sales_transactions.transaction_date', $this->dateTo);
        }

        return $query
            ->selectRaw('
                items.item_name,
                items.barcode,
                SUM(sales_items.qty) as total_qty,
                SUM(sales_items.selling_price * sales_items.qty) as total_revenue,
                SUM(sales_items.supplier_cost_snapshot * sales_items.qty) as total_cost
            ')
            ->groupBy('items.id', 'items.item_name', 'items.barcode')
            ->orderByDesc('total_revenue')
            ->get()
            ->map(function ($row) {
                $profit = $row->total_revenue - $row->total_cost;
                $margin = $row->total_revenue > 0 ? ($profit / $row->total_revenue) * 100 : 0;

                return (object) [
                    'item_name' => $row->item_name,
                    'barcode' => $row->barcode,
                    'total_qty' => (int) $row->total_qty,
                    'total_revenue' => (float) $row->total_revenue,
                    'total_cost' => (float) $row->total_cost,
                    'profit' => $profit,
                    'margin' => round($margin, 1),
                ];
            });
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'perItemBreakdown' => $this->getPerItemBreakdown(),
        ];
    }

    public static function formatRupiah(float $amount): string
    {
        return FormatHelper::price($amount);
    }
}
