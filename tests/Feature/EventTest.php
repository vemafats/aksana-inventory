<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\User;
use Database\Seeders\LocationSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            UserSeeder::class,
            LocationSeeder::class,
        ]);
    }

    public function test_owner_can_create_event_with_assignments(): void
    {
        $bazar = $this->bazar();
        $pic = User::query()->where('email', 'picbazar@aksana.id')->firstOrFail();
        $sales = User::query()->where('email', 'sales@aksana.id')->firstOrFail();

        $response = $this->actingAsOwner()->postJson('/api/events', [
            'location_id' => $bazar->id,
            'name' => 'Bazar Ramadhan 2026',
            'start_date' => $this->reportToday()->toDateString(),
            'end_date' => $this->reportToday()->addDays(7)->toDateString(),
            'notes' => 'Test event',
            'assignments' => [
                ['user_id' => $pic->id, 'role_in_event' => 'pic_bazar'],
                ['user_id' => $sales->id, 'role_in_event' => 'sales'],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Event berhasil dibuat')
            ->assertJsonCount(2, 'data.assigned_users');

        $roles = collect($response->json('data.assigned_users'))->pluck('role_in_event')->sort()->values()->all();
        $this->assertSame(['pic_bazar', 'sales'], $roles);
    }

    public function test_my_active_returns_running_event_for_assigned_user(): void
    {
        $bazar = $this->bazar();
        $sales = User::query()->where('email', 'sales@aksana.id')->firstOrFail();

        $create = $this->actingAsOwner()->postJson('/api/events', [
            'location_id' => $bazar->id,
            'name' => 'Event Aktif',
            'start_date' => $this->reportToday()->toDateString(),
            'end_date' => $this->reportToday()->addDays(3)->toDateString(),
            'assignments' => [
                ['user_id' => $sales->id, 'role_in_event' => 'sales'],
            ],
        ]);

        $create->assertCreated();

        Sanctum::actingAs($sales);

        $response = $this->getJson('/api/events/my-active');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Event Aktif')
            ->assertJsonPath('data.0.location_id', $bazar->id)
            ->assertJsonPath('data.0.location_name', $bazar->location_name)
            ->assertJsonPath('data.0.role_in_event', 'sales');
    }

    public function test_central_warehouse_location_is_rejected_with_422(): void
    {
        $warehouse = $this->warehouse();
        $sales = User::query()->where('email', 'sales@aksana.id')->firstOrFail();

        $response = $this->actingAsOwner()->postJson('/api/events', [
            'location_id' => $warehouse->id,
            'name' => 'Invalid Event',
            'start_date' => $this->reportToday()->toDateString(),
            'end_date' => $this->reportToday()->addDays(1)->toDateString(),
            'assignments' => [
                ['user_id' => $sales->id, 'role_in_event' => 'sales'],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['location_id']);

        $errors = $response->json('errors.location_id');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString(
            'gudang pusat',
            strtolower(implode(' ', (array) $errors)),
        );
    }

    public function test_sales_cannot_create_event(): void
    {
        $bazar = $this->bazar();
        $sales = User::query()->where('email', 'sales@aksana.id')->firstOrFail();

        Sanctum::actingAs($sales);

        $response = $this->postJson('/api/events', [
            'location_id' => $bazar->id,
            'name' => 'Forbidden Event',
            'start_date' => $this->reportToday()->toDateString(),
            'end_date' => $this->reportToday()->addDays(1)->toDateString(),
            'assignments' => [
                ['user_id' => $sales->id, 'role_in_event' => 'sales'],
            ],
        ]);

        $response->assertForbidden();
    }

    private function warehouse(): Location
    {
        return Location::query()->where('location_code', 'GUD-001')->firstOrFail();
    }

    private function bazar(): Location
    {
        return Location::query()->where('location_code', 'BAZ-001')->firstOrFail();
    }

    private function actingAsOwner(): static
    {
        Sanctum::actingAs(User::query()->where('email', 'owner@aksana.id')->firstOrFail());

        return $this;
    }

    private function reportToday(): Carbon
    {
        return Carbon::today(config('app.timezone', 'Asia/Jakarta'));
    }
}
