<?php

namespace Tests\Concerns;

use App\Models\Event;
use App\Models\Location;
use App\Models\User;
use App\Services\EventService;
use Illuminate\Support\Carbon;

trait CreatesActiveEvents
{
    protected function activeEventForLocation(Location $location, ?User $creator = null): Event
    {
        $existing = Event::query()
            ->where('location_id', $location->id)
            ->where('status', 'active')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $creator ??= User::query()->where('email', 'owner@aksana.id')->firstOrFail();

        $today = Carbon::today(config('app.timezone', 'Asia/Jakarta'));

        return app(EventService::class)->createEvent([
            'location_id' => $location->id,
            'name' => 'Test Event '.$location->location_code,
            'start_date' => $today->toDateString(),
            'end_date' => $today->copy()->addDays(7)->toDateString(),
            'status' => 'active',
            'assignments' => [
                [
                    'user_id' => User::query()->where('email', 'sales@aksana.id')->firstOrFail()->id,
                    'role_in_event' => 'sales',
                ],
            ],
        ], $creator);
    }
}
