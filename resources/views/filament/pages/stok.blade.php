<x-filament-panels::page>
    <div class="aksana-tabs mb-6 flex flex-wrap gap-2">
        @foreach ([
            'ringkasan' => ['label' => 'Ringkasan', 'icon' => 'heroicon-m-squares-2x2'],
            'tambah-stok' => ['label' => 'Tambah Stok', 'icon' => 'heroicon-m-plus'],
            'riwayat-pergerakan' => ['label' => 'Riwayat Pergerakan', 'icon' => 'heroicon-m-arrow-path'],
            'harga-jual' => ['label' => 'Harga Jual', 'icon' => 'heroicon-m-currency-dollar'],
        ] as $key => $tab)
            <button
                type="button"
                wire:click="selectTab('{{ $key }}')"
                @class([
                    'aksana-tab',
                    'aksana-tab-active' => $activeTab === $key,
                ])
            >
                <x-dynamic-component :component="$tab['icon']" class="h-4 w-4" />
                {{ $tab['label'] }}
            </button>
        @endforeach
    </div>

    <div class="rounded-lg border border-[var(--aksana-border)] bg-white p-8 text-center">
        <h2 class="text-lg font-semibold text-[var(--aksana-void)]">{{ $this->getActiveTabLabel() }}</h2>
        <p class="mt-2 text-sm text-[var(--aksana-muted)]">
            Konten tab <span class="font-mono font-semibold">{{ $activeTab }}</span> akan dibangun secara bertahap.
        </p>
    </div>
</x-filament-panels::page>
