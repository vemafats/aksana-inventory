<x-filament-panels::page>
    @php
        $summary = $this->getProfitSummary();
        $totalPenjualan = (float) ($summary['total_sales'] ?? 0);
        $grossProfit = (float) ($summary['gross_profit'] ?? 0);
        $netProfit = $grossProfit - ($totalExpenses ?? 0);
        $netMargin = $totalPenjualan > 0 ? ($netProfit / $totalPenjualan) * 100 : 0;
    @endphp

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <a href="{{ \App\Filament\Pages\LaporanLengkapPage::getUrl() }}" wire:navigate class="text-xs font-semibold uppercase text-[var(--aksana-muted)] hover:text-[var(--aksana-void)]">
            ← Kembali ke Laporan Lengkap
        </a>
        <div class="flex flex-wrap items-center gap-2">
            <input type="date" wire:model.live="dateFrom" class="rounded-md border border-[var(--aksana-border)] px-2 py-1.5 text-sm" />
            <span class="text-[var(--aksana-muted)]">—</span>
            <input type="date" wire:model.live="dateTo" class="rounded-md border border-[var(--aksana-border)] px-2 py-1.5 text-sm" />
            @if($this->isOwner())
                @include('filament.partials.gross-profit-export-button', [
                    'dateFrom' => $dateFrom,
                    'dateTo' => $dateTo,
                ])
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-7">
        @include('filament.components.aksana-stat-card', [
            'label' => 'TOTAL PENJUALAN',
            'value' => \App\Filament\Pages\LaporanGrossProfitPage::formatRupiah($totalPenjualan),
            'sub' => 'grand total periode',
            'icon' => 'heroicon-o-banknotes',
        ])
        @include('filament.components.aksana-stat-card', [
            'label' => 'TOTAL HPP',
            'value' => \App\Filament\Pages\LaporanGrossProfitPage::formatRupiah((float) ($summary['total_cogs'] ?? 0)),
            'sub' => 'cost of goods sold',
            'icon' => 'heroicon-o-cube',
        ])
        @include('filament.components.aksana-stat-card', [
            'label' => 'GROSS PROFIT',
            'value' => \App\Filament\Pages\LaporanGrossProfitPage::formatRupiah($grossProfit),
            'sub' => 'penjualan − HPP',
            'icon' => 'heroicon-o-chart-bar',
            'warn' => $grossProfit < 0,
            'danger' => $grossProfit < 0,
        ])
        @include('filament.components.aksana-stat-card', [
            'label' => 'TOTAL BIAYA',
            'value' => \App\Filament\Pages\LaporanGrossProfitPage::formatRupiah($totalExpenses ?? 0),
            'sub' => 'biaya operasional event',
            'icon' => 'heroicon-o-receipt-percent',
            'warn' => true,
        ])
        @include('filament.components.aksana-stat-card', [
            'label' => 'TOTAL DISKON',
            'value' => \App\Filament\Pages\LaporanGrossProfitPage::formatRupiah($totalDiscount ?? 0),
            'sub' => 'diskon transaksi',
            'icon' => 'heroicon-o-tag',
        ])
        @include('filament.components.aksana-stat-card', [
            'label' => 'NET PROFIT',
            'value' => \App\Filament\Pages\LaporanGrossProfitPage::formatRupiah($netProfit),
            'sub' => 'setelah biaya event',
            'icon' => $netProfit >= 0 ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-arrow-trending-down',
            'warn' => $netProfit < 0,
            'danger' => $netProfit < 0,
        ])
        @include('filament.components.aksana-stat-card', [
            'label' => 'NET MARGIN %',
            'value' => round($netMargin, 1).'%',
            'sub' => 'net profit / penjualan',
            'icon' => 'heroicon-o-calculator',
            'warn' => $netMargin < 20 && $netMargin >= 0,
            'danger' => $netMargin < 0,
        ])
    </div>

    <p class="mt-4 text-xs text-[var(--aksana-muted)]">
        {{ $summary['transaction_count'] }} transaksi · periode {{ $summary['period']['from'] }} s/d {{ $summary['period']['to'] }}
        · margin kotor {{ number_format((float) ($summary['gross_margin_pct'] ?? 0), 1) }}%
    </p>

    @include('filament.partials.per-item-profit-table', ['perItemProfit' => $perItemProfit ?? $this->getPerItemProfit()])

    @include('filament.partials.expenses-by-event-table', ['expensesByEvent' => $expensesByEvent ?? collect()])
</x-filament-panels::page>
