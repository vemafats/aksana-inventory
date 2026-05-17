<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Label QR — Aksana Inventory</title>
    <style>
        @page {
            size: A4;
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 8mm;
            font-family: Inter, Arial, sans-serif;
            background: #edf1f3;
            color: #070d1e;
        }

        .sheet {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 4mm;
            page-break-after: always;
        }

        .sheet:last-child {
            page-break-after: auto;
        }

        .label {
            border: 1px solid #d1dae5;
            border-radius: 4px;
            background: #fff;
            padding: 3mm;
            text-align: center;
            min-height: 48mm;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .label img {
            width: 150px;
            height: 150px;
            object-fit: contain;
        }

        .label .item-name {
            margin-top: 2mm;
            font-size: 8pt;
            line-height: 1.2;
            font-weight: 600;
        }

        .label .sku {
            margin-top: 1mm;
            font-family: 'IBM Plex Mono', 'Courier New', monospace;
            font-size: 7pt;
            color: #49586b;
            word-break: break-all;
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }

            @page {
                margin: 0;
            }
        }
    </style>
</head>
<body>
    @foreach ($items->chunk(40) as $chunk)
        <div class="sheet">
            @foreach ($chunk as $item)
                <div class="label">
                    <img src="data:image/png;base64,{{ $item->qr_base64 }}" alt="QR {{ $item->barcode }}">
                    <div class="item-name">{{ $item->item_name }}</div>
                    <div class="sku">{{ $item->sku }}</div>
                </div>
            @endforeach
        </div>
    @endforeach
</body>
</html>
