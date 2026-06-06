{{-- Usage: @include('filament.components.aksana-donut-chart', ['segments' => [...], 'title' => '...', 'size' => 140]) --}}
@php
    $size = $size ?? 140;
    $strokeWidth = $strokeWidth ?? 22;
    $segments = $segments ?? [];
    $title = $title ?? '';
    $total = collect($segments)->sum('value');
    $radius = ($size - $strokeWidth) / 2;
    $circumference = 2 * M_PI * $radius;
    $currentOffset = 0;
@endphp
<div style="text-align:center;">
    @if ($title)
        <p style="font-size:11px; font-weight:700; letter-spacing:1px; color:#6b7280; margin:0 0 12px; text-transform:uppercase;">{{ $title }}</p>
    @endif
    <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 {{ $size }} {{ $size }}" style="margin:0 auto; display:block;">
        @if ($total > 0)
            @foreach ($segments as $seg)
                @php
                    $pct = $seg['value'] / $total;
                    $dash = $pct * $circumference;
                    $gap = $circumference - $dash;
                    $rotation = ($currentOffset / $total) * 360 - 90;
                @endphp
                <circle cx="{{ $size / 2 }}" cy="{{ $size / 2 }}" r="{{ $radius }}"
                    fill="none" stroke="{{ $seg['color'] }}" stroke-width="{{ $strokeWidth }}"
                    stroke-dasharray="{{ $dash }} {{ $gap }}"
                    transform="rotate({{ $rotation }} {{ $size / 2 }} {{ $size / 2 }})" />
                @php $currentOffset += $seg['value']; @endphp
            @endforeach
        @else
            <circle cx="{{ $size / 2 }}" cy="{{ $size / 2 }}" r="{{ $radius }}"
                fill="none" stroke="#e5e7eb" stroke-width="{{ $strokeWidth }}" />
        @endif
        <text x="{{ $size / 2 }}" y="{{ $size / 2 }}" text-anchor="middle" dominant-baseline="central"
            style="font-size:{{ $size * 0.16 }}px; font-weight:700; fill:#1a1a2e; font-family:Inter,sans-serif;">
            {{ number_format($total) }}
        </text>
    </svg>
    <div style="display:flex; flex-wrap:wrap; justify-content:center; gap:6px 12px; margin-top:10px;">
        @foreach ($segments as $seg)
            <div style="display:flex; align-items:center; gap:4px;">
                <span style="width:8px; height:8px; border-radius:50%; background:{{ $seg['color'] }}; flex-shrink:0;"></span>
                <span style="font-size:11px; color:#6b7280;">{{ $seg['label'] }} {{ $total > 0 ? '('.round($seg['value'] / $total * 100).'%)' : '' }}</span>
            </div>
        @endforeach
    </div>
</div>
