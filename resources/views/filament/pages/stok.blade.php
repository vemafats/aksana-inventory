<x-filament-panels::page>
<div class="space-y-4">

{{-- Page subtitle --}}
<p class="text-sm text-gray-500">
    Monitoring level stok · {{ $locationCount ?? 0 }} lokasi
    · {{ $categoryCount ?? 0 }} kategori
</p>

{{-- Sub-tabs --}}
<div class="flex gap-1 border-b border-gray-200 pb-0">
    @foreach([
        'ringkasan' => ['label' => 'RINGKASAN', 'icon' => 'heroicon-o-chart-bar'],
        'tambah-stok' => ['label' => '+ TAMBAH STOK', 'icon' => 'heroicon-o-plus'],
        'riwayat-pergerakan' => ['label' => 'RIWAYAT PERGERAKAN', 'icon' => 'heroicon-o-arrow-path'],
        'harga-jual' => ['label' => 'HARGA JUAL DASAR', 'icon' => 'heroicon-o-tag'],
    ] as $key => $tab)
    <button
        wire:click="selectTab('{{ $key }}')"
        class="px-4 py-2 text-xs font-bold uppercase tracking-wide rounded-t-md transition-colors
            {{ $activeTab === $key
                ? 'bg-gray-900 text-white'
                : 'text-gray-500 hover:bg-gray-100' }}">
        {{ $tab['label'] }}
    </button>
    @endforeach
    @if($this->isOwner())
    <button
        wire:click="selectTab('update-harga')"
        style="padding: 8px 16px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; border-radius: 6px 6px 0 0; border: none; cursor: pointer; transition: all 0.15s; {{ $activeTab === 'update-harga' ? 'background: #070D1E; color: white;' : 'background: transparent; color: #49586B;' }}">
        UPDATE HARGA STOK
    </button>
    @endif
</div>

