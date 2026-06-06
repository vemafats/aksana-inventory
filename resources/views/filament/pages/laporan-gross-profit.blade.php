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

    <div class="mt-6 overflow-hidden rounded-lg border border-[var(--aksana-border)] bg-white">
        <div class="border-b border-[var(--aksana-border)] px-4 py-3">
            <h3 class="text-sm font-bold text-[var(--aksana-void)]">Gross Profit per Item</h3>
            <p class="mt-1 text-xs text-[var(--aksana-muted)]">Breakdown berdasarkan snapshot harga modal per transaksi</p>
        </div>
        <div class="overflow-x-auto p-4">
            <table class="aksana-table w-full text-sm">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Barcode</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Penjualan</th>
                        <th class="text-right">HPP</th>
                        <th class="text-right">Profit</th>
                        <th class="text-right">Margin %</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($perItemBreakdown as $row)
                        <tr>
                            <td class="font-semibold">{{ $row->item_name }}</td>
                            <td class="aksana-mono text-[var(--aksana-muted)]">{{ $row->barcode }}</td>
                            <td class="text-right font-mono">{{ number_format($row->total_qty) }}</td>
                            <td class="text-right font-mono">{{ \App\Filament\Pages\LaporanGrossProfitPage::formatRupiah($row->total_revenue) }}</td>
                            <td class="text-right font-mono">{{ \App\Filament\Pages\LaporanGrossProfitPage::formatRupiah($row->total_cost) }}</td>
                            <td class="text-right font-mono {{ $row->profit >= 0 ? 'text-[#29A85A]' : 'text-[#F04040]' }}">
                                {{ \App\Filament\Pages\LaporanGrossProfitPage::formatRupiah($row->profit) }}
                            </td>
                            <td class="text-right font-mono">{{ $row->margin }}%</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-6 text-center text-[var(--aksana-muted)]">Tidak ada data penjualan pada periode ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>

