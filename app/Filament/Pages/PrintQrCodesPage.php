<?php

namespace App\Filament\Pages;

use App\Models\Item;
use App\Services\QrCodeService;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class PrintQrCodesPage extends Page
{
    protected static string $view = 'filament.pages.print-qr-codes';

    protected static ?string $slug = 'catalogs/print-qrcodes';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.layouts.print';

    protected static ?string $title = 'Cetak QR Code';

    /** @var Collection<int, Item> */
    public Collection $items;

    public function mount(): void
    {
        $ids = request()->query('ids', []);

        if (! is_array($ids) || $ids === []) {
            abort(404);
        }

        $qrCodeService = app(QrCodeService::class);

        $this->items = Item::query()
            ->whereIn('id', $ids)
            ->orderBy('item_name')
            ->get()
            ->map(function (Item $item) use ($qrCodeService): Item {
                $item->qr_base64 = $qrCodeService->generateQrCode($item);

                return $item;
            });
    }

    /**
     * @param  list<string>  $ids
     */
    public static function printUrl(array $ids): string
    {
        return static::getUrl().'?'.http_build_query(['ids' => array_values($ids)]);
    }
}
