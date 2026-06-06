<x-filament-panels::page>
<div>

@if($activeReport === '')

<p style="font-size:13px; font-weight:500; color:#3d4a5c; margin:0 0 20px;">
    Pilih jenis laporan untuk melihat detail dan mengekspor data.
</p>

<div style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px;">

    <div wire:click="openReport('stok')"
        style="background:white; border:1px solid #D1DAE5; border-radius:12px; padding:20px; cursor:pointer; transition:box-shadow 0.15s; position:relative;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px;">
            <div style="width:40px; height:40px; background:#F3F4F6; border-radius:8px; display:flex; align-items:center; justify-content:center;">
                <x-heroicon-o-archive-box style="width:20px; height:20px; color:#49586B;" />
            </div>
            <x-heroicon-o-chevron-right style="width:18px; height:18px; color:#D1DAE5;" />
        </div>
        <h3 style="font-size:14px; font-weight:700; color:#070D1E; margin:0 0 4px;">Laporan Stok</h3>
        <p style="font-size:13px; font-weight:500; color:#3d4a5c; margin:0;">Ringkasan stok gudang pusat dan semua lokasi per item.</p>
    </div>

    <div wire:click="openReport('penjualan')"
        style="background:white; border:1px solid #D1DAE5; border-radius:12px; padding:20px; cursor:pointer; transition:box-shadow 0.15s; position:relative;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px;">
            <div style="width:40px; height:40px; background:#F3F4F6; border-radius:8px; display:flex; align-items:center; justify-content:center;">
                <x-heroicon-o-shopping-bag style="width:20px; height:20px; color:#49586B;" />
            </div>
            <x-heroicon-o-chevron-right style="width:18px; height:18px; color:#D1DAE5;" />
        </div>
        <h3 style="font-size:14px; font-weight:700; color:#070D1E; margin:0 0 4px;">Laporan Penjualan</h3>
        <p style="font-size:13px; font-weight:500; color:#3d4a5c; margin:0;">Penjualan per lokasi dengan filter rentang tanggal.</p>
    </div>

    <div wire:click="openReport('profit')"
        style="background:white; border:1px solid #D1DAE5; border-radius:12px; padding:20px; cursor:pointer; transition:box-shadow 0.15s; position:relative;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px;">
            <div style="width:40px; height:40px; background:#F3F4F6; border-radius:8px; display:flex; align-items:center; justify-content:center;">
                <x-heroicon-o-arrow-trending-up style="width:20px; height:20px; color:#49586B;" />
            </div>
            <x-heroicon-o-chevron-right style="width:18px; height:18px; color:#D1DAE5;" />
        </div>
        <h3 style="font-size:14px; font-weight:700; color:#070D1E; margin:0 0 4px;">Laporan Gross Profit</h3>
        <p style="font-size:13px; font-weight:500; color:#3d4a5c; margin:0;">Ringkasan laba kotor dan margin periode terpilih. Hanya untuk Owner.</p>
    </div>
</div>

@endif

