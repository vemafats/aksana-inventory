<x-filament-panels::page>
<div>

@if($hasActiveSession)
<div style="background:#FEF3C7; border:1px solid #F59100;
    border-radius:10px; padding:12px 16px; margin-bottom:16px;
    display:flex; align-items:center; gap:10px;">
    <span style="font-size:18px;">⚠</span>
    <div>
        <p style="font-size:13px; font-weight:700; color:#D97706;
            margin:0 0 2px;">Sesi Opname Aktif</p>
        <p style="font-size:12px; color:#92400E; margin:0;">
            Semua transaksi dinonaktifkan selama sesi opname berlangsung.
            Validasi atau tolak sesi sebelum melanjutkan operasional.
        </p>
    </div>
</div>
@endif

{{-- Tabs --}}
<div style="display:flex; gap:2px; border-bottom:1px solid #D1DAE5;
    margin-bottom:16px;">
    <button wire:click="$set('activeTab','aktif')" type="button"
        style="padding:8px 16px; font-size:11px; font-weight:700;
            text-transform:uppercase; letter-spacing:0.08em;
            border:none; cursor:pointer; border-radius:6px 6px 0 0;
            {{ $activeTab === 'aktif'
                ? 'background:#070D1E; color:white;'
                : 'background:transparent; color:#49586B;' }}">
        AKTIF & PENDING
        @if($activeSessions->count() > 0)
        <span style="background:#F04040; color:white;
            border-radius:10px; padding:1px 6px; font-size:10px;
            margin-left:4px;">
            {{ $activeSessions->count() }}
        </span>
        @endif
    </button>
    <button wire:click="$set('activeTab','riwayat')" type="button"
        style="padding:8px 16px; font-size:11px; font-weight:700;
            text-transform:uppercase; letter-spacing:0.08em;
            border:none; cursor:pointer; border-radius:6px 6px 0 0;
            {{ $activeTab === 'riwayat'
                ? 'background:#070D1E; color:white;'
                : 'background:transparent; color:#49586B;' }}">
        RIWAYAT
    </button>
</div>

