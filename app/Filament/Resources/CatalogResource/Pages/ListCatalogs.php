<?php

namespace App\Filament\Resources\CatalogResource\Pages;

use App\Filament\Resources\CatalogResource;
use App\Models\Item;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCatalogs extends ListRecords
{
    protected static string $resource = CatalogResource::class;

    protected static string $view = 'filament.resources.catalog-resource.pages.list-catalogs';

    public bool $showPrintModal = false;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cetakQrCode')
                ->label('Cetak QR Code')
                ->icon('heroicon-o-printer')
                ->action('openPrintModal'),
            Actions\CreateAction::make(),
        ];
    }

    public function openPrintModal(): void
    {
        $this->showPrintModal = true;
    }

    public function closePrintModal(): void
    {
        $this->showPrintModal = false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return array_merge(parent::getViewData(), [
            'printableItems' => Item::query()
                ->where('is_active', true)
                ->select('barcode', 'item_name')
                ->orderBy('item_name')
                ->get(),
        ]);
    }
}
