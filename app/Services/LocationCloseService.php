<?php

namespace App\Services;

use App\Enums\LocationStatus;
use App\Models\Location;
use App\Models\StockBalance;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use LogicException;

class LocationCloseService
{
    public function canClose(Location $location): bool
    {
        return ! StockBalance::query()
            ->where('location_id', $location->id)
            ->where('qty', '>', 0)
            ->exists();
    }

    /**
     * @return Collection<int, StockBalance>
     */
    public function getRemainingStock(Location $location): Collection
    {
        return StockBalance::query()
            ->where('location_id', $location->id)
            ->where('qty', '>', 0)
            ->with(['item', 'location'])
            ->get();
    }

    public function closeLocation(Location $location, User $closedBy): Location
    {
        if (! $this->canClose($location)) {
            throw new LogicException('Lokasi tidak bisa ditutup. Masih ada stok tersisa.');
        }

        if (! $closedBy->role->canCloseBazar()) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk menutup lokasi.');
        }

        $location->update([
            'status' => LocationStatus::CLOSED,
        ]);

        return $location->refresh();
    }
}