@if($activeTab === 'aktif')
@forelse($activeSessions as $session)
<div style="background:white; border:1px solid #D1DAE5;
    border-radius:12px; overflow:hidden; margin-bottom:12px;">

    {{-- Session header --}}
    <div style="padding:14px 16px; border-bottom:1px solid #F3F4F6;
        display:flex; justify-content:space-between; align-items:center;
        background:#F8F9FB;">
        <div>
            <div style="display:flex; align-items:center; gap:8px;">
                <span style="font-size:13px; font-weight:700;
                    color:#070D1E; font-family:monospace;">
                    {{ $session->opname_number ?? 'AUDIT #' . str_pad($loop->iteration, 5, '0', STR_PAD_LEFT) }}
                </span>
                @if($session->validation_status === 'pending_validation')
                <span style="background:#FEF3C7; color:#D97706;
                    font-size:10px; font-weight:700; padding:2px 8px;
                    border-radius:20px;">
                    MENUNGGU VALIDASI
                </span>
                @else
                <span style="background:#DBEAFE; color:#1D4ED8;
                    font-size:10px; font-weight:700; padding:2px 8px;
                    border-radius:20px;">
                    DRAFT
                </span>
                @endif
            </div>
            <p style="font-size:12px; color:#49586B; margin:4px 0 0;">
                {{ $session->location->location_name ?? '-' }} ·
                Oleh: {{ $session->createdBy->name ?? '-' }} ·
                {{ \Carbon\Carbon::parse($session->created_at)
                    ->timezone('Asia/Jakarta')->format('d M Y · H:i') }}
            </p>
        </div>
        @if($session->validation_status === 'pending_validation' && $this->canValidate())
        <div style="display:flex; gap:8px;">
            <button
                wire:click="rejectSession('{{ $session->id }}')"
                wire:confirm="Tolak sesi opname ini? Stok tidak akan diubah."
                type="button"
                style="padding:8px 16px; border:1px solid #F04040;
                    color:#F04040; background:white; border-radius:8px;
                    font-size:12px; font-weight:700; cursor:pointer;
                    text-transform:uppercase;">
                TOLAK
            </button>
            <button
                wire:click="validateSession('{{ $session->id }}')"
                wire:confirm="Validasi sesi opname? Stok akan diperbarui sesuai temuan fisik."
                type="button"
                style="padding:8px 16px; background:#29A85A;
                    color:white; border:none; border-radius:8px;
                    font-size:12px; font-weight:700; cursor:pointer;
                    text-transform:uppercase;">
                ✓ VALIDASI
            </button>
        </div>
        @endif
    </div>

    {{-- Session items --}}
    <table style="width:100%; border-collapse:collapse; font-size:12px;">
        <thead>
            <tr style="background:#F8F9FB;">
                <th style="padding:8px 16px; text-align:left;
                    font-size:10px; font-weight:700;
                    text-transform:uppercase; letter-spacing:0.08em;
                    color:#49586B;">ITEM</th>
                <th style="padding:8px 16px; text-align:right;
                    font-size:10px; font-weight:700;
                    text-transform:uppercase; letter-spacing:0.08em;
                    color:#49586B;">SISTEM</th>
                <th style="padding:8px 16px; text-align:right;
                    font-size:10px; font-weight:700;
                    text-transform:uppercase; letter-spacing:0.08em;
                    color:#49586B;">FISIK</th>
                <th style="padding:8px 16px; text-align:right;
                    font-size:10px; font-weight:700;
                    text-transform:uppercase; letter-spacing:0.08em;
                    color:#49586B;">SELISIH</th>
                <th style="padding:8px 16px; text-align:right;
                    font-size:10px; font-weight:700;
                    text-transform:uppercase; letter-spacing:0.08em;
                    color:#49586B;">RUSAK</th>
            </tr>
        </thead>
        <tbody>
            @foreach($session->stockOpnameItems as $opnameItem)
            @php($diff = $opnameItem->available_difference_qty ?? 0)
            <tr style="border-bottom:1px solid #F3F4F6;">
                <td style="padding:10px 16px;">
                    <div style="font-weight:600; color:#070D1E;">
                        {{ $opnameItem->item->item_name ?? '-' }}
                    </div>
                    <div style="font-size:11px; font-family:monospace;
                        color:#49586B;">
                        {{ $opnameItem->item->barcode ?? '-' }}
                    </div>
                </td>
                <td style="padding:10px 16px; text-align:right;
                    font-family:monospace; color:#49586B;">
                    {{ $opnameItem->system_available_qty ?? 0 }}
                </td>
                <td style="padding:10px 16px; text-align:right;
                    font-family:monospace; font-weight:700;
                    color:#070D1E;">
                    {{ $opnameItem->physical_available_qty ?? 0 }}
                </td>
                <td style="padding:10px 16px; text-align:right;
                    font-family:monospace; font-weight:700;
                    color:{{ $diff > 0 ? '#29A85A' : ($diff < 0 ? '#F04040' : '#49586B') }};">
                    {{ $diff > 0 ? '+' : '' }}{{ $diff }}
                </td>
                <td style="padding:10px 16px; text-align:right;
                    font-family:monospace;
                    color:{{ ($opnameItem->damaged_qty ?? 0) > 0 ? '#F59100' : '#49586B' }};">
                    {{ $opnameItem->damaged_qty ?? 0 }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@empty
<div style="background:white; border:1px solid #D1DAE5;
    border-radius:12px; padding:48px; text-align:center;">
    <p style="font-size:14px; font-weight:600; color:#070D1E;
        margin:0 0 4px;">Tidak ada sesi opname aktif</p>
    <p style="font-size:13px; color:#49586B; margin:0;">
        Buat sesi baru dari aplikasi mobile untuk memulai stok opname.
    </p>
</div>
@endforelse
@endif

@if($activeTab === 'riwayat')
<div style="background:white; border:1px solid #D1DAE5;
    border-radius:12px; overflow:hidden;">
    <table style="width:100%; border-collapse:collapse; font-size:13px;">
        <thead>
            <tr style="background:#F8F9FB;">
                <th style="padding:10px 16px; text-align:left;
                    font-size:10px; font-weight:700;
                    text-transform:uppercase; letter-spacing:0.08em;
                    color:#49586B;">TANGGAL</th>
                <th style="padding:10px 16px; text-align:left;
                    font-size:10px; font-weight:700;
                    text-transform:uppercase; letter-spacing:0.08em;
                    color:#49586B;">LOKASI</th>
                <th style="padding:10px 16px; text-align:left;
                    font-size:10px; font-weight:700;
                    text-transform:uppercase; letter-spacing:0.08em;
                    color:#49586B;">OLEH</th>
                <th style="padding:10px 16px; text-align:center;
                    font-size:10px; font-weight:700;
                    text-transform:uppercase; letter-spacing:0.08em;
                    color:#49586B;">STATUS</th>
                <th style="padding:10px 16px; text-align:left;
                    font-size:10px; font-weight:700;
                    text-transform:uppercase; letter-spacing:0.08em;
                    color:#49586B;">VALIDATOR</th>
            </tr>
        </thead>
        <tbody>
            @forelse($completedSessions as $session)
            <tr style="border-bottom:1px solid #F3F4F6;">
                <td style="padding:10px 16px; font-family:monospace;
                    font-size:11px; color:#49586B;">
                    {{ \Carbon\Carbon::parse($session->validated_at ?? $session->created_at)
                        ->timezone('Asia/Jakarta')->format('d M Y') }}
                </td>
                <td style="padding:10px 16px; font-weight:600;
                    color:#070D1E;">
                    {{ $session->location->location_name ?? '-' }}
                </td>
                <td style="padding:10px 16px; color:#49586B;">
                    {{ $session->createdBy->name ?? '-' }}
                </td>
                <td style="padding:10px 16px; text-align:center;">
                    @if($session->validation_status === 'validated')
                    <span style="background:#D1FAE5; color:#059669;
                        font-size:10px; font-weight:700;
                        padding:2px 8px; border-radius:20px;">
                        ✓ TERVALIDASI
                    </span>
                    @else
                    <span style="background:#FEE2E2; color:#DC2626;
                        font-size:10px; font-weight:700;
                        padding:2px 8px; border-radius:20px;">
                        ✗ DITOLAK
                    </span>
                    @endif
                </td>
                <td style="padding:10px 16px; color:#49586B;
                    font-size:12px;">
                    {{ $session->validator->name ?? '-' }}
                    @if($session->validation_status === 'rejected' && filled($session->rejection_note))
                    <div style="font-size:11px; color:#92400E; margin-top:2px;">
                        {{ $session->rejection_note }}
                    </div>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" style="padding:32px; text-align:center;
                    color:#49586B;">
                    Belum ada riwayat stok opname.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endif

</div>
</x-filament-panels::page>
