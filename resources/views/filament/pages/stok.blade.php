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
</div>

{{-- Tab content --}}
<div class="bg-white rounded-lg border border-gray-200 p-4">

    {{-- RINGKASAN --}}
    @if($activeTab === 'ringkasan')
    <div class="space-y-4">
        {{-- Stat cards --}}
        <div class="grid grid-cols-4 gap-4">
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <p class="text-xs font-bold uppercase tracking-widest text-gray-400">TOTAL SKU</p>
                <p class="text-3xl font-bold font-mono mt-1">{{ $totalSku ?? 0 }}</p>
                <p class="text-xs font-mono text-gray-400 mt-1">katalog aktif</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <p class="text-xs font-bold uppercase tracking-widest text-gray-400">TOTAL UNIT STOK</p>
                <p class="text-3xl font-bold font-mono mt-1">{{ number_format($totalUnits ?? 0) }}</p>
                <p class="text-xs font-mono text-gray-400 mt-1">across {{ $locationCount ?? 0 }} lokasi</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <p class="text-xs font-bold uppercase tracking-widest text-gray-400">NILAI INVENTORY</p>
                <p class="text-3xl font-bold font-mono mt-1">Rp {{ number_format(($totalCapitalValue ?? 0)/1000000, 1) }}M</p>
                <p class="text-xs font-mono text-gray-400 mt-1">harga modal</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <p class="text-xs font-bold uppercase tracking-widest text-gray-400">STOK KRITIS</p>
                <p class="text-3xl font-bold font-mono mt-1 {{ ($lowStockCount ?? 0) > 0 ? 'text-red-500' : '' }}">
                    {{ $lowStockCount ?? 0 }}
                </p>
                <p class="text-xs font-mono text-gray-400 mt-1">di bawah min</p>
            </div>
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
                        @php
                            $locationQtyMap = collect($item->per_location ?? [])->keyBy('location_id');
                            $rowTotal = (int) ($item->total_available ?? 0);
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium sticky left-0 bg-white">{{ $item->item_name }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $item->barcode }}</td>
                            @foreach($allLocations ?? [] as $location)
                            @php
                                $locQty = (int) ($locationQtyMap->get($location->id)['qty'] ?? 0);
                            @endphp
                            <td class="px-3 py-3 text-right font-mono text-xs {{ $locQty > 0 ? 'font-bold text-gray-900' : 'text-gray-300' }}">
                                {{ $locQty > 0 ? $locQty : '—' }}
                            </td>
                            @endforeach
                            <td class="px-4 py-3 text-right font-mono font-bold">{{ $rowTotal }}</td>
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

            <div class="flex flex-wrap items-center justify-between gap-4 border-t border-gray-100 pt-4">
                <div class="text-sm">
                    <span class="text-gray-500">TOTAL QTY:</span>
                    <span class="font-mono font-bold ml-1">{{ $this->getTotalQty() }} unit</span>
                    <span class="text-gray-300 mx-2">|</span>
                    <span class="text-gray-500">TOTAL NILAI MODAL:</span>
                    <span class="font-mono font-bold ml-1">Rp {{ number_format($this->getTotalModal(), 0, ',', '.') }}</span>
                </div>
                <div class="flex gap-2">
                    <button type="button" wire:click="resetStockInForm"
                        class="px-4 py-2 text-xs font-bold uppercase tracking-wide rounded border border-gray-300 text-gray-600 hover:bg-gray-50">
                        Batal
                    </button>
                    <button type="button" wire:click="submitStockIn" wire:loading.attr="disabled"
                        class="px-4 py-2 text-xs font-bold uppercase tracking-wide rounded bg-gray-900 text-white hover:bg-gray-800 disabled:opacity-50">
                        <span wire:loading.remove wire:target="submitStockIn">Simpan Stok Masuk</span>
                        <span wire:loading wire:target="submitStockIn">Menyimpan...</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Right panel --}}
        <div class="space-y-3">
            <h3 class="text-sm font-semibold text-gray-900">Penambahan Terbaru</h3>
            <div class="space-y-2">
                @forelse($recentStockIns ?? [] as $recent)
                <div class="rounded-lg border border-gray-200 p-3 text-sm">
                    <p class="font-mono text-xs font-bold text-gray-900">{{ $recent->transaction_number }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ $recent->transaction_date?->format('d M Y') }}</p>
                    <p class="text-xs text-gray-400 mt-1">
                        {{ $recent->stock_in_items_count ?? 0 }} jenis · {{ $recent->total_qty_received }} unit
                    </p>
                    @if($recent->note)
                    <p class="text-xs text-gray-400 mt-1 truncate">{{ \Illuminate\Support\Str::limit($recent->note, 40) }}</p>
                    @endif
                </div>
                @empty
                <p class="text-xs text-gray-400">Belum ada transaksi barang masuk.</p>
                @endforelse
            </div>
        </div>
    </div>
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
                @php
                    $movementType = $movement->movement_type instanceof \BackedEnum
                        ? $movement->movement_type->value
                        : (string) $movement->movement_type;
                    $typeColors = [
                        'stock_in_available' => 'bg-green-100 text-green-700',
                        'stock_in_damaged' => 'bg-orange-100 text-orange-700',
                        'transfer_available' => 'bg-blue-100 text-blue-700',
                        'sale' => 'bg-red-100 text-red-700',
                        'return_to_warehouse' => 'bg-yellow-100 text-yellow-700',
                    ];
                    $typeLabels = [
                        'stock_in_available' => 'MASUK',
                        'stock_in_damaged' => 'MASUK RUSAK',
                        'transfer_available' => 'DISTRIBUSI',
                        'sale' => 'KELUAR',
                        'return_to_warehouse' => 'RETUR',
                    ];
                    $color = $typeColors[$movementType] ?? 'bg-gray-100 text-gray-600';
                    $label = $typeLabels[$movementType] ?? strtoupper($movementType);
                    $isInbound = in_array($movementType, ['stock_in_available', 'stock_in_damaged', 'return_to_warehouse'], true);
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-xs text-gray-500 font-mono">
                        {{ $movement->created_at?->format('d M Y · H:i') }}
                    </td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 text-xs font-bold rounded {{ $color }}">{{ $label }}</span>
                    </td>
                    <td class="px-4 py-3">
                        <p class="font-medium text-xs">{{ $movement->item->item_name ?? '-' }}</p>
                        <p class="text-xs text-gray-400 font-mono">{{ $movement->item->barcode ?? '' }}</p>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-500">{{ $movement->fromLocation->location_name ?? 'Supplier' }}</td>
                    <td class="px-4 py-3 text-xs text-gray-500">{{ $movement->toLocation->location_name ?? 'Penjualan' }}</td>
                    <td class="px-4 py-3 text-right font-mono font-bold {{ $isInbound ? 'text-green-600' : 'text-gray-700' }}">
                        {{ $isInbound ? '+' : '-' }}{{ $movement->qty }}
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
                {{ $showCost ? '🔒 Sembunyikan Modal' : '👁 Lihat Harga Modal' }}
            </button>
            @endif
        </div>

        {{-- Password modal for cost view --}}
        @if($showPasswordModal)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 w-80 shadow-xl">
                <h3 class="font-bold text-sm mb-1">Verifikasi Identitas</h3>
                <p class="text-xs text-gray-400 mb-4">Masukkan password untuk melihat harga modal</p>
                <input
                    type="password"
                    wire:model="costPassword"
                    placeholder="Password"
                    class="w-full border border-gray-300 rounded px-3 py-2 text-sm mb-3 focus:outline-none focus:border-gray-900">
                @if($passwordError)
                <p class="text-xs text-red-500 mb-3">{{ $passwordError }}</p>
                @endif
                <div class="flex gap-2">
                    <button wire:click="verifyCostPassword"
                        class="flex-1 bg-gray-900 text-white text-xs font-bold py-2 rounded uppercase tracking-wide">
                        Verifikasi
                    </button>
                    <button wire:click="cancelCostView"
                        class="flex-1 border border-gray-300 text-xs font-bold py-2 rounded uppercase tracking-wide">
                        Batal
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
                    <th class="px-4 py-2 text-right text-xs font-bold uppercase tracking-wide text-gray-400">MARGIN</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($priceItems ?? [] as $item)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-xs">{{ $item->item_name }}</td>
                    <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $item->barcode }}</td>
                    @if($showCost)
                    <td class="px-4 py-3 text-right font-mono text-xs text-gray-500">
                        Rp {{ number_format($item->latest_supplier_cost) }}
                    </td>
                    @endif
                    <td class="px-4 py-3 text-right font-mono text-xs font-bold">
                        Rp {{ number_format($item->latest_base_selling_price) }}
                    </td>
                    <td class="px-4 py-3 text-right text-xs font-bold text-green-600">
                        @php
                        $margin = $item->latest_supplier_cost > 0
                            ? round((($item->latest_base_selling_price - $item->latest_supplier_cost) / $item->latest_supplier_cost) * 100)
                            : 0;
                        @endphp
                        ↗ {{ $margin }}%
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400 text-sm">Belum ada data harga.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @endif

</div>
</div>
</x-filament-panels::page>
