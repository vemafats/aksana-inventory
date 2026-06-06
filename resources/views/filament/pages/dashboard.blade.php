<x-filament-panels::page>
    <x-filament-widgets::widgets
        :columns="$this->getColumns()"
        :data="
            [
                ...(property_exists($this, 'filters') ? ['filters' => $this->filters] : []),
            ]
        "
        :widgets="$this->getVisibleWidgets()"
    />

    <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:20px; margin-top:24px;">
        <div style="background:white; border-radius:12px; border:1px solid #e5e7eb; padding:20px;">
            @include('filament.components.aksana-donut-chart', ['segments' => $stockByStatus, 'title' => 'Stok per Status'])
        </div>
        <div style="background:white; border-radius:12px; border:1px solid #e5e7eb; padding:20px;">
            @include('filament.components.aksana-donut-chart', ['segments' => $salesByPayment, 'title' => 'Penjualan Hari Ini'])
        </div>
        <div style="background:white; border-radius:12px; border:1px solid #e5e7eb; padding:20px;">
            @include('filament.components.aksana-donut-chart', ['segments' => $stockByLocation, 'title' => 'Stok per Lokasi'])
        </div>
    </div>
</x-filament-panels::page>