{{-- Tab content --}}
<div class="bg-white rounded-lg border border-gray-200 p-4">

    {{-- RINGKASAN --}}
    @if($activeTab === 'ringkasan')
    <div class="space-y-4">
        {{-- Stat cards --}}
        <div @class([
            'mb-2 grid grid-cols-1 gap-4 md:grid-cols-2',
            'xl:grid-cols-4' => $this->isOwner(),
            'xl:grid-cols-3' => ! $this->isOwner(),
        ])>
            @foreach ($this->getRingkasanStatCards() as $card)
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

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs text-[#3d4a5c]">Cetak label QR langsung ke printer Zebra ZD230 (ZPL).</p>
            <button
                type="button"
                wire:click="openPrintModal"
                class="inline-flex items-center gap-2 rounded-md border border-[var(--aksana-border)] bg-white px-4 py-2 text-[13px] font-semibold text-[var(--aksana-void)] transition hover:bg-gray-50"
            >
                <x-heroicon-o-printer class="h-4 w-4" />
                Cetak QR Code
            </button>
        </div>

        {{-- Total Semua Stok per Item --}}
        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-sm font-semibold">Total Semua Stok per Item</h3>
                <span class="text-xs text-gray-400">{{ $totalSku ?? 0 }} item · {{ ($allLocations ?? collect())->count() }} lokasi</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm min-w-max">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-bold uppercase tracking-wide text-gray-400 sticky left-0 bg-gray-50">ITEM</th>
                            <th class="px-4 py-2 text-left text-xs font-bold uppercase tracking-wide text-gray-400">BARCODE</th>
                            @foreach($allLocations ?? [] as $location)
                            <th class="px-3 py-2 text-right text-xs font-bold uppercase tracking-wide text-gray-400 whitespace-nowrap">
                                {{ \Illuminate\Support\Str::limit(strtoupper($location->location_name), 12, '') }}
                            </th>
                            @endforeach
                            <th class="px-4 py-2 text-right text-xs font-bold uppercase tracking-wide text-gray-400">TOTAL</th>
                            <th class="px-4 py-2 text-center text-xs font-bold uppercase tracking-wide text-gray-400">STATUS</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($stockItems ?? [] as $item)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium sticky left-0 bg-white">{{ $item->item_name }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $item->barcode }}</td>
                            @foreach($allLocations ?? [] as $location)
                            @php($locQty = $this->locationQtyFor($item, $location->id))
                            <td class="px-3 py-3 text-right font-mono text-xs {{ $locQty > 0 ? 'font-bold text-gray-900' : 'text-gray-300' }}">
                                {{ $locQty > 0 ? $locQty : '—' }}
                            </td>
                            @endforeach
                            <td class="px-4 py-3 text-right font-mono font-bold">{{ (int) ($item->total_available ?? 0) }}</td>
                            <td class="px-4 py-3 text-center">
                                @if(($item->total_available ?? 0) == 0)
                                    <span class="px-2 py-1 text-xs font-bold rounded-full bg-red-100 text-red-600">HABIS</span>
                                @elseif(($item->total_available ?? 0) <= 1)
                                    <span class="px-2 py-1 text-xs font-bold rounded-full bg-orange-100 text-orange-600">KRITIS</span>
                                @else
                                    <span class="px-2 py-1 text-xs font-bold rounded-full bg-green-100 text-green-600">AMAN</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="{{ 4 + ($allLocations ?? collect())->count() }}" class="px-4 py-8 text-center text-gray-400 text-sm">Belum ada stok. Lakukan barang masuk terlebih dahulu.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    {{-- TAMBAH STOK --}}
    @if($activeTab === 'tambah-stok')
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        {{-- Left panel --}}
        <div class="xl:col-span-2 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-sm font-semibold text-gray-900">+ Transaksi Penambahan Stok</h3>
                <span class="text-xs font-mono text-gray-400 uppercase">draft · belum tersimpan</span>
            </div>

            @if($stockInSuccess)
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ $stockInSuccess }}</div>
            @endif
            @if($stockInError)
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $stockInError }}</div>
            @endif
            @if($stockInWarning)
            <div class="rounded-lg border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-700">{{ $stockInWarning }}</div>
            @endif

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="text-xs font-bold uppercase tracking-wide text-gray-400">No. Referensi/PO</label>
                    <input type="text" wire:model="poReference" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="PO-2026-001">
                </div>
                <div>
                    <label class="text-xs font-bold uppercase tracking-wide text-gray-400">Tanggal Masuk</label>
                    <input type="date" wire:model="transactionDate" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono">
                </div>
                <div>
                    <label class="text-xs font-bold uppercase tracking-wide text-gray-400">Nama Supplier</label>
                    <input type="text" wire:model="supplierName" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="Opsional">
                </div>
                <div>
                    <label class="text-xs font-bold uppercase tracking-wide text-gray-400">Lokasi Tujuan</label>
                    <input type="text" readonly value="{{ $centralWarehouse->location_name ?? 'Gudang Pusat' }}" class="mt-1 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm">
                </div>
            </div>

            <div class="space-y-3">
                <h4 class="text-xs font-bold uppercase tracking-wide text-gray-500">Item Diterima</h4>

                @forelse($stockInItems as $index => $row)
                <div wire:key="stock-in-row-{{ $index }}" class="rounded-lg border border-gray-200 p-4 space-y-3 bg-gray-50/50">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-xs font-bold text-gray-500">Item #{{ $index + 1 }}</p>
                        <button type="button" wire:click="removeStockInItem({{ $index }})" class="text-red-500 hover:text-red-700">
                            <x-heroicon-o-trash class="w-4 h-4" />
                        </button>
                    </div>

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label class="text-xs text-gray-400">Pilih Item</label>
                            <select
                                wire:change="updateStockInItem({{ $index }}, 'item_id', $event.target.value)"
                                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                            >
                                <option value="">— Pilih item katalog —</option>
                                @foreach($catalogItems ?? [] as $catalogItem)
                                <option value="{{ $catalogItem->id }}" @selected(($row['item_id'] ?? '') === $catalogItem->id)>
                                    {{ $catalogItem->item_name }} ({{ $catalogItem->barcode }})
                                </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="text-xs text-gray-400">Qty Diterima</label>
                            <input type="number" min="1" value="{{ $row['qty_received'] ?? 1 }}"
                                wire:change="updateStockInItem({{ $index }}, 'qty_received', $event.target.value)"
                                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono">
                        </div>
                        <div>
                            <label class="text-xs text-gray-400">Qty Available</label>
                            <input type="number" min="0" value="{{ $row['qty_available'] ?? 0 }}"
                                wire:change="updateStockInItem({{ $index }}, 'qty_available', $event.target.value)"
                                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono">
                        </div>
                        <div>
                            <label class="text-xs text-gray-400">Qty Damaged (auto)</label>
                            <input type="number" readonly value="{{ $row['qty_damaged'] ?? 0 }}"
                                class="mt-1 w-full rounded-lg border border-gray-200 bg-gray-100 px-3 py-2 text-sm font-mono text-orange-600">
                        </div>
                        <div>
                            <label class="text-xs text-gray-400">Harga Modal (Rp)</label>
                            <input type="number" min="0" step="1" value="{{ $row['supplier_cost'] ?? 0 }}"
                                wire:change="updateStockInItem({{ $index }}, 'supplier_cost', $event.target.value)"
                                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono">
                        </div>
                        <div>
                            <label class="text-xs text-gray-400">Tipe Margin</label>
                            <select
                                wire:change="updateStockInItem({{ $index }}, 'margin_type', $event.target.value)"
                                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                            >
                                <option value="none" @selected(($row['margin_type'] ?? '') === 'none')>Tanpa Margin</option>
                                <option value="nominal" @selected(($row['margin_type'] ?? 'nominal') === 'nominal')>Nominal (Rp)</option>
                                <option value="percentage" @selected(($row['margin_type'] ?? '') === 'percentage')>Persentase (%)</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-gray-400">Nilai Margin</label>
                            <input type="number" min="0" step="1" value="{{ $row['margin_value'] ?? 0 }}"
                                wire:change="updateStockInItem({{ $index }}, 'margin_value', $event.target.value)"
                                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono">
                        </div>
                        <div>
                            <label class="text-xs text-gray-400">Harga Jual Dasar (auto)</label>
                            <input type="text" readonly
                                value="Rp {{ number_format($row['calculated_selling_price'] ?? 0, 0, ',', '.') }}"
                                class="mt-1 w-full rounded-lg border border-gray-200 bg-gray-100 px-3 py-2 text-sm font-mono font-bold">
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-xs text-gray-400">Catatan QC</label>
                            <input type="text" value="{{ $row['qc_note'] ?? '' }}"
                                wire:change="updateStockInItem({{ $index }}, 'qc_note', $event.target.value)"
                                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="Opsional">
                        </div>
                    </div>
                </div>
                @empty
                <p class="text-sm text-gray-400 py-4 text-center">Belum ada item. Klik "+ TAMBAH ITEM" untuk memulai.</p>
                @endforelse

                <button type="button" wire:click="addStockInItem"
                    class="w-full rounded-lg border border-dashed border-gray-300 py-2 text-xs font-bold uppercase tracking-wide text-gray-600 hover:bg-gray-50">
                    + TAMBAH ITEM
                </button>
            </div>

            {{-- F003: Photo upload --}}
            <div class="rounded-lg border border-gray-200 p-4 space-y-3">
                <label class="text-xs font-bold uppercase tracking-wide text-gray-500">Upload Foto QC</label>
                <p class="text-xs text-gray-400">JPG/PNG, maks. 5MB (opsional)</p>

                @if($stockInPhotoPreview)
                <div class="flex items-center gap-3">
                    <img src="{{ $stockInPhotoPreview }}" alt="Preview QC" class="h-20 w-20 rounded-lg object-cover border border-gray-200">
                    <button type="button" wire:click="removeStockInPhoto" class="text-xs font-bold text-red-600 uppercase">Hapus Foto</button>
                </div>
                @else
                <div class="flex flex-wrap items-center gap-3">
                    <input type="file" accept="image/jpeg,image/png,image/jpg" wire:model="stockInPhoto"
                        class="text-sm text-gray-600">
                    <button type="button" wire:click="uploadStockInPhoto" wire:loading.attr="disabled"
                        class="px-3 py-1.5 text-xs font-bold uppercase tracking-wide rounded border border-gray-300 hover:bg-gray-50">
                        <span wire:loading.remove wire:target="uploadStockInPhoto,stockInPhoto">Upload</span>
                        <span wire:loading wire:target="uploadStockInPhoto,stockInPhoto">Mengupload...</span>
                    </button>
                </div>
                @endif
            </div>
        </div>

        {{-- Right panel: Riwayat Penambahan Stok --}}
        <div class="space-y-4">
            <h3 class="text-sm font-semibold text-gray-900">Riwayat Penambahan Stok</h3>

            @if($editStockInSuccess)
            <div class="p-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm">
                ✓ {{ $editStockInSuccess }}
            </div>
            @endif

            @if($editStockInError && !$editingStockInId)
            <div class="p-3 bg-red-50 border border-red-200 rounded-lg text-red-600 text-sm">
                ✗ {{ $editStockInError }}
            </div>
            @endif

            <div class="overflow-x-auto rounded-lg border border-gray-200">
                <table class="w-full text-xs">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-bold uppercase tracking-wide text-gray-400">Tanggal</th>
                            <th class="px-3 py-2 text-left font-bold uppercase tracking-wide text-gray-400">No. Ref</th>
                            <th class="px-3 py-2 text-right font-bold uppercase tracking-wide text-gray-400">Qty</th>
                            <th class="px-3 py-2 text-left font-bold uppercase tracking-wide text-gray-400">Harga</th>
                            @if($this->canEditStockInPrice())
                            <th class="px-3 py-2 text-right font-bold uppercase tracking-wide text-gray-400">Aksi</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($recentStockIns ?? [] as $recent)
                        <tr wire:key="recent-stock-in-{{ $recent->id }}" class="hover:bg-gray-50/50">
                            <td class="px-3 py-2 font-mono text-gray-600">{{ $recent->transaction_date?->format('d/m/y') }}</td>
                            <td class="px-3 py-2">
                                <span class="font-mono font-bold text-gray-900">{{ $recent->transaction_number }}</span>
                            </td>
                            <td class="px-3 py-2 text-right font-mono">{{ $recent->total_qty_received }}</td>
                            <td class="px-3 py-2">
                                @if($this->stockInHasMissingPrice($recent))
                                <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-800">
                                    Harga Belum Diisi
                                </span>
                                @else
                                <span class="text-gray-500">Lengkap</span>
                                @endif
                            </td>
                            @if($this->canEditStockInPrice())
                            <td class="px-3 py-2 text-right">
                                <button type="button" wire:click="selectStockInForEdit('{{ $recent->id }}')"
                                    class="text-[10px] font-bold uppercase tracking-wide text-gray-900 hover:underline">
                                    Edit Harga
                                </button>
                            </td>
                            @endif
                        </tr>
                        @empty
                        <tr>
                            <td colspan="{{ $this->canEditStockInPrice() ? 5 : 4 }}" class="px-3 py-6 text-center text-gray-400">
                                Belum ada transaksi barang masuk.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($editingStockInId && $this->canEditStockInPrice())
            @php($editingTransaction = $this->getEditingStockInTransaction())
            @php($editingItem = $this->getEditingStockInItem())
            <div class="rounded-lg border border-gray-200 p-4 space-y-3 bg-gray-50/50">
                <div class="flex items-center justify-between">
                    <h4 class="text-xs font-bold uppercase tracking-wide text-gray-700">Edit Harga Barang Masuk</h4>
                    <button type="button" wire:click="cancelStockInPriceEdit" class="text-xs text-gray-500 hover:text-gray-700">Tutup</button>
                </div>

                @if($editStockInError)
                <div class="p-2 bg-red-50 border border-red-200 rounded text-red-600 text-xs">
                    ✗ {{ $editStockInError }}
                </div>
                @endif

                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div>
                        <span class="text-gray-400">Tanggal</span>
                        <p class="font-mono">{{ $editingTransaction?->transaction_date?->format('d M Y') ?? '—' }}</p>
                    </div>
                    <div>
                        <span class="text-gray-400">No. Ref</span>
                        <p class="font-mono font-bold">{{ $editingTransaction?->transaction_number ?? '—' }}</p>
                    </div>
                    <div class="col-span-2">
                        <span class="text-gray-400">Item</span>
                        <p class="font-semibold">{{ $editingItem?->item?->item_name ?? '—' }}</p>
                    </div>
                    <div>
                        <span class="text-gray-400">Qty</span>
                        <p class="font-mono">{{ $editingItem?->qty_received ?? 0 }}</p>
                    </div>
                </div>

                <div>
                    <label class="text-xs text-gray-400">Harga Modal (Rp)</label>
                    <input type="number" min="0" step="1000"
                        wire:model.lazy="editSupplierCost"
                        wire:change="recalculateEditPrice"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono">
                </div>
                <div>
                    <label class="text-xs text-gray-400">Tipe Margin</label>
                    <select wire:model.lazy="editMarginType"
                        wire:change="recalculateEditPrice"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        <option value="none">Tanpa Margin</option>
                        <option value="nominal">Nominal (Rp)</option>
                        <option value="percentage">Persentase (%)</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs text-gray-400">Nilai Margin</label>
                    <input type="number" min="0" step="1"
                        wire:model.lazy="editMarginValue"
                        wire:change="recalculateEditPrice"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono">
                </div>
                <div>
                    <label class="text-xs text-gray-400">Harga Jual Dasar (auto)</label>
                    <input type="text" readonly
                        value="Rp {{ number_format($editCalculatedPrice, 0, ',', '.') }}"
                        class="mt-1 w-full rounded-lg border border-gray-200 bg-gray-100 px-3 py-2 text-sm font-mono font-bold">
                </div>
                <div>
                    <label class="text-xs text-gray-400">Catatan QC</label>
                    <input type="text" wire:model="editQcNote"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="Opsional">
                </div>
                <button type="button" wire:click="saveStockInPrice" wire:loading.attr="disabled"
                    class="w-full px-6 py-2 text-sm font-bold uppercase bg-gray-900 text-white rounded-lg hover:opacity-90 disabled:opacity-50"
                    style="background-color:#111827;color:#fff;">
                    <span wire:loading.remove wire:target="saveStockInPrice">SIMPAN HARGA</span>
                    <span wire:loading wire:target="saveStockInPrice">Menyimpan...</span>
                </button>
            </div>
            @endif
        </div>
    </div>

    <p class="text-sm text-gray-500 font-mono">
        Total: {{ $this->getTotalQty() }} unit ·
        Nilai: Rp {{ number_format($this->getTotalModal(), 0, ',', '.') }}
    </p>

    <div class="flex flex-wrap justify-end gap-3 mt-6 pt-4 border-t border-gray-200">
        <button
            wire:click="resetStockInForm"
            type="button"
            class="shrink-0 px-4 py-2 text-sm border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50">
            BATAL
        </button>
        <button
            wire:click="submitStockIn"
            type="button"
            class="shrink-0 px-6 py-2 text-sm font-bold uppercase bg-gray-900 text-white rounded-lg hover:opacity-90"
            style="background-color:#111827;color:#fff;">
            ✓ SIMPAN STOK MASUK
        </button>
    </div>

    @if($stockInSuccess)
    <div class="p-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm mt-4">
        ✓ {{ $stockInSuccess }}
    </div>
    @endif

    @if($stockInError)
    <div class="p-3 bg-red-50 border border-red-200 rounded-lg text-red-600 text-sm mt-4">
        ✗ {{ $stockInError }}
    </div>
    @endif
    @endif

    {{-- RIWAYAT PERGERAKAN --}}
    @if($activeTab === 'riwayat-pergerakan')
    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold">Riwayat Pergerakan Stok</h3>
            <span class="text-xs text-gray-400">30 hari terakhir</span>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-bold uppercase tracking-wide text-gray-400">TANGGAL</th>
                    <th class="px-4 py-2 text-left text-xs font-bold uppercase tracking-wide text-gray-400">TIPE</th>
                    <th class="px-4 py-2 text-left text-xs font-bold uppercase tracking-wide text-gray-400">ITEM</th>
                    <th class="px-4 py-2 text-left text-xs font-bold uppercase tracking-wide text-gray-400">DARI</th>
                    <th class="px-4 py-2 text-left text-xs font-bold uppercase tracking-wide text-gray-400">KE</th>
                    <th class="px-4 py-2 text-right text-xs font-bold uppercase tracking-wide text-gray-400">QTY</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($recentMovements ?? [] as $movement)
                @php($movementMeta = $this->movementTypeMeta($movement))
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-xs text-gray-500 font-mono">
                        {{ $movement->created_at?->format('d M Y · H:i') }}
                    </td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 text-xs font-bold rounded {{ $movementMeta['color'] }}">{{ $movementMeta['label'] }}</span>
                    </td>
                    <td class="px-4 py-3">
                        <p class="font-medium text-xs">{{ $movement->item->item_name ?? '-' }}</p>
                        <p class="text-xs text-gray-400 font-mono">{{ $movement->item->barcode ?? '' }}</p>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-500">{{ $movement->fromLocation->location_name ?? 'Supplier' }}</td>
                    <td class="px-4 py-3 text-xs text-gray-500">{{ $movement->toLocation->location_name ?? 'Penjualan' }}</td>
                    <td class="px-4 py-3 text-right font-mono font-bold {{ $movementMeta['isInbound'] ? 'text-green-600' : 'text-gray-700' }}">
                        {{ $movementMeta['isInbound'] ? '+' : '-' }}{{ $movement->qty }}
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400 text-sm">Belum ada pergerakan stok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @endif

    {{-- HARGA JUAL DASAR --}}
    @if($activeTab === 'harga-jual')
    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-sm font-semibold">Harga Jual per Item</h3>
                <p class="text-xs text-gray-400">jual · diskon · margin</p>
            </div>
            @if(auth()->user()->role === \App\Enums\UserRole::OWNER)
            <button
                wire:click="toggleCostView"
                class="px-3 py-1.5 text-xs font-bold uppercase tracking-wide rounded border
                    {{ $showCost ? 'bg-gray-900 text-white border-gray-900' : 'border-gray-300 text-gray-600 hover:bg-gray-50' }}">
                {{ $showCost ? '🔒 Sembunyikan Modal & Margin' : '👁 Lihat Harga Modal & Margin' }}
            </button>
            @endif
        </div>

        {{-- Password modal for cost view --}}
        @if($showPasswordModal)
        <div style="position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.5);">
            <div style="background: white; border-radius: 12px; padding: 24px; width: 100%; max-width: 360px; margin: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
                <h3 style="font-weight: 700; font-size: 15px; margin: 0 0 4px 0; color: #070D1E;">
                    Verifikasi Identitas
                </h3>
                <p style="font-size: 12px; color: #49586B; margin: 0 0 16px 0;">
                    Masukkan password untuk melihat harga modal dan margin
                </p>

                <input
                    type="password"
                    wire:model="costPassword"
                    wire:keydown.enter="verifyCostPassword"
                    placeholder="Password"
                    style="width: 100%; box-sizing: border-box; border: 1px solid #d1d5db; border-radius: 8px; padding: 10px 12px; font-size: 14px; margin-bottom: 8px; outline: none;">

                @if($passwordError)
                <p style="color: #F04040; font-size: 12px; margin: 0 0 8px 0;">
                    {{ $passwordError }}
                </p>
                @endif

                <div style="display: flex; gap: 8px; margin-top: 8px;">
                    <button
                        wire:click="cancelCostView"
                        type="button"
                        style="flex: 1; padding: 10px 0; border: 1px solid #d1d5db; border-radius: 8px; font-size: 13px; font-weight: 600; background: white; cursor: pointer; color: #49586B;">
                        Batal
                    </button>
                    <button
                        wire:click="verifyCostPassword"
                        type="button"
                        style="flex: 1; padding: 10px 0; border-radius: 8px; font-size: 13px; font-weight: 600; color: white; background: #070D1E; cursor: pointer; border: none;">
                        Verifikasi
                    </button>
                </div>
            </div>
        </div>
        @endif

        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-bold uppercase tracking-wide text-gray-400">ITEM</th>
                    <th class="px-4 py-2 text-left text-xs font-bold uppercase tracking-wide text-gray-400">BARCODE</th>
                    @if($showCost)
                    <th class="px-4 py-2 text-right text-xs font-bold uppercase tracking-wide text-gray-400">MODAL</th>
                    @endif
                    <th class="px-4 py-2 text-right text-xs font-bold uppercase tracking-wide text-gray-400">HARGA JUAL DASAR</th>
                    @if($showCost)
                    <th class="px-4 py-2 text-right text-xs font-bold uppercase tracking-wide text-gray-400">MARGIN</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($priceItems ?? [] as $item)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-xs">{{ $item->item_name }}</td>
                    <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $item->barcode }}</td>
                    @if($showCost)
                    <td class="px-4 py-3 text-right font-mono text-xs text-gray-500">
                        {{ \App\Helpers\FormatHelper::price($item->latest_supplier_cost) }}
                    </td>
                    @endif
                    <td class="px-4 py-3 text-right font-mono text-xs font-bold">
                        {{ \App\Helpers\FormatHelper::price($item->latest_base_selling_price) }}
                    </td>
                    @if($showCost)
                    <td class="px-4 py-3 text-right text-xs font-bold text-green-600">
                        ↗ {{ $this->itemMarginPercent($item) }}%
                    </td>
                    @endif
                </tr>
                @empty
                <tr><td colspan="{{ $showCost ? 5 : 3 }}" class="px-4 py-8 text-center text-gray-400 text-sm">Belum ada data harga.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @endif

    {{-- UPDATE HARGA STOK (Owner only) --}}
    @if($activeTab === 'update-harga' && $this->isOwner())
    <div>
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">
            <div>
                <h3 style="font-size:14px; font-weight:700; color:#070D1E; margin:0;">Update Harga Stok</h3>
                <p style="font-size:12px; color:#49586B; margin:4px 0 0;">Update harga modal untuk stok yang masuk via mobile</p>
            </div>
            <div style="display:flex; gap:8px; align-items:center;">
                <span style="font-size:11px; color:#49586B; background:#F3F4F6; padding:4px 10px; border-radius:20px; font-family:monospace;">
                    {{ ($stockInHistory ?? collect())->count() }} transaksi
                </span>
            </div>
        </div>

        @if($editStockInSuccess)
        <div style="padding:12px; background:#D1FAE5; border:1px solid #A7F3D0; border-radius:8px; color:#059669; font-size:13px; margin-bottom:12px;">
            ✓ {{ $editStockInSuccess }}
        </div>
        @endif

        @if($editStockInError)
        <div style="padding:12px; background:#FEE2E2; border:1px solid #FECACA; border-radius:8px; color:#DC2626; font-size:13px; margin-bottom:12px;">
            ✗ {{ $editStockInError }}
        </div>
        @endif

        <div style="display:flex; gap:12px; margin-bottom:12px;">
            <div style="display:flex; align-items:center; gap:6px;">
                <span style="width:8px; height:8px; border-radius:50%; background:#F59100; display:inline-block;"></span>
                <span style="font-size:11px; color:#49586B;">Harga belum diisi</span>
            </div>
            <div style="display:flex; align-items:center; gap:6px;">
                <span style="width:8px; height:8px; border-radius:50%; background:#29A85A; display:inline-block;"></span>
                <span style="font-size:11px; color:#49586B;">Harga sudah lengkap</span>
            </div>
        </div>

        <div style="border:1px solid #D1DAE5; border-radius:12px; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:13px;">
                <thead>
                    <tr style="background:#F8F9FB;">
                        <th style="padding:10px 16px; text-align:left; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:#49586B; border-bottom:1px solid #D1DAE5;">TANGGAL</th>
                        <th style="padding:10px 16px; text-align:left; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:#49586B; border-bottom:1px solid #D1DAE5;">NO. REF</th>
                        <th style="padding:10px 16px; text-align:left; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:#49586B; border-bottom:1px solid #D1DAE5;">ITEM</th>
                        <th style="padding:10px 16px; text-align:right; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:#49586B; border-bottom:1px solid #D1DAE5;">QTY</th>
                        <th style="padding:10px 16px; text-align:center; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:#49586B; border-bottom:1px solid #D1DAE5;">STATUS HARGA</th>
                        <th style="padding:10px 16px; text-align:left; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:#49586B; border-bottom:1px solid #D1DAE5;">DIINPUT OLEH</th>
                        <th style="padding:10px 16px; text-align:center; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:#49586B; border-bottom:1px solid #D1DAE5;">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($stockInHistory ?? [] as $trx)
                    <tr style="border-bottom:1px solid #F3F4F6; {{ $loop->last ? 'border-bottom:none;' : '' }}">
                        <td style="padding:12px 16px; font-family:monospace; font-size:12px; color:#49586B;">
                            {{ $trx->transaction_date?->timezone('Asia/Jakarta')->format('d M Y') ?? '—' }}
                        </td>
                        <td style="padding:12px 16px; font-family:monospace; font-size:12px; color:#070D1E; font-weight:600;">
                            {{ $trx->transaction_number ?? '-' }}
                        </td>
                        <td style="padding:12px 16px;">
                            @foreach($trx->items as $item)
                            <div style="font-size:12px; font-weight:600; color:#070D1E;">{{ $item->item->item_name ?? '-' }}</div>
                            <div style="font-size:11px; font-family:monospace; color:#49586B;">{{ $item->item->barcode ?? '-' }}</div>
                            @endforeach
                        </td>
                        <td style="padding:12px 16px; text-align:right; font-family:monospace; font-weight:700; color:#070D1E;">
                            {{ $trx->total_qty_received ?? $trx->items->sum('qty_received') }}
                        </td>
                        <td style="padding:12px 16px; text-align:center;">
                            @if($trx->has_unpriced_items)
                            <span style="background:#FEF3C7; color:#D97706; font-size:10px; font-weight:700; padding:3px 10px; border-radius:20px; letter-spacing:0.05em;">⚠ HARGA BELUM DIISI</span>
                            @else
                            <span style="background:#D1FAE5; color:#059669; font-size:10px; font-weight:700; padding:3px 10px; border-radius:20px; letter-spacing:0.05em;">✓ LENGKAP</span>
                            @endif
                        </td>
                        <td style="padding:12px 16px; font-size:12px; color:#49586B;">{{ $trx->createdBy->name ?? '-' }}</td>
                        <td style="padding:12px 16px; text-align:center;">
                            <button wire:click="selectStockInForEdit('{{ $trx->id }}')" type="button"
                                style="padding:6px 14px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; background:#070D1E; color:white; border:none; border-radius:6px; cursor:pointer;">
                                EDIT HARGA
                            </button>
                        </td>
                    </tr>

                    @if($editingStockInId === $trx->id)
                    <tr style="background:#F8F9FB;">
                        <td colspan="7" style="padding:16px;">
                            <div style="background:white; border:1px solid #D1DAE5; border-radius:10px; padding:16px;">
                                <h4 style="font-size:13px; font-weight:700; color:#070D1E; margin:0 0 12px;">
                                    Edit Harga — {{ $trx->items->first()?->item?->item_name }}
                                </h4>
                                <div style="display:grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap:12px; align-items:end;">
                                    <div>
                                        <label style="font-size:10px; font-weight:700; color:#49586B; text-transform:uppercase; letter-spacing:0.08em; display:block; margin-bottom:6px;">Harga Modal (Rp)</label>
                                        <input wire:model.lazy="editSupplierCost" wire:change="recalculateEditPrice" type="number" min="0" step="1000"
                                            style="width:100%; padding:8px 12px; border:1px solid #D1DAE5; border-radius:8px; font-size:13px; box-sizing:border-box;">
                                    </div>
                                    <div>
                                        <label style="font-size:10px; font-weight:700; color:#49586B; text-transform:uppercase; letter-spacing:0.08em; display:block; margin-bottom:6px;">Tipe Margin</label>
                                        <select wire:model="editMarginType" wire:change="recalculateEditPrice"
                                            style="width:100%; padding:8px 12px; border:1px solid #D1DAE5; border-radius:8px; font-size:13px; box-sizing:border-box; background:white;">
                                            <option value="percentage">Persentase (%)</option>
                                            <option value="nominal">Nominal (Rp)</option>
                                            <option value="none">Tanpa Margin</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="font-size:10px; font-weight:700; color:#49586B; text-transform:uppercase; letter-spacing:0.08em; display:block; margin-bottom:6px;">Nilai Margin</label>
                                        <input wire:model.lazy="editMarginValue" wire:change="recalculateEditPrice" type="number" min="0"
                                            style="width:100%; padding:8px 12px; border:1px solid #D1DAE5; border-radius:8px; font-size:13px; box-sizing:border-box;">
                                    </div>
                                    <div>
                                        <label style="font-size:10px; font-weight:700; color:#49586B; text-transform:uppercase; letter-spacing:0.08em; display:block; margin-bottom:6px;">Harga Jual Dasar (Auto)</label>
                                        <div style="padding:8px 12px; background:#F3F4F6; border:1px solid #D1DAE5; border-radius:8px; font-size:13px; font-family:monospace; font-weight:700; color:#070D1E;">
                                            Rp {{ number_format($editCalculatedPrice, 0, ',', '.') }}
                                        </div>
                                    </div>
                                </div>
                                <div style="margin-top:12px;">
                                    <label style="font-size:10px; font-weight:700; color:#49586B; text-transform:uppercase; letter-spacing:0.08em; display:block; margin-bottom:6px;">Catatan QC</label>
                                    <input wire:model="editQcNote" type="text" placeholder="Opsional"
                                        style="width:100%; padding:8px 12px; border:1px solid #D1DAE5; border-radius:8px; font-size:13px; box-sizing:border-box;">
                                </div>
                                <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:16px;">
                                    <button wire:click="cancelStockInPriceEdit" type="button"
                                        style="padding:8px 16px; border:1px solid #D1DAE5; border-radius:8px; font-size:12px; font-weight:700; background:white; cursor:pointer; color:#49586B; text-transform:uppercase;">
                                        BATAL
                                    </button>
                                    <button wire:click="saveStockInPrice" type="button"
                                        style="padding:8px 20px; background:#070D1E; color:white; border:none; border-radius:8px; font-size:12px; font-weight:700; cursor:pointer; text-transform:uppercase;">
                                        SIMPAN HARGA
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endif

                    @empty
                    <tr>
                        <td colspan="7" style="padding:32px; text-align:center; color:#49586B; font-size:13px;">
                            Belum ada riwayat penambahan stok.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

