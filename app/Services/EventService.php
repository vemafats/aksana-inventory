<?php

namespace App\Services;

use App\Enums\LocationType;
use App\Enums\StockStatus;
use App\Models\Event;
use App\Models\Location;
use App\Models\StockBalance;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

class EventService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function createEvent(array $data, User $creator): Event
    {
        $this->assertValidLocationForEvent($data['location_id']);

        return DB::transaction(function () use ($data, $creator): Event {
            $event = Event::query()->create([
                'location_id' => $data['location_id'],
                'name' => $data['name'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'status' => $data['status'] ?? 'active',
                'notes' => $data['notes'] ?? null,
                'created_by' => $creator->id,
            ]);

            $this->syncAssignments($event, $data['assignments'] ?? []);

            return $event->fresh(['location', 'users']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateEvent(Event $event, array $data): Event
    {
        if (isset($data['location_id'])) {
            $this->assertValidLocationForEvent($data['location_id']);
        }

        return DB::transaction(function () use ($event, $data): Event {
            $fillable = ['location_id', 'name', 'start_date', 'end_date', 'status', 'notes'];
            $attributes = array_intersect_key($data, array_flip($fillable));

            if ($attributes !== []) {
                $event->update($attributes);
            }

            if (array_key_exists('assignments', $data)) {
                $this->syncAssignments($event, $data['assignments']);
            }

            return $event->fresh(['location', 'users']);
        });
    }

    public function endEvent(Event $event): Event
    {
        $remainingUnits = (int) StockBalance::query()
            ->where('location_id', $event->location_id)
            ->whereIn('stock_status', [
                StockStatus::AVAILABLE->value,
                StockStatus::DAMAGED->value,
            ])
            ->where('qty', '>', 0)
            ->sum('qty');

        if ($remainingUnits > 0) {
            throw new LogicException(
                'Tidak dapat mengakhiri event. Masih ada stok tersisa di lokasi. Lakukan return terlebih dahulu. '.
                "Sisa stok: {$remainingUnits} unit",
            );
        }

        $event->update(['status' => 'ended']);

        return $event->refresh();
    }

    /**
     * @return Collection<int, Event>
     */
    public function activeEventsForUser(User $user): Collection
    {
        return Event::query()
            ->currentlyRunning()
            ->whereHas('users', fn ($query) => $query->where('users.id', $user->id))
            ->with(['location', 'users'])
            ->get();
    }

    private function assertValidLocationForEvent(string $locationId): void
    {
        $location = Location::query()->findOrFail($locationId);

        if ($location->location_type === LocationType::CENTRAL_WAREHOUSE) {
            throw new InvalidArgumentException('Gudang pusat tidak dapat menjadi lokasi event.');
        }
    }

    /**
     * @param  array<int, array{user_id: string, role_in_event: string}>  $assignments
     */
    private function syncAssignments(Event $event, array $assignments): void
    {
        $syncData = [];

        foreach ($assignments as $assignment) {
            $syncData[$assignment['user_id']] = [
                'id' => (string) Str::uuid(),
                'role_in_event' => $assignment['role_in_event'],
            ];
        }

        $event->users()->sync($syncData);
    }
}
