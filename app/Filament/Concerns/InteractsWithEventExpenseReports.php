<?php

namespace App\Filament\Concerns;

use App\Services\ReportService;
use Illuminate\Support\Collection;

trait InteractsWithEventExpenseReports
{
    private function getTotalExpenses(): float
    {
        return app(ReportService::class)->totalEventExpenses($this->dateFrom, $this->dateTo);
    }

    /**
     * @return Collection<int, object>
     */
    private function getExpensesByEvent(): Collection
    {
        return app(ReportService::class)->eventExpensesByEvent($this->dateFrom, $this->dateTo);
    }

    private function getTotalDiscount(): float
    {
        return app(ReportService::class)->totalTransactionDiscount($this->dateFrom, $this->dateTo);
    }
}