</div>
</div>

@if ($showPrintModal)
    <div
        class="fixed inset-0 z-[100] flex items-center justify-center p-4"
        wire:keydown.escape.window="closePrintModal"
    >
        <div class="absolute inset-0 bg-black/50" wire:click="closePrintModal"></div>

        <div
            class="relative z-10 w-full max-w-md rounded-xl border border-[var(--aksana-border)] bg-white p-6 shadow-xl"
            wire:ignore
            x-data="printLabelModal()"
            @click.stop
        >
            <div class="mb-5 flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-base font-bold text-[var(--aksana-void)]">Cetak Label QR Code</h3>
                    <p class="mt-1 text-[13px] text-[#3d4a5c]">Zebra ZD230 · Browser Print (localhost:9100)</p>
                </div>
                <button
                    type="button"
                    @click="$wire.closePrintModal()"
                    class="flex h-8 w-8 items-center justify-center rounded-md border border-[var(--aksana-border)] text-[var(--aksana-muted)] hover:bg-gray-50"
                    title="Tutup"
                >
                    <x-heroicon-o-x-mark class="h-4 w-4" />
                </button>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="mb-1.5 block text-[11px] font-bold uppercase tracking-[0.08em] text-[#3d4a5c]">Item</label>
                    <select
                        x-ref="itemSelect"
                        x-model="selectedBarcode"
                        class="w-full rounded-md border border-[var(--aksana-border)] px-3 py-2.5 text-[13px] text-[var(--aksana-void)]"
                    >
                        <option value="">-- Pilih Item --</option>
                        @foreach ($printableItems ?? [] as $item)
                            <option value="{{ $item->barcode }}">{{ $item->item_name }} ({{ $item->barcode }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-[11px] font-bold uppercase tracking-[0.08em] text-[#3d4a5c]">Ukuran Label</label>
                    <div class="flex flex-wrap gap-4 text-[13px] text-[var(--aksana-void)]">
                        <label class="inline-flex items-center gap-2">
                            <input type="radio" x-model="labelSize" value="40x20" class="rounded-full border-gray-300">
                            40 × 20 mm
                        </label>
                        <label class="inline-flex items-center gap-2">
                            <input type="radio" x-model="labelSize" value="50x25" class="rounded-full border-gray-300">
                            50 × 25 mm
                        </label>
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-[11px] font-bold uppercase tracking-[0.08em] text-[#3d4a5c]">Jumlah Cetak</label>
                    <input
                        type="number"
                        x-model.number="qty"
                        min="1"
                        max="100"
                        class="w-full rounded-md border border-[var(--aksana-border)] px-3 py-2.5 text-[13px] text-[var(--aksana-void)]"
                    >
                </div>

                <div
                    x-show="selectedBarcode"
                    x-cloak
                    class="rounded-lg border border-[var(--aksana-border)] bg-[#f8f9fb] px-4 py-3 text-[13px] text-[#3d4a5c]"
                >
                    <p class="font-semibold text-[var(--aksana-void)]">Preview</p>
                    <p class="mt-1"><span class="font-medium">Kode item:</span> <span x-text="selectedBarcode" class="font-mono"></span></p>
                    <p><span class="font-medium">QR data:</span> <span x-text="selectedBarcode" class="font-mono"></span></p>
                    <p><span class="font-medium">Ukuran:</span> <span x-text="labelSize === '40x20' ? '40 × 20 mm' : '50 × 25 mm'"></span></p>
                    <p><span class="font-medium">Qty:</span> <span x-text="qty"></span> lembar</p>
                </div>

                <div class="mt-4 flex gap-3">
                    <button
                        type="button"
                        @click="$wire.closePrintModal()"
                        style="flex:1; padding:12px; border:1px solid #d1d5db; border-radius:8px; background:white; cursor:pointer; font-size:13px; font-weight:600; color:#49586B;"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="printLabel()"
                        :disabled="!selectedBarcode || printing"
                        style="flex:1; padding:12px; border-radius:8px; color:white; font-size:13px; font-weight:600; cursor:pointer; border:none; display:inline-flex; align-items:center; justify-content:center; gap:6px;"
                        :style="!selectedBarcode || printing ? 'background:#9ca3af; cursor:not-allowed;' : 'background:#070D1E;'"
                    >
                        <x-heroicon-o-printer class="h-4 w-4" style="color:white;" />
                        <span x-text="printing ? 'Mencetak...' : 'CETAK'"></span>
                    </button>
                </div>

                <div
                    x-show="status"
                    x-cloak
                    x-text="status"
                    :class="statusClass"
                    class="mt-3 text-center text-sm"
                ></div>
            </div>
        </div>
    </div>
@endif

<script src="{{ asset('js/zebra-print-label.js') }}"></script>
</x-filament-panels::page>
