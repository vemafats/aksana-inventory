<x-filament-panels::page>
    @php
        $stats = $this->getDistribusiStats();
        $distribusi = app(\App\Services\DistribusiService::class);
    @endphp

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4 mb-6">
        @foreach ([
            ['label' => 'Transfer Aktif', 'value' => number_format($stats['transfer_aktif']), 'sub' => 'outlet & bazar berjalan', 'warn' => false],
            ['label' => 'Item Dalam Perjalanan', 'value' => number_format($stats['item_dalam_perjalanan']), 'sub' => 'unit menuju lokasi', 'warn' => false],
            ['label' => 'Menunggu Retur', 'value' => number_format($stats['menunggu_retur']), 'sub' => 'lokasi menjelang berakhir', 'warn' => $stats['menunggu_retur'] > 0],
            ['label' => 'Retur Damaged (30D)', 'value' => number_format($stats['retur_damaged']), 'sub' => 'perlu inspeksi', 'warn' => $stats['retur_damaged'] > 0, 'danger' => true],
        ] as $card)
            <div class="aksana-stat-panel">
                <p class="aksana-stat-label">{{ $card['label'] }}</p>
                <p @class([
                    'aksana-stat-value',
                    'text-[#F59100]' => ($card['warn'] ?? false) && ! ($card['danger'] ?? false),
                    'text-[#F04040]' => ($card['danger'] ?? false) && ($card['warn'] ?? false),
                ])>{{ $card['value'] }}</p>
                <p class="aksana-stat-sub">{{ $card['sub'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="aksana-tabs mb-6 flex flex-wrap gap-2">
        @foreach ([
            'transfer_keluar' => ['icon' => 'heroicon-m-arrow-up-right', 'label' => 'Transfer Keluar'],
            'retur_masuk' => ['icon' => 'heroicon-m-arrow-uturn-left', 'label' => 'Retur Masuk'],
            'lokasi' => ['icon' => 'heroicon-m-map-pin', 'label' => 'Lokasi Penjualan'],
            'riwayat' => ['icon' => 'heroicon-m-arrow-path', 'label' => 'Riwayat'],
        ] as $tab => $meta)
            <button
                type="button"
                wire:click="$set('activeTab', '{{ $tab }}')"
                @class(['aksana-tab', 'aksana-tab-active' => $activeTab === $tab])
            >
                <x-dynamic-component :component="$meta['icon']" class="h-4 w-4" />
                {{ $meta['label'] }}
            </button>
        @endforeach
    </div>

    @if ($activeTab === 'transfer_keluar')
        {{ $this->table }}
    @endif

    @if ($activeTab === 'retur_masuk')
        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="aksana-panel xl:col-span-2 space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h3 class="text-sm font-semibold text-[#070D1E]">Transaksi Retur dari Lokasi Penjualan</h3>
                        <p class="aksana-mono text-xs text-[#49586B]">{{ $returnRef }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div>
                        <label class="aksana-stat-label">Lokasi Asal Retur</label>
                        <select
                            wire:model.live="returnLocationId"
                            class="mt-1 w-full rounded-lg border border-[#D1DAE5] px-3 py-2 text-sm"
                        >
                            <option value="">Pilih lokasi...</option>
                            @foreach (app(\App\Services\DistribusiService::class)->returnSourceLocations() as $loc)
                                <option value="{{ $loc->id }}">{{ $loc->location_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="aksana-stat-label">Tanggal Retur</label>
                        <input
                            type="date"
                            wire:model="returnDate"
                            class="mt-1 w-full rounded-lg border border-[#D1DAE5] px-3 py-2 text-sm aksana-mono"
                        />
                    </div>
                    <div>
                        <label class="aksana-stat-label">PIC Lokasi</label>
                        <input
                            type="text"
                            readonly
                            value="{{ $returnPicName ?? '—' }}"
                            class="mt-1 w-full rounded-lg border border-[#D1DAE5] bg-[#EDF1F3] px-3 py-2 text-sm"
                        />
                    </div>
                </div>

                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <h4 class="text-xs font-bold uppercase tracking-wide text-[#49586B]">Item Sisa Untuk Diretur</h4>
                        <button type="button" class="aksana-tab text-[10px]">
                            <x-heroicon-m-qr-code class="h-4 w-4" /> Scan Barcode
                        </button>
                    </div>

                    @if ($returnLines === [])
                        <p class="text-sm text-[#49586B]">Pilih lokasi untuk memuat daftar item.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="aksana-table w-full text-sm">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Kirim</th>
                                        <th>Terjual</th>
                                        <th>Sisa</th>
                                        <th>✓ Good</th>
                                        <th>✗ Damaged</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($returnLines as $index => $line)
                                        <tr wire:key="return-line-{{ $line['item_id'] }}">
                                            <td>
                                                <div class="font-semibold">{{ $line['item_name'] }}</div>
                                                <div class="aksana-mono text-xs text-[#49586B]">{{ $line['sku'] }}</div>
                                            </td>
                                            <td class="aksana-mono">{{ $line['kirim'] }}</td>
                                            <td class="aksana-mono">{{ $line['terjual'] }}</td>
                                            <td class="aksana-mono font-bold">{{ $line['sisa'] }}</td>
                                            <td>
                                                <div class="flex items-center gap-1">
                                                    <span class="text-[#29A85A]">✓</span>
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        max="{{ $line['sisa'] }}"
                                                        wire:model.live="returnLines.{{ $index }}.qty_good"
                                                        class="w-12 rounded border border-[#D1DAE5] px-1 py-1 text-center aksana-mono text-sm"
                                                    />
                                                </div>
                                            </td>
                                            <td>
                                                <div class="flex items-center gap-1">
                                                    <span class="text-[#F04040]">✗</span>
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        max="{{ $line['sisa'] }}"
                                                        wire:model.live="returnLines.{{ $index }}.qty_damaged"
                                                        class="w-12 rounded border border-[#D1DAE5] px-1 py-1 text-center aksana-mono text-sm"
                                                    />
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <div>
                    <label class="aksana-stat-label">Catatan Retur</label>
                    <textarea
                        wire:model="returnNote"
                        rows="3"
                        class="mt-1 w-full rounded-lg border border-[#D1DAE5] px-3 py-2 text-sm"
                        placeholder="Catatan tambahan..."
                    ></textarea>
                </div>

                <div class="flex flex-wrap gap-3 justify-end">
                    <button type="button" wire:click="resetReturnForm" class="aksana-tab">Batal</button>
                    <button type="button" wire:click="saveReturn" class="aksana-tab aksana-tab-active">
                        ↗ Simpan Retur
                    </button>
                </div>
            </div>

            @php $summary = $this->getReturnSummary(); @endphp
            <div class="aksana-panel space-y-4">
                <h3 class="aksana-panel-title">Ringkasan Retur</h3>
                <p class="text-sm font-semibold">{{ $this->getReturnLocationName() ?? '—' }}</p>

                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-lg border border-[#29A85A]/30 bg-[#29A85A]/10 p-3">
                        <p class="text-xs font-bold text-[#29A85A]">✓ GOOD</p>
                        <p class="aksana-stat-value text-2xl text-[#29A85A]">{{ $summary['good'] }}</p>
                        <p class="text-xs text-[#49586B]">kembali ke gudang</p>
                    </div>
                    <div class="rounded-lg border border-[#F04040]/30 bg-[#F04040]/10 p-3">
                        <p class="text-xs font-bold text-[#F04040]">✗ DAMAGED</p>
                        <p class="aksana-stat-value text-2xl text-[#F04040]">{{ $summary['damaged'] }}</p>
                        <p class="text-xs text-[#49586B]">menunggu inspeksi</p>
                    </div>
                </div>

                <div class="space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-[#49586B]">Total Dikirim</span><span class="aksana-mono font-semibold">{{ $summary['kirim'] }}</span></div>
                    <div class="flex justify-between"><span class="text-[#49586B]">Terjual</span><span class="aksana-mono font-semibold">{{ $summary['terjual'] }}</span></div>
                    <div class="flex justify-between border-t border-[#D1DAE5] pt-2"><span class="font-semibold">Diretur</span><span class="aksana-mono font-bold">{{ $summary['diretur'] }}</span></div>
                </div>
            </div>
        </div>
    @endif

    @if ($activeTab === 'lokasi')
        <div class="mb-4 flex justify-end">
            <button type="button" class="aksana-tab aksana-tab-active text-[10px]">+ Lokasi Baru</button>
        </div>
        <div class="aksana-panel overflow-x-auto">
            <table class="aksana-table w-full text-sm">
                <thead>
                    <tr>
                        <th>Lokasi</th>
                        <th>Tipe</th>
                        <th>Periode Aktif</th>
                        <th>PIC</th>
                        <th>Dikirim</th>
                        <th>Sisa</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->getLocationRows() as $row)
                        <tr>
                            <td class="font-semibold">{{ $row['location_name'] }}</td>
                            <td><span class="rounded-full border border-[#D1DAE5] bg-[#DDE4EC]/60 px-2 py-0.5 text-[9px] font-bold uppercase">{{ $row['location_type_label'] }}</span></td>
                            <td class="text-xs">
                                @if ($row['start_date'] || $row['end_date'])
                                    {{ $row['start_date'] ?? '—' }} – {{ $row['end_date'] ?? '—' }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $row['pic_name'] }}</td>
                            <td class="aksana-mono">{{ $row['dikirim'] }}</td>
                            <td class="aksana-mono font-bold">{{ $row['sisa'] }}</td>
                            <td>
                                @php $st = $row['status']; @endphp
                                <span @class([
                                    'rounded-full px-2 py-0.5 text-[9px] font-bold uppercase',
                                    'border border-[#29A85A]/30 bg-[#29A85A]/15 text-[#1d7a42]' => $st['color'] === 'success',
                                    'border border-[#F59100]/30 bg-[#F59100]/15 text-[#b45309]' => $st['color'] === 'warning',
                                    'border border-[#D1DAE5] bg-[#DDE4EC]/60 text-[#49586B]' => $st['color'] === 'gray',
                                ])>{{ $st['label'] }}</span>
                            </td>
                            <td>
                                <button
                                    type="button"
                                    wire:click="goToRetur('{{ $row['id'] }}')"
                                    class="aksana-tab aksana-tab-active text-[9px] mr-1"
                                >↩ Retur</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($activeTab === 'riwayat')
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <input type="date" wire:model.live="historyDateFrom" class="rounded-lg border border-[#D1DAE5] px-2 py-1 text-sm aksana-mono" />
                <span class="text-[#49586B]">—</span>
                <input type="date" wire:model.live="historyDateTo" class="rounded-lg border border-[#D1DAE5] px-2 py-1 text-sm aksana-mono" />
            </div>
            <span class="text-xs text-[#49586B] aksana-mono">7 hari terakhir</span>
        </div>
        {{ $this->table }}
    @endif
</x-filament-panels::page>
