<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Event\StoreEventRequest;
use App\Http\Requests\Event\UpdateEventRequest;
use App\Models\Event;
use App\Models\User;
use App\Services\EventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use LogicException;

class EventController extends Controller
{
    public function __construct(
        private readonly EventService $eventService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Event::query()
            ->with(['location', 'users'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->string('location_id'));
        }

        $events = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $events->through(fn (Event $event) => $this->formatEvent($event)),
        ]);
    }

    public function store(StoreEventRequest $request): JsonResponse
    {
        try {
            $event = $this->eventService->createEvent(
                $request->validated(),
                $request->user(),
            );
        } catch (InvalidArgumentException|LogicException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Event berhasil dibuat',
            'data' => $this->formatEvent($event),
        ], 201);
    }

    public function show(Event $event): JsonResponse
    {
        $event->load(['location', 'users']);

        return response()->json([
            'success' => true,
            'data' => $this->formatEvent($event),
        ]);
    }

    public function update(UpdateEventRequest $request, Event $event): JsonResponse
    {
        try {
            $event = $this->eventService->updateEvent($event, $request->validated());
        } catch (InvalidArgumentException|LogicException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Event berhasil diperbarui',
            'data' => $this->formatEvent($event),
        ]);
    }

    public function end(Event $event): JsonResponse
    {
        $user = request()->user();

        if ($user === null || ! $user->role->canManageEvents()) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak',
            ], 403);
        }

        try {
            $event = $this->eventService->endEvent($event);
        } catch (InvalidArgumentException|LogicException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        $event->load(['location', 'users']);

        return response()->json([
            'success' => true,
            'message' => 'Event diakhiri',
            'data' => $this->formatEvent($event),
        ]);
    }

    public function myActive(Request $request): JsonResponse
    {
        $events = $this->eventService->activeEventsForUser($request->user());

        return response()->json([
            'success' => true,
            'data' => $events
                ->map(fn (Event $event) => $this->formatEvent($event, $request->user()))
                ->values(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatEvent(Event $event, ?User $viewer = null): array
    {
        $data = $event->toArray();

        if ($event->relationLoaded('location') && $event->location) {
            $data['location'] = [
                'id' => $event->location->id,
                'location_name' => $event->location->location_name,
                'location_type' => $event->location->location_type->value,
            ];
            $data['location_id'] = $event->location->id;
            $data['location_name'] = $event->location->location_name;
        }

        if ($event->relationLoaded('users')) {
            $data['assigned_users'] = $event->users->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_in_event' => $user->pivot->role_in_event,
            ])->values()->all();
        }

        if ($viewer !== null && $event->relationLoaded('users')) {
            $assignment = $event->users->firstWhere('id', $viewer->id);
            $data['role_in_event'] = $assignment?->pivot->role_in_event;
        }

        $data['total_expenses'] = $event->totalExpenses();
        $data['expenses_count'] = $event->expenses()->count();

        return $data;
    }
}
