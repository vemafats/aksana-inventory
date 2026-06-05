<?php

namespace App\Services;

use App\Enums\BazarAdjustType;
use App\Enums\StockStatus;
use App\Models\Item;
use App\Models\StockBalance;
use App\Models\TransferItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PricePropagationService
{
    public function __construct(
        private readonly TransferService $transferService,
    ) {}

    /**
     * Recalculate bazar_selling_price on active transfer_items after Item.latest_base_selling_price changes.
     */
    public function propagatePriceToTransfers(Item $item): int
    {
        return DB::transaction(function () use ($item): int {
            $item = $item->fresh();

            $activeDestinationIds = StockBalance::query()
                ->where('item_id', $item->id)
                ->where('stock_status', StockStatus::AVAILABLE)
                ->where('qty', '>', 0)
                ->pluck('location_id');

            if ($activeDestinationIds->isEmpty()) {
                return 0;
            }

            $transferItems = TransferItem::query()
                ->where('item_id', $item->id)
                ->where('bazar_adjust_type', '!=', BazarAdjustType::MANUAL->value)
                ->whereHas('transferTransaction', function ($query) use ($activeDestinationIds): void {
                    $query->where('status', 'completed')
                        ->whereIn('to_location_id', $activeDestinationIds);
                })
                ->get();

            $newBase = (float) $item->latest_base_selling_price;
            $updated = 0;

            foreach ($transferItems as $transferItem) {
                $adjustType = $transferItem->bazar_adjust_type instanceof BazarAdjustType
                    ? $transferItem->bazar_adjust_type
                    : BazarAdjustType::from((string) $transferItem->bazar_adjust_type);

                if ($adjustType === BazarAdjustType::MANUAL) {
                    continue;
                }

                $newBazarPrice = $this->transferService->resolveBazarSellingPrice(
                    $adjustType,
                    $newBase,
                    (float) $transferItem->bazar_adjust_value,
                    (float) $transferItem->bazar_selling_price,
                );

                $transferItem->update([
                    'base_selling_price_snapshot' => $newBase,
                    'bazar_selling_price' => $newBazarPrice,
                ]);

                $updated++;
            }

            if ($updated > 0) {
                Log::info('PricePropagationService: updated transfer_items', [
                    'item_id' => $item->id,
                    'count' => $updated,
                    'new_base_selling_price' => $newBase,
                ]);
            }

            return $updated;
        });
    }
}