@if($activeReport === 'stok')
<div>
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:20px;">
        <button wire:click="closeReport" type="button"
            style="background:none; border:none; cursor:pointer; color:#49586B; font-size:13px; display:flex; align-items:center; gap:4px; padding:0;">
            ← Kembali
        </button>
        <h2 style="font-size:16px; font-weight:700; color:#070D1E; margin:0;">Laporan Stok</h2>
    </div>

    <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:20px;">
        <div style="background:white; border:1px solid #D1DAE5; border-radius:10px; padding:16px;">
            <p style="font-size:11px; font-weight:700; color:#3d4a5c; text-transform:uppercase; letter-spacing:0.1em; margin:0 0 6px;">TOTAL SKU</p>
            <p style="font-size:28px; font-weight:700; font-family:monospace; color:#070D1E; margin:0;">{{ ($allStockItems ?? collect())->count() }}</p>
        </div>
        <div style="background:white; border:1px solid #D1DAE5; border-radius:10px; padding:16px;">
            <p style="font-size:11px; font-weight:700; color:#3d4a5c; text-transform:uppercase; letter-spacing:0.1em; margin:0 0 6px;">TOTAL UNIT</p>
            <p style="font-size:28px; font-weight:700; font-family:monospace; color:#070D1E; margin:0;">{{ number_format($stockTotalUnits ?? 0) }}</p>
        </div>
        <div style="background:white; border:1px solid #D1DAE5; border-radius:10px; padding:16px;">
            <p style="font-size:11px; font-weight:700; color:#3d4a5c; text-transform:uppercase; letter-spacing:0.1em; margin:0 0 6px;">RUSAK</p>
            <p style="font-size:28px; font-weight:700; font-family:monospace; color:{{ ($stockTotalDamaged ?? 0) > 0 ? '#F59100' : '#070D1E' }}; margin:0;">
                {{ number_format($stockTotalDamaged ?? 0) }}
            </p>
        </div>
        <div style="background:white; border:1px solid #D1DAE5; border-radius:10px; padding:16px;">
            <p style="font-size:11px; font-weight:700; color:#3d4a5c; text-transform:uppercase; letter-spacing:0.1em; margin:0 0 6px;">STOK KRITIS</p>
            <p style="font-size:28px; font-weight:700; font-family:monospace; color:{{ ($stockLowCount ?? 0) > 0 ? '#F04040' : '#070D1E' }}; margin:0;">
                {{ $stockLowCount ?? 0 }}
            </p>
        </div>
    </div>

    <div style="background:white; border:1px solid #D1DAE5; border-radius:12px; overflow:hidden;">
        <div style="padding:14px 16px; border-bottom:1px solid #D1DAE5; display:flex; justify-content:space-between; align-items:center;">
            <span style="font-size:16px; font-weight:700; color:#070D1E;">Stok Per Item Per Lokasi</span>
            <span style="font-size:11px; font-family:monospace; color:#49586B;">{{ ($allStockItems ?? collect())->count() }} item</span>
        </div>
        <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; font-size:13px;">
            <thead>
                <tr style="background:#F8F9FB;">
                    <th style="padding:10px 16px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.08em; color:#3d4a5c; white-space:nowrap;">ITEM</th>
                    <th style="padding:10px 16px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.08em; color:#3d4a5c; white-space:nowrap;">BARCODE</th>
                    @foreach($allLocations ?? [] as $loc)
                    <th style="padding:10px 12px; text-align:right; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.08em; color:#3d4a5c; white-space:nowrap;">
                        {{ $this->locationShortLabel($loc) }}
                    </th>
                    @endforeach
                    <th style="padding:10px 16px; text-align:right; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.08em; color:#3d4a5c;">TOTAL</th>
                    <th style="padding:10px 16px; text-align:center; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.08em; color:#3d4a5c;">STATUS</th>
                </tr>
            </thead>
            <tbody>
                @forelse($allStockItems ?? [] as $item)
                @php($totalAvail = (int) ($item->total_available ?? 0))
                <tr style="border-bottom:1px solid #F3F4F6;">
                    <td style="padding:10px 16px; font-weight:600; color:#070D1E;">{{ $item->item_name }}</td>
                    <td style="padding:10px 16px; font-family:monospace; font-size:12px; font-weight:500; color:#3d4a5c;">{{ $item->barcode }}</td>
                    @foreach($allLocations ?? [] as $loc)
                    @php($locQty = $this->itemLocationQty($item, $loc->id))
                    <td style="padding:10px 12px; text-align:right; font-family:monospace; color:{{ $locQty > 0 ? '#070D1E' : '#D1DAE5' }}; font-weight:{{ $locQty > 0 ? '700' : '400' }};">
                        {{ $locQty > 0 ? $locQty : '—' }}
                    </td>
                    @endforeach
                    <td style="padding:10px 16px; text-align:right; font-family:monospace; font-weight:700; color:#070D1E;">{{ $totalAvail }}</td>
                    <td style="padding:10px 16px; text-align:center;">
                        @if($totalAvail == 0)
                            <span style="background:#FEE2E2; color:#DC2626; font-size:10px; font-weight:700; padding:2px 8px; border-radius:20px;">HABIS</span>
                        @elseif($totalAvail <= 1)
                            <span style="background:#FEF3C7; color:#D97706; font-size:10px; font-weight:700; padding:2px 8px; border-radius:20px;">KRITIS</span>
                        @else
                            <span style="background:#D1FAE5; color:#059669; font-size:10px; font-weight:700; padding:2px 8px; border-radius:20px;">AMAN</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="{{ 4 + ($allLocations ?? collect())->count() }}" style="padding:32px; text-align:center; color:#49586B;">
                        Belum ada data stok.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>
