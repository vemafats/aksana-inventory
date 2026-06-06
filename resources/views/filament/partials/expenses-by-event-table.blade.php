@if(isset($expensesByEvent) && $expensesByEvent->count() > 0)
<div style="background:white; border-radius:12px; border:1px solid #e5e7eb; padding:20px; margin-top:24px;">
    <h3 style="font-size:14px; font-weight:700; margin:0 0 16px; color:#1a1a2e;">Biaya per Event</h3>
    <table style="width:100%; border-collapse:collapse; font-size:13px;">
        <thead>
            <tr style="border-bottom:2px solid #e5e7eb;">
                <th style="text-align:left; padding:10px 12px; font-weight:600; font-size:11px; text-transform:uppercase; color:#6b7280;">Event</th>
                <th style="text-align:left; padding:10px 12px; font-weight:600; font-size:11px; text-transform:uppercase; color:#6b7280;">Lokasi</th>
                <th style="text-align:right; padding:10px 12px; font-weight:600; font-size:11px; text-transform:uppercase; color:#6b7280;">Jumlah Biaya</th>
                <th style="text-align:right; padding:10px 12px; font-weight:600; font-size:11px; text-transform:uppercase; color:#6b7280;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($expensesByEvent as $exp)
            <tr style="border-bottom:1px solid #f3f4f6;">
                <td style="padding:10px 12px; font-weight:500;">{{ $exp->event_name }}</td>
                <td style="padding:10px 12px; color:#6b7280;">{{ $exp->location_name }}</td>
                <td style="padding:10px 12px; text-align:right;">{{ $exp->expense_count }}</td>
                <td style="padding:10px 12px; text-align:right; font-family:'IBM Plex Mono',monospace; font-weight:600; color:#f59e0b;">
                    {{ \App\Helpers\FormatHelper::price($exp->total_amount) }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
