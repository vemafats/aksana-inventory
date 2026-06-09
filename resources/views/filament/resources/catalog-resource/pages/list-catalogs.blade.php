<x-filament-panels::page>
    <div>
        {{ $this->table }}
    </div>

    <script src="{{ asset('js/zebra-print-label.js') }}?v=6"></script>

    @if ($showPrintModal)
        <div
            class="fixed inset-0 z-[100] flex items-center justify-center p-4"
            wire:keydown.escape.window="closePrintModal"
        >
            <div class="absolute inset-0 bg-black/50" wire:click="closePrintModal"></div>

            <div
                class="relative z-10 w-full rounded-xl border border-[var(--aksana-border)] bg-white shadow-xl"
                style="min-width:420px; max-width:480px;"
                wire:ignore
                x-data="printLabelModal()"
                @click.stop
            >
                <div style="padding:24px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                        <div>
                            <h3 style="margin:0; font-size:18px; font-weight:700;">Cetak Label QR Code</h3>
                            <p style="margin:4px 0 0; font-size:13px; color:#6b7280;">Zebra GC420 · Browser Print (localhost:9100)</p>
                        </div>
                        <button @click="open = false" type="button" style="background:none; border:none; cursor:pointer; padding:4px;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                        </button>
                    </div>

                    <label style="display:block; font-weight:600; font-size:13px; margin-bottom:6px;">Item</label>
                    <select x-model="selectedBarcode" style="width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:14px; margin-bottom:16px; background:white; box-sizing:border-box;">
                        <option value="">-- Pilih Item --</option>
                        @foreach ($printableItems ?? [] as $item)
                            <option value="{{ $item->barcode }}">{{ $item->item_name }} ({{ $item->barcode }})</option>
                        @endforeach
                    </select>

                    <label style="display:block; font-weight:600; font-size:13px; margin-bottom:6px;">Ukuran Label</label>
                    <div style="display:flex; gap:16px; margin-bottom:16px; flex-wrap:wrap;">
                        <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:14px;">
                            <input type="radio" x-model="labelSize" value="40x30"> 40 × 30 mm
                        </label>
                        <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:14px;">
                            <input type="radio" x-model="labelSize" value="50x25"> 50 × 25 mm
                        </label>
                        <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:14px;">
                            <input type="radio" x-model="labelSize" value="30x20"> 30 × 20 mm
                        </label>
                    </div>

                    <label style="display:block; font-weight:600; font-size:13px; margin-bottom:6px;">Jumlah Cetak</label>
                    <input type="number" x-model.number="qty" min="1" max="100" style="width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:14px; margin-bottom:16px; box-sizing:border-box;">

                    <div x-show="selectedBarcode" style="background:#f3f4f6; padding:12px; border-radius:8px; margin-bottom:16px; font-size:13px;">
                        <strong>Preview</strong><br>
                        Kode: <span x-text="selectedBarcode" style="font-family:monospace;"></span><br>
                        Ukuran: <span x-text="labelSize === '40x30' ? '40 × 30 mm' : (labelSize === '30x20' ? '30 × 20 mm' : '50 × 25 mm')"></span><br>
                        Qty: <span x-text="qty"></span> lembar
                    </div>

                    <div style="margin-bottom:16px;">
                        <button @click="showZplEditor = !showZplEditor" type="button"
                            style="background:none; border:none; cursor:pointer; font-size:13px; font-weight:600; color:#6b7280; display:flex; align-items:center; gap:4px; padding:0;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                x-bind:style="showZplEditor ? 'transform:rotate(90deg)' : ''">
                                <polyline points="9 18 15 12 9 6"></polyline>
                            </svg>
                            Edit Script ZPL
                        </button>

                        <div x-show="showZplEditor" x-transition style="margin-top:8px;">
                            <p style="font-size:11px; color:#9ca3af; margin:0 0 6px;">
                                Edit ZPL di bawah untuk mengatur posisi dan ukuran. Variabel: {CODE} = kode item, {QTY} = jumlah cetak.
                            </p>
                            <textarea x-model="customZpl"
                                style="width:100%; height:200px; font-family:'IBM Plex Mono',monospace; font-size:12px; padding:10px; border:1px solid #d1d5db; border-radius:8px; resize:vertical; line-height:1.5; background:#f9fafb;"
                                spellcheck="false"></textarea>
                            <div style="display:flex; gap:8px; margin-top:6px;">
                                <button @click="saveZpl()" type="button"
                                    style="padding:6px 12px; font-size:11px; border:1px solid #d1d5db; border-radius:6px; background:#1a1a2e; color:white; cursor:pointer; font-weight:600;">
                                    Simpan Template
                                </button>
                                <button @click="resetZpl()" type="button"
                                    style="padding:6px 12px; font-size:11px; border:1px solid #d1d5db; border-radius:6px; background:white; cursor:pointer; color:#6b7280;">
                                    Reset Default
                                </button>
                                <span x-show="saveConfirm" x-transition style="font-size:11px; color:#16a34a; line-height:28px;">
                                    ✓ Tersimpan
                                </span>
                            </div>
                        </div>
                    </div>

                    <div style="display:flex; gap:12px; margin-top:20px;">
                        <button @click="open = false" type="button"
                            style="flex:1; padding:14px; border:1px solid #d1d5db; border-radius:10px; background:#ffffff; color:#374151; cursor:pointer; font-weight:600; font-size:14px; text-align:center;">
                            Batal
                        </button>
                        <button @click="printLabel()" type="button" x-bind:disabled="!selectedBarcode || printing"
                            x-bind:style="!selectedBarcode || printing
                                ? 'flex:1; padding:14px; border:1px solid #e5e7eb; border-radius:10px; background:#e5e7eb; color:#9ca3af; cursor:not-allowed; font-weight:600; font-size:14px; text-align:center;'
                                : 'flex:1; padding:14px; border:1px solid #d1d5db; border-radius:10px; background:#ffffff; color:#374151; cursor:pointer; font-weight:600; font-size:14px; text-align:center;'">
                            <span x-show="!printing">Cetak</span>
                            <span x-show="printing">Mencetak...</span>
                        </button>
                    </div>

                    <div x-show="statusMsg" x-text="statusMsg"
                        x-bind:style="statusSuccess ? 'color:#16a34a; font-weight:600;' : 'color:#dc2626;'"
                        style="margin-top:12px; font-size:13px; text-align:center;">
                    </div>

                    <div x-show="statusMsg && !statusSuccess" style="margin-top:6px; font-size:11px; color:#6b7280; text-align:center;">
                        Buka Developer Tools (F12 → Console) untuk detail debug.
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
