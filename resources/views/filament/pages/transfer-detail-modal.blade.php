@php
    $distribusi = app(\App\Services\DistribusiService::class);
@endphp

<div class="space-y-4 text-sm">
    <div class="grid grid-cols-2 gap-3">
        <div>
            <p class="text-xs uppercase tracking-wide text-[#49586B]">Asal</p>
            <p class="font-semibold">{{ $transfer->fromLocation?->location_name }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-[#49586B]">Tujuan</p>
            <p class="font-semibold">{{ $transfer->toLocation?->location_name }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-[#49586B]">Tanggal</p>
            <p>{{ $transfer->transfer_date?->format('d M Y') }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-[#49586B]">Status</p>
            @php $status = $distribusi->transferDisplayStatus($transfer); @endphp
            <span @class([
                'rounded-full px-2 py-0.5 text-[9px] font-bold uppercase',
                $status['badge_class'] ?? 'border border-[#D1DAE5] bg-[#DDE4EC]/60 text-[#49586B]',
            ])>{{ $status['label'] }}</span>
        </div>
    </div>

    <table class="w-full text-sm">
        <thead>
            <tr class="border-b text-left text-xs uppercase text-[#49586B]">
                <th class="pb-2">Item</th>
                <th class="pb-2">SKU</th>
                <th class="pb-2">Qty</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($transfer->transferItems as $line)
                <tr class="border-b border-[#EDF1F3]">
                    <td class="py-2">{{ $line->item?->item_name }}</td>
                    <td class="py-2 aksana-mono text-xs">{{ $line->item?->sku }}</td>
                    <td class="py-2 aksana-mono font-bold">{{ $line->qty }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
