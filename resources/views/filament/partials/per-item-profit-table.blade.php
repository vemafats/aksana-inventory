@if (isset($perItemProfit) && $perItemProfit->count() > 0)
<div style="background:white; border-radius:12px; border:1px solid #e5e7eb; padding:20px; margin-top:24px;">
    <h3 style="font-size:14px; font-weight:700; margin:0 0 16px; color:#1a1a2e;">Gross Profit per Item</h3>
    <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; font-size:13px;">
            <thead>
                <tr style="border-bottom:2px solid #e5e7eb;">
                    <th style="text-align:left; padding:10px 12px; font-weight:600; font-size:11px; text-transform:uppercase; letter-spacing:0.5px; color:#6b7280;">Item</th>
                    <th style="text-align:left; padding:10px 12px; font-weight:600; font-size:11px; text-transform:uppercase; letter-spacing:0.5px; color:#6b7280;">Barcode</th>
                    <th style="text-align:right; padding:10px 12px; font-weight:600; font-size:11px; text-transform:uppercase; letter-spacing:0.5px; color:#6b7280;">Qty</th>
                    <th style="text-align:right; padding:10px 12px; font-weight:600; font-size:11px; text-transform:uppercase; letter-spacing:0.5px; color:#6b7280;">Penjualan (Kotor)</th>
                    <th style="text-align:right; padding:10px 12px; font-weight:600; font-size:11px; text-transform:uppercase; letter-spacing:0.5px; color:#6b7280;">Diskon</th>
                    <th style="text-align:right; padding:10px 12px; font-weight:600; font-size:11px; text-transform:uppercase; letter-spacing:0.5px; color:#6b7280;">Net Penjualan</th>
                    <th style="text-align:right; padding:10px 12px; font-weight:600; font-size:11px; text-transform:uppercase; letter-spacing:0.5px; color:#6b7280;">HPP</th>
                    <th style="text-align:right; padding:10px 12px; font-weight:600; font-size:11px; text-transform:uppercase; letter-spacing:0.5px; color:#6b7280;">Profit</th>
                    <th style="text-align:right; padding:10px 12px; font-weight:600; font-size:11px; text-transform:uppercase; letter-spacing:0.5px; color:#6b7280;">Margin</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($perItemProfit as $item)
                <tr style="border-bottom:1px solid #f3f4f6;">
                    <td style="padding:10px 12px; font-weight:500;">{{ $item->item_name }}</td>
                    <td style="padding:10px 12px; font-family:'IBM Plex Mono',monospace; font-size:12px; color:#6b7280;">{{ $item->barcode }}</td>
                    <td style="padding:10px 12px; text-align:right; font-family:'IBM Plex Mono',monospace; font-weight:600;">{{ number_format($item->total_qty) }}</td>
                    <td style="padding:10px 12px; text-align:right; font-family:'IBM Plex Mono',monospace; color:#6b7280;">
                        {{ \App\Helpers\FormatHelper::price($item->total_revenue_before_discount) }}
                    </td>
                    <td style="padding:10px 12px; text-align:right; font-family:'IBM Plex Mono',monospace; color:#f59e0b;">
                        {{ $item->total_discount > 0 ? '-'.\App\Helpers\FormatHelper::price($item->total_discount) : '-' }}
                    </td>
                    <td style="padding:10px 12px; text-align:right; font-family:'IBM Plex Mono',monospace; font-weight:500;">
                        {{ \App\Helpers\FormatHelper::price($item->total_revenue) }}
                    </td>
                    <td style="padding:10px 12px; text-align:right; font-family:'IBM Plex Mono',monospace;">{{ \App\Helpers\FormatHelper::price($item->total_cost) }}</td>
                    <td style="padding:10px 12px; text-align:right; font-family:'IBM Plex Mono',monospace; font-weight:600; color:{{ $item->profit >= 0 ? '#16a34a' : '#dc2626' }};">
                        {{ \App\Helpers\FormatHelper::price($item->profit) }}
                    </td>
                    <td style="padding:10px 12px; text-align:right; font-weight:600; color:{{ $item->margin >= 20 ? '#16a34a' : ($item->margin >= 0 ? '#f59e0b' : '#dc2626') }};">
                        {{ $item->margin }}%
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                @php
                    $totalQty = $perItemProfit->sum('total_qty');
                    $totalRevenueBeforeDiscount = $perItemProfit->sum('total_revenue_before_discount');
                    $totalDiscount = $perItemProfit->sum('total_discount');
                    $totalRevenue = $perItemProfit->sum('total_revenue');
                    $totalCost = $perItemProfit->sum('total_cost');
                    $totalProfit = $totalRevenue - $totalCost;
                    $totalMargin = $totalRevenue > 0 ? round(($totalProfit / $totalRevenue) * 100, 1) : 0;
                @endphp
                <tr style="border-top:2px solid #1a1a2e;">
                    <td style="padding:12px; font-weight:700;" colspan="2">TOTAL</td>
                    <td style="padding:12px; text-align:right; font-family:'IBM Plex Mono',monospace; font-weight:700;">{{ number_format($totalQty) }}</td>
                    <td style="padding:12px; text-align:right; font-family:'IBM Plex Mono',monospace; font-weight:700;">{{ \App\Helpers\FormatHelper::price($totalRevenueBeforeDiscount) }}</td>
                    <td style="padding:12px; text-align:right; font-family:'IBM Plex Mono',monospace; font-weight:700; color:#f59e0b;">
                        {{ $totalDiscount > 0 ? '-'.\App\Helpers\FormatHelper::price($totalDiscount) : '-' }}
                    </td>
                    <td style="padding:12px; text-align:right; font-family:'IBM Plex Mono',monospace; font-weight:700;">{{ \App\Helpers\FormatHelper::price($totalRevenue) }}</td>
                    <td style="padding:12px; text-align:right; font-family:'IBM Plex Mono',monospace; font-weight:700;">{{ \App\Helpers\FormatHelper::price($totalCost) }}</td>
                    <td style="padding:12px; text-align:right; font-family:'IBM Plex Mono',monospace; font-weight:700; color:{{ $totalProfit >= 0 ? '#16a34a' : '#dc2626' }};">
                        {{ \App\Helpers\FormatHelper::price($totalProfit) }}
                    </td>
                    <td style="padding:12px; text-align:right; font-weight:700; color:{{ $totalMargin >= 20 ? '#16a34a' : ($totalMargin >= 0 ? '#f59e0b' : '#dc2626') }};">
                        {{ $totalMargin }}%
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endif
