<x-filament-panels::page>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <a href="{{ \App\Filament\Pages\LaporanLengkapPage::getUrl() }}" wire:navigate class="text-xs font-semibold uppercase text-[var(--aksana-muted)] hover:text-[var(--aksana-void)]">
            ← Kembali ke Laporan Lengkap
        </a>
        <input
            type="search"
            wire:model.live.debounce.300ms="search"
            placeholder="Cari nama atau SKU..."
            class="w-full max-w-xs rounded-md border border-[var(--aksana-border)] px-3 py-2 text-sm"
        />
    </div>

    <div class="overflow-hidden rounded-lg border border-[var(--aksana-border)] bg-white">
        <table class="aksana-table w-full text-sm">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>SKU</th>
                    <th>Available</th>
                    <th>Damaged</th>
                    <th>Lost</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->getStockRows() as $row)
                    <tr>
                        <td class="font-semibold">{{ $row['item_name'] }}</td>
                        <td class="aksana-mono text-[var(--aksana-muted)]">{{ $row['sku'] }}</td>
                        <td class="font-mono font-bold">{{ $row['available'] }}</td>
                        <td class="font-mono">{{ $row['damaged'] }}</td>
                        <td class="font-mono">{{ $row['lost'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-8 text-center text-[var(--aksana-muted)]">Tidak ada data stok.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>

