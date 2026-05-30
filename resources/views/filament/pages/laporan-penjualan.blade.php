<x-filament-panels::page>
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

    <div class="overflow-hidden rounded-lg border border-[var(--aksana-border)] bg-white">
        <table class="aksana-table w-full text-sm">
            <thead>
                <tr>
                    <th>Lokasi</th>
                    <th>Tipe</th>
                    <th>Transaksi</th>
                    <th>Total Penjualan</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->getSalesRows() as $row)
                    <tr>
                        <td class="font-semibold">{{ data_get($row, 'location_name', '-') }}</td>
                        <td>{{ data_get($row, 'location_type', '-') }}</td>
                        <td class="font-mono">{{ data_get($row, 'transaction_count', 0) }}</td>
                        <td class="font-mono font-bold">{{ \App\Filament\Pages\LaporanPenjualanPage::formatRupiah((float) data_get($row, 'total_sales', 0)) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="py-8 text-center text-[var(--aksana-muted)]">Tidak ada data penjualan.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>