@endif

@if($activeReport === 'penjualan')
<div>
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:20px;">
        <button wire:click="closeReport" type="button"
            style="background:none; border:none; cursor:pointer; color:#49586B; font-size:13px; padding:0;">
            ← Kembali
        </button>
        <h2 style="font-size:16px; font-weight:700; color:#070D1E; margin:0;">Laporan Penjualan</h2>
    </div>

    <div style="background:white; border:1px solid #D1DAE5; border-radius:10px; padding:16px; margin-bottom:16px; display:flex; gap:12px; align-items:flex-end;">
        <div>
            <label style="font-size:11px; font-weight:700; color:#3d4a5c; text-transform:uppercase; letter-spacing:0.1em; display:block; margin-bottom:6px;">DARI TANGGAL</label>
            <input type="date" wire:model.live="dateFrom" style="padding:8px 12px; border:1px solid #D1DAE5; border-radius:8px; font-size:13px;">
        </div>
        <div>
            <label style="font-size:11px; font-weight:700; color:#3d4a5c; text-transform:uppercase; letter-spacing:0.1em; display:block; margin-bottom:6px;">SAMPAI TANGGAL</label>
            <input type="date" wire:model.live="dateTo" style="padding:8px 12px; border:1px solid #D1DAE5; border-radius:8px; font-size:13px;">
        </div>
    </div>

    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
        <div style="background:white; border:1px solid #D1DAE5; border-radius:10px; padding:16px;">
            <p style="font-size:11px; font-weight:700; color:#3d4a5c; text-transform:uppercase; letter-spacing:0.1em; margin:0 0 6px;">TOTAL OMZET</p>
            <p style="font-size:28px; font-weight:700; font-family:monospace; color:#070D1E; margin:0;">
                {{ \App\Filament\Pages\LaporanLengkapPage::formatRupiah($salesTotalSales ?? 0) }}
            </p>
        </div>
        <div style="background:white; border:1px solid #D1DAE5; border-radius:10px; padding:16px;">
            <p style="font-size:11px; font-weight:700; color:#3d4a5c; text-transform:uppercase; letter-spacing:0.1em; margin:0 0 6px;">TOTAL TRANSAKSI</p>
            <p style="font-size:28px; font-weight:700; font-family:monospace; color:#070D1E; margin:0;">
                {{ number_format($salesTotalTrx ?? 0) }}
            </p>
        </div>
    </div>

    <div style="background:white; border:1px solid #D1DAE5; border-radius:12px; overflow:hidden;">
        <div style="padding:14px 16px; border-bottom:1px solid #D1DAE5;">
            <span style="font-size:16px; font-weight:700; color:#070D1E;">Penjualan Per Lokasi</span>
        </div>
        <table style="width:100%; border-collapse:collapse; font-size:13px;">
            <thead>
                <tr style="background:#F8F9FB;">
                    <th style="padding:10px 16px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.08em; color:#3d4a5c;">LOKASI</th>
                    <th style="padding:10px 16px; text-align:right; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.08em; color:#3d4a5c;">OMZET</th>
                    <th style="padding:10px 16px; text-align:right; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.08em; color:#3d4a5c;">TRANSAKSI</th>
                    <th style="padding:10px 16px; text-align:right; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.08em; color:#3d4a5c;">% KONTRIBUSI</th>
                </tr>
            </thead>
            <tbody>
                @forelse($salesByLoc ?? [] as $loc)
                <tr style="border-bottom:1px solid #F3F4F6;">
                    <td style="padding:12px 16px; font-weight:600; color:#070D1E;">{{ data_get($loc, 'location_name', '-') }}</td>
                    <td style="padding:12px 16px; text-align:right; font-family:monospace; font-weight:700; color:#070D1E;">
                        {{ \App\Filament\Pages\LaporanLengkapPage::formatRupiah((float) data_get($loc, 'total_sales', 0)) }}
                    </td>
                    <td style="padding:12px 16px; text-align:right; font-family:monospace; font-size:13px; font-weight:500; color:#3d4a5c;">
                        {{ number_format((int) data_get($loc, 'transaction_count', 0)) }}
                    </td>
                    <td style="padding:12px 16px; text-align:right; font-family:monospace; font-size:13px; font-weight:500; color:#3d4a5c;">
                        {{ ($salesTotalSales ?? 0) > 0
                            ? number_format((data_get($loc, 'total_sales', 0) / $salesTotalSales) * 100, 1)
                            : 0 }}%
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" style="padding:32px; text-align:center; color:#49586B;">
                        Tidak ada data penjualan pada periode ini.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif

