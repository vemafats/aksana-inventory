<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Services\QrCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class CatalogQrCodeController extends Controller
{
    public function __construct(
        private readonly QrCodeService $qrCodeService,
    ) {}

    public function show(Item $item): Response
    {
        $path = $this->qrCodeService->ensureQrCodeFile($item);

        return response(
            Storage::disk('public')->get($path),
            200,
            ['Content-Type' => 'image/png'],
        );
    }

    public function printLabel(Request $request): View
    {
        $validated = $request->validate([
            'item_ids' => ['required', 'array', 'max:100'],
            'item_ids.*' => ['required', 'uuid', 'exists:items,id'],
        ]);

        $items = Item::query()
            ->whereIn('id', $validated['item_ids'])
            ->orderBy('item_name')
            ->get()
            ->map(function (Item $item) {
                $item->qr_base64 = $this->qrCodeService->generateQrCode($item);

                return $item;
            });

        return view('catalog.print-labels', [
            'items' => $items,
        ]);
    }
}
