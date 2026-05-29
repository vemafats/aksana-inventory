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
        'harga-jual' => ['label' => 'HARGA JUAL', 'icon' => 'heroicon-o-tag'],
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
                <span class="text-xs text-gray-400">{{ $totalSku ?? 0 }} item</span>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-bold uppercase tracking-wide text-gray-400">ITEM</th>
                        <th class="px-4 py-2 text-left text-xs font-bold uppercase tracking-wide text-gray-400">BARCODE</th>
                        <th class="px-4 py-2 text-right text-xs font-bold uppercase tracking-wide text-gray-400">AVAILABLE</th>
                        <th class="px-4 py-2 text-right text-xs font-bold uppercase tracking-wide text-gray-400">DAMAGED</th>
                        <th class="px-4 py-2 text-right text-xs font-bold uppercase tracking-wide text-gray-400">TOTAL</th>
                        <th class="px-4 py-2 text-center text-xs font-bold uppercase tracking-wide text-gray-400">STATUS</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($stockItems ?? [] as $item)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium">{{ $item->item_name }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $item->barcode }}</td>
                        <td class="px-4 py-3 text-right font-mono font-bold">{{ $item->total_available ?? 0 }}</td>
                        <td class="px-4 py-3 text-right font-mono text-orange-500">{{ $item->total_damaged ?? 0 }}</td>
                        <td class="px-4 py-3 text-right font-mono font-bold">{{ $item->total_stock ?? 0 }}</td>
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
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400 text-sm">Belum ada stok. Lakukan barang masuk terlebih dahulu.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- TAMBAH STOK --}}
    @if($activeTab === 'tambah-stok')
    <div class="text-center py-12">
        <x-heroicon-o-plus-circle class="w-12 h-12 text-gray-300 mx-auto mb-3"/>
        <p class="text-sm font-semibold text-gray-600">Tambah Stok (Barang Masuk)</p>
        <p class="text-xs text-gray-400 mt-1">Gunakan aplikasi mobile untuk input barang masuk dari supplier.</p>
        <p class="text-xs text-gray-400">Atau gunakan API: POST /api/stock-in</p>
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

    {{-- HARGA JUAL --}}
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
                    <th class="px-4 py-2 text-right text-xs font-bold uppercase tracking-wide text-gray-400">HARGA JUAL</th>
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
