<div class="space-y-4 text-sm">
    <div class="grid grid-cols-2 gap-3">
        <div>
            <p class="text-xs uppercase tracking-wide text-gray-500">Kode</p>
            <p class="aksana-mono font-semibold">{{ $transaction->sales_number }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-gray-500">Waktu</p>
            <p>{{ $transaction->transaction_date->format('d M Y H:i') }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-gray-500">Lokasi</p>
            <p>{{ $transaction->location?->location_name }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-gray-500">Kasir</p>
            <p>{{ $transaction->employee?->name }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-gray-500">Total</p>
            <p class="aksana-mono font-bold">{{ \App\Filament\Pages\PenjualanPage::formatRupiah((float) $transaction->grand_total) }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-gray-500">Pembayaran</p>
            <p>{{ $transaction->payment_method->label() }}</p>
        </div>
    </div>

    <div>
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Item</p>
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b text-left text-xs uppercase text-gray-500">
                    <th class="pb-2">Nama</th>
                    <th class="pb-2">Qty</th>
                    <th class="pb-2">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($transaction->salesItems as $line)
                    <tr class="border-b border-gray-100">
                        <td class="py-2">{{ $line->item?->item_name }}</td>
                        <td class="py-2 aksana-mono">{{ $line->qty }}</td>
                        <td class="py-2 aksana-mono">{{ \App\Filament\Pages\PenjualanPage::formatRupiah((float) $line->total_after_discount) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
