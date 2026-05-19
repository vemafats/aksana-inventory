<x-filament-panels::page>
    <p class="mb-6 text-sm text-[var(--aksana-muted)]">
        Pilih jenis laporan untuk melihat detail dan mengekspor data.
    </p>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        @foreach ($this->getReportCards() as $card)
            <a
                href="{{ $card['url'] }}"
                wire:navigate
                class="group flex flex-col rounded-lg border border-[var(--aksana-border)] bg-white p-5 transition hover:border-[var(--aksana-void)] hover:shadow-sm"
            >
                <div class="mb-4 flex items-start justify-between">
                    <div class="flex h-10 w-10 items-center justify-center rounded-md bg-gray-100">
                        <x-dynamic-component :component="$card['icon']" class="h-5 w-5 text-[var(--aksana-void)]" />
                    </div>
                    <x-heroicon-o-chevron-right class="h-5 w-5 text-[var(--aksana-muted)] transition group-hover:translate-x-0.5 group-hover:text-[var(--aksana-void)]" />
                </div>
                <h3 class="text-sm font-semibold text-[var(--aksana-void)]">{{ $card['title'] }}</h3>
                <p class="mt-1 text-xs text-[var(--aksana-muted)]">{{ $card['description'] }}</p>
            </a>
        @endforeach
    </div>
</x-filament-panels::page>
