<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\InteractsWithEventExpenseReports;
use App\Helpers\FormatHelper;
use App\Services\ReportService;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class LaporanGrossProfitPage extends Page
{
    use InteractsWithEventExpenseReports;
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
    public function getPerItemProfit(): Collection
    {
        return app(ReportService::class)->grossProfitPerItem($this->dateFrom, $this->dateTo);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'perItemProfit' => $this->getPerItemProfit(),
            'totalExpenses' => $this->getTotalExpenses(),
            'expensesByEvent' => $this->getExpensesByEvent(),
            'totalDiscount' => $this->getTotalDiscount(),
        ];
    }

    public static function formatRupiah(float $amount): string
    {
        return FormatHelper::price($amount);
    }
}
