@props([
    'label',
    'value',
    'sub',
    'icon' => 'heroicon-o-chart-bar',
    'warn' => false,
    'danger' => false,
])

<div class="aksana-stat-panel">
    <div class="flex items-start gap-4">
        <div class="aksana-stat-icon-wrap">
            <x-dynamic-component :component="$icon" class="aksana-stat-icon" />
        </div>
        <div class="min-w-0 flex-1">
            <p class="aksana-stat-label">{{ $label }}</p>
            <p @class([
                'aksana-stat-value',
                'text-[#F59100]' => $warn && ! $danger,
                'text-[#F04040]' => $danger && $warn,
            ])>{{ $value }}</p>
            <p class="aksana-stat-sub">{{ $sub }}</p>
        </div>
    </div>
</div>
