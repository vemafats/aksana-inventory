<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\OpnameSessionActiveException;
use App\Http\Controllers\Controller;
use App\Models\StockOpnameTransaction;
use App\Services\StockOpnameService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use LogicException;

class StockOpnameController extends Controller
{
    public function __construct(
        private readonly StockOpnameService $stockOpnameService,
    ) {}

    public function getActive(): JsonResponse
    {
        $session = $this->stockOpnameService->checkActiveSession();

        return response()->json([
            'success' => true,
            'data' => $session,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = StockOpnameTransaction::query()
            ->with(['location', 'createdBy', 'validator'])
            ->latest();

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->string('location_id'));
        }

        if ($request->filled('validation_status')) {
            $query->where('validation_status', $request->string('validation_status'));
        }

        $sessions = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $sessions,
        ]);
    }

    public function show(StockOpnameTransaction $opname): JsonResponse
    {
        $opname->load([
            'stockOpnameItems.item.category',
            'stockOpnameItems.item.brand',
            'stockOpnameItems.item.color',
            'stockOpnameItems.item.size',
            'location',
            'createdBy',
            'validator',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->formatOpname($opname),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'location_id' => ['required', 'uuid', 'exists:locations,id'],
            'opname_date' => ['required', 'date'],
            'note' => ['nullable', 'string'],
        ]);

        try {
            $opname = $this->stockOpnameService->createSession(
                $validated['location_id'],
                $validated['opname_date'],
                $request->user(),
            );

            if (! empty($validated['note'])) {
                $opname->update(['note' => $validated['note']]);
            }
        } catch (OpnameSessionActiveException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'active_session_id' => $exception->activeSessionId,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Sesi opname berhasil dibuat',
            'data' => $this->formatOpname($opname->fresh(['location', 'createdBy'])),
        ], 201);
    }

    public function addItem(Request $request, StockOpnameTransaction $opname): JsonResponse
    {
        $validated = $request->validate([
            'item_id' => ['required', 'uuid', 'exists:items,id'],
            'physical_available_qty' => ['required', 'integer', 'min:0'],
            'damaged_qty' => ['required', 'integer', 'min:0'],
            'photo_id' => ['nullable', 'uuid'],
        ]);

        try {
            $item = $this->stockOpnameService->addItem(
                $opname,
                $validated['item_id'],
                $validated['physical_available_qty'],
                $validated['damaged_qty'],
                $validated['photo_id'] ?? null,
                $request->user(),
            );
        } catch (LogicException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        $item->load('item');

        return response()->json([
            'success' => true,
            'message' => 'Item opname berhasil disimpan',
            'data' => $item,
        ]);
    }

    public function submit(StockOpnameTransaction $opname): JsonResponse
    {
        try {
            $opname = $this->stockOpnameService->submitForValidation($opname, request()->user());
        } catch (LogicException|InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Sesi opname dikirim untuk validasi',
            'data' => $this->formatOpname($opname),
        ]);
    }

    public function validate(StockOpnameTransaction $opname): JsonResponse
    {
        try {
            $opname = $this->stockOpnameService->validateOpname($opname, request()->user());
        } catch (AuthorizationException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 403);
        } catch (LogicException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Opname berhasil divalidasi',
            'data' => $this->formatOpname($opname),
        ]);
    }

    public function reject(Request $request, StockOpnameTransaction $opname): JsonResponse
    {
        $validated = $request->validate([
            'rejection_note' => ['required', 'string'],
        ]);

        try {
            $opname = $this->stockOpnameService->rejectOpname(
                $opname,
                $request->user(),
                $validated['rejection_note'],
            );
        } catch (AuthorizationException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 403);
        } catch (LogicException|InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Opname ditolak',
            'data' => $this->formatOpname($opname),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatOpname(StockOpnameTransaction $opname): array
    {
        $data = $opname->toArray();

        if ($opname->relationLoaded('stockOpnameItems')) {
            $data['items'] = $opname->stockOpnameItems->map(function ($item) {
                $row = $item->toArray();

                if ($item->relationLoaded('item') && $item->item) {
                    $row['item'] = $item->item->toArray();
                }

                return $row;
            })->all();
        }

        return $data;
    }
}
