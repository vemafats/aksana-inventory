<?php

namespace App\Filament\Concerns;

use App\Models\EventExpense;
use App\Models\SalesTransaction;
use App\Support\TimezoneQuery;
use Illuminate\Support\Collection;

trait InteractsWithEventExpenseReports
{
    private function getTotalExpenses(): float
    {
        $query = EventExpense::query();

        if ($this->dateFrom) {
            TimezoneQuery::whereDateColumnFrom($query, 'expense_date', $this->dateFrom);
        }

        if ($this->dateTo) {
            TimezoneQuery::whereDateColumnTo($query, 'expense_date', $this->dateTo);
        }

        return (float) $query->sum('amount');
    }

    /**
     * @return Collection<int, object>
     */
    private function getExpensesByEvent(): Collection
    {
        $query = EventExpense::query()
            ->join('events', 'event_expenses.event_id', '=', 'events.id')
            ->join('locations', 'events.location_id', '=', 'locations.id');

        if ($this->dateFrom) {
            TimezoneQuery::whereDateColumnFrom($query, 'event_expenses.expense_date', $this->dateFrom);
        }

        if ($this->dateTo) {
            TimezoneQuery::whereDateColumnTo($query, 'event_expenses.expense_date', $this->dateTo);
        }

        return $query
            ->selectRaw('
                events.name as event_name,
                locations.location_name,
                SUM(event_expenses.amount) as total_amount,
                COUNT(event_expenses.id) as expense_count
            ')
            ->groupBy('events.id', 'events.name', 'locations.location_name')
            ->orderByDesc('total_amount')
            ->get();
    }

    private function getTotalDiscount(): float
    {
        $query = SalesTransaction::query();

        if ($this->dateFrom) {
            TimezoneQuery::whereTimestampFrom($query, 'transaction_date', $this->dateFrom);
        }

        if ($this->dateTo) {
            TimezoneQuery::whereTimestampTo($query, 'transaction_date', $this->dateTo);
        }

        return (float) $query->sum('transaction_discount_amount');
    }
}
