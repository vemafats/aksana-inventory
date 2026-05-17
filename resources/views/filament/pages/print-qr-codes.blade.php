<div>
    <style>
        @page {
            size: A4;
            margin: 0;
        }

        .print-qr-page {
            font-family: Inter, Arial, sans-serif;
            color: #070d1e;
            background: #edf1f3;
        }

        .print-qr-page .sheet {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            padding: 8px;
            page-break-after: always;
        }

        .print-qr-page .sheet:last-child {
            page-break-after: auto;
        }

        .print-qr-page .label {
            border: 1px solid #ddd;
            background: #fff;
            padding: 8px;
            text-align: center;
            page-break-inside: avoid;
        }

        .print-qr-page .label img {
            width: 150px;
            height: 150px;
            object-fit: contain;
        }

        .print-qr-page .item-name {
            font-size: 8pt;
            margin-top: 4px;
            font-family: Inter, sans-serif;
            font-weight: 600;
        }

        .print-qr-page .barcode {
            font-size: 7pt;
            font-family: 'IBM Plex Mono', monospace;
            color: #666;
            word-break: break-all;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            .print-qr-page {
                background: #fff;
            }

            @page {
                margin: 0;
            }
        }
    </style>

    <div class="print-qr-page">
        <div class="no-print" style="padding: 16px; display: flex; gap: 12px; align-items: center;">
            <button
                type="button"
                onclick="window.print()"
                style="background: #070d1e; color: #fff; border: none; border-radius: 6px; padding: 10px 16px; font-weight: 600; cursor: pointer;"
            >
                Cetak QR Code
            </button>
            <a href="{{ \App\Filament\Resources\CatalogResource::getUrl('index') }}" style="color: #49586b; text-decoration: none;">
                ← Kembali
            </a>
        </div>

        @foreach ($this->items->chunk(40) as $chunk)
            <div class="sheet">
                @foreach ($chunk as $item)
                    <div class="label">
                        <img
                            src="data:image/png;base64,{{ $item->qr_base64 }}"
                            width="150"
                            height="150"
                            alt="{{ $item->barcode }}"
                        >
                        <div class="item-name">{{ $item->item_name }}</div>
                        <div class="barcode">{{ $item->barcode }}</div>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>

    <script>
        window.addEventListener('load', () => {
            setTimeout(() => window.print(), 300);
        });
    </script>
</div>
