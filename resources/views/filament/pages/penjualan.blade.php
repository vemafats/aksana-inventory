<x-filament-panels::page>
    <div class="aksana-tabs mb-6 flex flex-wrap gap-2">
        <button
            type="button"
            wire:click="$set('activeTab', 'ringkasan')"
            @class([
                'aksana-tab',
                'aksana-tab-active' => $activeTab === 'ringkasan',
            ])
        >
            <x-heroicon-m-squares-2x2 class="h-4 w-4" />
            Ringkasan
        </button>
        <button
            type="button"
            wire:click="$set('activeTab', 'riwayat')"
            @class([
                'aksana-tab',
                'aksana-tab-active' => $activeTab === 'riwayat',
            ])
        >
            <x-heroicon-m-bars-3 class="h-4 w-4" />
            Riwayat
        </button>
        <button
            type="button"
            wire:click="$set('activeTab', 'top_produk')"
            @class([
                'aksana-tab',
                'aksana-tab-active' => $activeTab === 'top_produk',
            ])
        >
            <x-heroicon-m-arrow-trending-up class="h-4 w-4" />
            Top Produk
        </button>
    </div>

    @if ($activeTab === 'ringkasan')
        @php
            $stats = $this->getRingkasanStats();
            $locations = $this->getSalesByLocation();
            $payments = $this->getPaymentBreakdown();
            $recent = $this->getRecentTransactions();
            $maxLocationSales = max($locations->max('total_sales') ?: 1, 1);
        @endphp

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4 mb-6">
            @foreach ([
                ['label' => 'Penjualan Hari Ini', 'value' => \App\Filament\Pages\PenjualanPage::formatRupiah($stats['today_sales']), 'sub' => 'hari ini', 'icon' => 'heroicon-o-banknotes'],
                ['label' => 'Penjualan 7 Hari', 'value' => \App\Filament\Pages\PenjualanPage::formatRupiah($stats['seven_day_sales']), 'sub' => '7 hari terakhir', 'icon' => 'heroicon-o-calendar-days'],
                ['label' => 'Item Terjual (24H)', 'value' => number_format($stats['items_sold_24h']), 'sub' => 'unit terjual', 'icon' => 'heroicon-o-shopping-bag'],
                ['label' => 'Rata-rata Nota', 'value' => \App\Filament\Pages\PenjualanPage::formatRupiah($stats['avg_basket']), 'sub' => 'per transaksi hari ini', 'icon' => 'heroicon-o-receipt-percent'],
            ] as $card)
                @include('filament.components.aksana-stat-card', [
                    'label' => $card['label'],
                    'value' => $card['value'],
                    'sub' => $card['sub'],
                    'icon' => $card['icon'],
                    'warn' => $card['warn'] ?? false,
                    'danger' => $card['danger'] ?? false,
                ])
            @endforeach
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3 mb-6">
            <div class="aksana-panel xl:col-span-2">
                <h3 class="aksana-panel-title">Penjualan per Lokasi · hari ini</h3>
                <div class="space-y-4">
                    @forelse ($locations as $row)
                        <div>
                            <div class="mb-1 flex items-center justify-between gap-4 text-sm">
                                <span class="font-semibold">{{ $row['location_name'] }}</span>
                                <span class="aksana-mono text-sm">
                                    {{ \App\Filament\Pages\PenjualanPage::formatRupiah($row['total_sales'], true) }}
                                    <span class="text-gray-500">{{ $row['transaction_count'] }} trx</span>
                                </span>
                            </div>
                            <div class="aksana-progress-track">
                                <div
                                    class="aksana-progress-fill"
                                    style="width: {{ min(100, ($row['total_sales'] / $maxLocationSales) * 100) }}%"
                                ></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Belum ada penjualan hari ini.</p>
                    @endforelse
                </div>
            </div>

            <div class="aksana-panel">
                <h3 class="aksana-panel-title">Metode Pembayaran</h3>
                <div class="space-y-3">
                    <div class="aksana-payment-card">
                        <div class="aksana-payment-icon">QR</div>
                        <div class="flex-1">
                            <p class="font-semibold">QRIS</p>
                            <p class="text-xs text-gray-500">{{ $payments['qris']['count'] }} transaksi</p>
                        </div>
                        <p class="aksana-mono font-semibold">{{ \App\Filament\Pages\PenjualanPage::formatRupiah($payments['qris']['total'], true) }}</p>
                    </div>
                    <div class="aksana-payment-card">
                        <div class="aksana-payment-icon">Rp</div>
                        <div class="flex-1">
                            <p class="font-semibold">Tunai</p>
                            <p class="text-xs text-gray-500">{{ $payments['cash']['count'] }} transaksi</p>
                        </div>
                        <p class="aksana-mono font-semibold">{{ \App\Filament\Pages\PenjualanPage::formatRupiah($payments['cash']['total'], true) }}</p>
                    </div>
                    <div class="aksana-payment-card">
                        <div
                            class="aksana-payment-icon flex items-center justify-center"
                            style="background:#DBEAFE;color:#1660ED;"
                        >
                            <x-heroicon-m-building-library class="h-4 w-4" />
                        </div>
                        <div class="flex-1">
                            <p class="font-semibold">Transfer Bank</p>
                            <p class="text-xs text-gray-500">{{ $payments['transfer']['count'] }} transaksi</p>
                        </div>
                        <p class="aksana-mono font-semibold">{{ \App\Filament\Pages\PenjualanPage::formatRupiah($payments['transfer']['total'], true) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="aksana-panel">
            <h3 class="aksana-panel-title mb-4">Transaksi Terbaru</h3>
            <div class="overflow-x-auto">
                <table class="aksana-table w-full text-sm">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Waktu</th>
                            <th>Lokasi</th>
                            <th>Kasir</th>
                            <th>Item</th>
                            <th>Total</th>
                            <th>Bayar</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recent as $trx)
                            <tr>
                                <td class="aksana-mono">{{ $trx->sales_number }}</td>
                                <td>{{ $trx->transaction_date->format('d M Y H:i') }}</td>
                                <td>{{ $trx->location?->location_name }}</td>
                                <td>
                                    <span class="inline-flex items-center gap-1">
                                        <x-heroicon-m-user class="h-4 w-4 text-gray-400" />
                                        {{ $trx->salesUser?->name }}
                                    </span>
                                </td>
                                <td class="aksana-mono">{{ $trx->salesItems->sum('qty') }}</td>
                                <td class="aksana-mono font-bold">{{ \App\Filament\Pages\PenjualanPage::formatRupiah((float) $trx->grand_total, true) }}</td>
                                <td>{{ $trx->payment_method->label() }}</td>
                                <td><span class="aksana-badge-success">LUNAS</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-6 text-center text-gray-500">Belum ada transaksi.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($activeTab === 'riwayat')
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">Tanggal</label>
            <input
                type="date"
                wire:model.live="historyDate"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm aksana-mono"
            />
        </div>

        {{ $this->table }}
    @endif

    @if ($activeTab === 'top_produk')
        @php
            $topProducts = $this->getTopProducts();
            $maxQty = max($topProducts->max('total_qty_sold') ?: 1, 1);
        @endphp

        <div class="aksana-panel">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="aksana-panel-title">Top Produk Terjual</h3>
                <span class="text-xs text-gray-500 aksana-mono">30 hari terakhir</span>
            </div>
            <div class="overflow-x-auto">
                <table class="aksana-table w-full text-sm">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Barcode</th>
                            <th>Item</th>
                            <th>Volume</th>
                            <th>Qty</th>
                            <th>Omzet</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($topProducts as $row)
                            <tr>
                                <td class="aksana-mono">{{ str_pad((string) $row['rank'], 2, '0', STR_PAD_LEFT) }}</td>
                                <td class="aksana-mono font-bold">{{ $row['barcode'] }}</td>
                                <td class="font-semibold">{{ $row['item_name'] }}</td>
                                <td class="min-w-[160px]">
                                    <div class="aksana-progress-track">
                                        <div
                                            class="aksana-progress-fill"
                                            style="width: {{ min(100, ($row['total_qty_sold'] / $maxQty) * 100) }}%"
                                        ></div>
                                    </div>
                                </td>
                                <td class="aksana-mono font-bold">{{ $row['total_qty_sold'] }}</td>
                                <td class="aksana-mono font-bold">{{ \App\Filament\Pages\PenjualanPage::formatRupiah($row['total_revenue'], true) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-6 text-center text-gray-500">Belum ada data penjualan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-filament-panels::page>