@if($activeReport === 'profit')
<div>
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:20px;">
        <button wire:click="closeReport" type="button"
            style="background:none; border:none; cursor:pointer; color:#49586B; font-size:13px; padding:0;">
            ← Kembali
        </button>
        <h2 style="font-size:16px; font-weight:700; color:#070D1E; margin:0;">Laporan Gross Profit</h2>
    </div>

    @if(! $this->isOwner())
    <div style="background:#FEF3C7; border:1px solid #F59100; border-radius:10px; padding:16px; text-align:center;">
        <p style="color:#D97706; font-size:13px; margin:0;">
            ⚠ Laporan Gross Profit hanya dapat diakses oleh Owner.
        </p>
    </div>
    @else
    <div style="background:white; border:1px solid #D1DAE5; border-radius:10px; padding:16px; margin-bottom:16px; display:flex; gap:12px; align-items:flex-end;">
        <div>
            <label style="font-size:11px; font-weight:700; color:#3d4a5c; text-transform:uppercase; letter-spacing:0.1em; display:block; margin-bottom:6px;">DARI TANGGAL</label>
            <input type="date" wire:model.live="dateFrom" style="padding:8px 12px; border:1px solid #D1DAE5; border-radius:8px; font-size:13px;">
        </div>
        <div>
            <label style="font-size:11px; font-weight:700; color:#3d4a5c; text-transform:uppercase; letter-spacing:0.1em; display:block; margin-bottom:6px;">SAMPAI TANGGAL</label>
            <input type="date" wire:model.live="dateTo" style="padding:8px 12px; border:1px solid #D1DAE5; border-radius:8px; font-size:13px;">
        </div>
    </div>

    <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:16px;">
        <div style="background:#070D1E; border-radius:10px; padding:16px;">
            <p style="font-size:11px; font-weight:700; color:rgba(255,255,255,0.75); text-transform:uppercase; letter-spacing:0.1em; margin:0 0 6px;">TOTAL PENJUALAN</p>
            <p style="font-size:28px; font-weight:700; font-family:monospace; color:white; margin:0;">
                {{ \App\Filament\Pages\LaporanLengkapPage::formatRupiah((float) data_get($profitData ?? [], 'total_sales', 0)) }}
            </p>
        </div>
        <div style="background:white; border:1px solid #D1DAE5; border-radius:10px; padding:16px;">
            <p style="font-size:11px; font-weight:700; color:#3d4a5c; text-transform:uppercase; letter-spacing:0.1em; margin:0 0 6px;">TOTAL HPP</p>
            <p style="font-size:28px; font-weight:700; font-family:monospace; color:#070D1E; margin:0;">
                {{ \App\Filament\Pages\LaporanLengkapPage::formatRupiah((float) data_get($profitData ?? [], 'total_cogs', 0)) }}
            </p>
        </div>
        <div style="background:white; border:1px solid #D1DAE5; border-radius:10px; padding:16px;">
            <p style="font-size:11px; font-weight:700; color:#3d4a5c; text-transform:uppercase; letter-spacing:0.1em; margin:0 0 6px;">GROSS PROFIT</p>
            <p style="font-size:28px; font-weight:700; font-family:monospace; color:{{ data_get($profitData ?? [], 'gross_profit', 0) >= 0 ? '#29A85A' : '#F04040' }}; margin:0;">
                {{ \App\Filament\Pages\LaporanLengkapPage::formatRupiah((float) data_get($profitData ?? [], 'gross_profit', 0)) }}
            </p>
        </div>
        <div style="background:white; border:1px solid #D1DAE5; border-radius:10px; padding:16px;">
            <p style="font-size:11px; font-weight:700; color:#3d4a5c; text-transform:uppercase; letter-spacing:0.1em; margin:0 0 6px;">MARGIN %</p>
            <p style="font-size:28px; font-weight:700; font-family:monospace; color:{{ data_get($profitData ?? [], 'gross_margin_pct', 0) >= 20 ? '#29A85A' : '#F59100' }}; margin:0;">
                ↗ {{ number_format((float) data_get($profitData ?? [], 'gross_margin_pct', 0), 1) }}%
            </p>
        </div>
    </div>

    @include('filament.partials.per-item-profit-table', ['perItemProfit' => $perItemProfit ?? collect()])

    <div style="background:white; border:1px solid #D1DAE5; border-radius:12px; overflow:hidden;">
        <div style="padding:14px 16px; border-bottom:1px solid #D1DAE5;">
            <span style="font-size:16px; font-weight:700; color:#070D1E;">Top 10 Produk Terlaris</span>
        </div>
        <table style="width:100%; border-collapse:collapse; font-size:13px;">
            <thead>
                <tr style="background:#F8F9FB;">
                    <th style="padding:10px 16px; text-align:center; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.08em; color:#3d4a5c; width:40px;">#</th>
                    <th style="padding:10px 16px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.08em; color:#3d4a5c;">ITEM</th>
                    <th style="padding:10px 16px; text-align:right; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.08em; color:#3d4a5c;">QTY TERJUAL</th>
                    <th style="padding:10px 16px; text-align:right; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.08em; color:#3d4a5c;">OMZET</th>
                </tr>
            </thead>
            <tbody>
                @forelse($bestSelling ?? [] as $product)
                <tr style="border-bottom:1px solid #F3F4F6;">
                    <td style="padding:10px 16px; text-align:center; font-family:monospace; font-weight:700; color:#49586B; font-size:11px;">
                        {{ str_pad((string) data_get($product, 'rank', 1), 2, '0', STR_PAD_LEFT) }}
                    </td>
                    <td style="padding:10px 16px;">
                        <div style="font-weight:600; color:#070D1E;">{{ data_get($product, 'item_name', '-') }}</div>
                        <div style="font-family:monospace; font-size:12px; font-weight:500; color:#3d4a5c;">{{ data_get($product, 'sku', '-') }}</div>
                    </td>
                    <td style="padding:10px 16px; text-align:right; font-family:monospace; font-weight:700; color:#070D1E;">
                        {{ number_format((int) data_get($product, 'total_qty_sold', 0)) }}
                    </td>
                    <td style="padding:10px 16px; text-align:right; font-family:monospace; font-weight:700; color:#070D1E;">
                        {{ \App\Filament\Pages\LaporanLengkapPage::formatRupiah((float) data_get($product, 'total_revenue', 0)) }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" style="padding:32px; text-align:center; color:#49586B;">
                        Tidak ada data penjualan pada periode ini.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @endif
</div>
@endif

</div>
</x-filament-panels::page>
