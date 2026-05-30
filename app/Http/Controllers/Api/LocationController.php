<?php

namespace App\Http\Controllers\Api;

use App\Enums\LocationStatus;
use App\Enums\LocationType;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Services\LocationCloseService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogicException;

class LocationController extends Controller
{
    public function __construct(
        private readonly LocationCloseService $locationCloseService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $locations = Location::query()
            ->where('status', LocationStatus::ACTIVE->value)
            ->where('location_type', '!=', LocationType::CENTRAL_WAREHOUSE->value)
            ->orderBy('location_name')
            ->get(['id', 'location_name', 'location_type', 'status']);

        return response()->json([
            'success' => true,
            'data' => $locations->map(fn (Location $location) => [
                'id' => $location->id,
                'location_name' => $location->location_name,
                'location_type' => $location->location_type->value,
                'status' => $location->status->value,
            ])->values(),
        ]);
    }

    public function centralWarehouse(): JsonResponse
    {
        $warehouse = Location::query()
            ->where('location_type', LocationType::CENTRAL_WAREHOUSE->value)
            ->where('status', 'active')
            ->orderBy('created_at')
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $warehouse->id,
                'location_name' => $warehouse->location_name,
            ],
        ]);
    }

    public function salesLocations(): JsonResponse
    {
        $locations = Location::query()
            ->where('status', LocationStatus::ACTIVE->value)
            ->where('location_type', '!=', LocationType::CENTRAL_WAREHOUSE->value)
            ->orderBy('location_name')
            ->get(['id', 'location_name', 'location_type']);

        return response()->json([
            'success' => true,
            'data' => $locations->map(fn (Location $location) => [
                'id' => $location->id,
                'name' => $location->location_name,
                'location_name' => $location->location_name,
                'location_type' => $location->location_type->value,
            ])->values(),
        ]);
    }

    public function close(Location $location): JsonResponse
    {
        try {
            $location = $this->locationCloseService->closeLocation(
                $location,
                request()->user(),
            );
        } catch (AuthorizationException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 403);
        } catch (LogicException $exception) {
            $remaining = $this->locationCloseService->getRemainingStock($location);

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'remaining_stock' => $remaining,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Lokasi berhasil ditutup',
            'data' => $location,
        ]);
    }
}
