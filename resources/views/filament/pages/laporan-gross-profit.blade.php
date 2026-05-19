<x-filament-panels::page>
    @php($summary = $this->getProfitSummary())

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <a href="{{ \App\Filament\Pages\LaporanLengkapPage::getUrl() }}" wire:navigate class="text-xs font-semibold uppercase text-[var(--aksana-muted)] hover:text-[var(--aksana-void)]">
            ← Kembali ke Laporan Lengkap
        </a>
        <div class="flex flex-wrap items-center gap-2">
            <input type="date" wire:model.live="dateFrom" class="rounded-md border border-[var(--aksana-border)] px-2 py-1.5 text-sm" />
            <span class="text-[var(--aksana-muted)]">—</span>
            <input type="date" wire:model.live="dateTo" class="rounded-md border border-[var(--aksana-border)] px-2 py-1.5 text-sm" />
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['label' => 'Total Penjualan', 'value' => \App\Filament\Pages\LaporanGrossProfitPage::formatRupiah($summary['total_sales'])],
            ['label' => 'Total COGS', 'value' => \App\Filament\Pages\LaporanGrossProfitPage::formatRupiah($summary['total_cogs'])],
            ['label' => 'Gross Profit', 'value' => \App\Filament\Pages\LaporanGrossProfitPage::formatRupiah($summary['gross_profit'])],
            ['label' => 'Margin', 'value' => $summary['gross_margin_pct'].'%'],
        ] as $card)
            <div class="rounded-lg border border-[var(--aksana-border)] bg-white p-4">
                <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--aksana-muted)]">{{ $card['label'] }}</p>
                <p class="mt-2 text-2xl font-bold font-mono text-[var(--aksana-void)]">{{ $card['value'] }}</p>
            </div>
        @endforeach
    </div>

    <p class="mt-4 text-xs text-[var(--aksana-muted)]">
        {{ $summary['transaction_count'] }} transaksi · periode {{ $summary['period']['from'] }} s/d {{ $summary['period']['to'] }}
    </p>
</x-filament-panels::page>

