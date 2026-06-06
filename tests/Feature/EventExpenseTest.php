<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventExpense;
use App\Models\Location;
use App\Models\User;
use Database\Seeders\LocationSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventExpenseTest extends TestCase
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

    public function test_owner_can_crud_event_expenses(): void
    {
        $event = $this->createEvent();
        $owner = User::query()->where('email', 'owner@aksana.id')->firstOrFail();
        Sanctum::actingAs($owner);

        $create = $this->postJson("/api/events/{$event->id}/expenses", [
            'description' => 'Sewa tenda',
            'amount' => 1500000,
            'expense_date' => $this->reportToday()->toDateString(),
        ]);

        $create->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Biaya berhasil ditambahkan')
            ->assertJsonPath('data.description', 'Sewa tenda');

        $expenseId = $create->json('data.id');

        $this->getJson("/api/events/{$event->id}/expenses")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');

        $this->putJson("/api/events/{$event->id}/expenses/{$expenseId}", [
            'description' => 'Sewa tenda + transport',
            'amount' => 1750000,
            'expense_date' => $this->reportToday()->toDateString(),
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Biaya berhasil diperbarui')
            ->assertJsonPath('data.amount', 1750000);

        $this->deleteJson("/api/events/{$event->id}/expenses/{$expenseId}")
            ->assertOk()
            ->assertJsonPath('message', 'Biaya berhasil dihapus');

        $this->assertDatabaseMissing('event_expenses', ['id' => $expenseId]);
    }

    public function test_event_show_includes_expense_totals(): void
    {
        $event = $this->createEvent();
        $owner = User::query()->where('email', 'owner@aksana.id')->firstOrFail();

        EventExpense::query()->create([
            'event_id' => $event->id,
            'description' => 'Makan petugas',
            'amount' => 500000,
            'expense_date' => $this->reportToday()->toDateString(),
            'created_by' => $owner->id,
        ]);

        Sanctum::actingAs($owner);

        $this->getJson("/api/events/{$event->id}")
            ->assertOk()
            ->assertJsonPath('data.total_expenses', 500000)
            ->assertJsonPath('data.expenses_count', 1);
    }

    public function test_sales_cannot_create_event_expense(): void
    {
        $event = $this->createEvent();
        $sales = User::query()->where('email', 'sales@aksana.id')->firstOrFail();
        Sanctum::actingAs($sales);

        $this->postJson("/api/events/{$event->id}/expenses", [
            'description' => 'Forbidden',
            'amount' => 100000,
            'expense_date' => $this->reportToday()->toDateString(),
        ])->assertForbidden();
    }

    public function test_expense_must_belong_to_event(): void
    {
        $eventA = $this->createEvent('Event A');
        $eventB = $this->createEvent('Event B');
        $owner = User::query()->where('email', 'owner@aksana.id')->firstOrFail();

        $expense = EventExpense::query()->create([
            'event_id' => $eventA->id,
            'description' => 'Biaya A',
            'amount' => 100000,
            'expense_date' => $this->reportToday()->toDateString(),
            'created_by' => $owner->id,
        ]);

        Sanctum::actingAs($owner);

        $this->putJson("/api/events/{$eventB->id}/expenses/{$expense->id}", [
            'description' => 'Hijack',
            'amount' => 1,
            'expense_date' => $this->reportToday()->toDateString(),
        ])->assertNotFound();
    }

    private function createEvent(string $name = 'Event Biaya Test'): Event
    {
        $bazar = Location::query()->where('location_code', 'BAZ-001')->firstOrFail();
        $sales = User::query()->where('email', 'sales@aksana.id')->firstOrFail();
        $owner = User::query()->where('email', 'owner@aksana.id')->firstOrFail();

        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/events', [
            'location_id' => $bazar->id,
            'name' => $name,
            'start_date' => $this->reportToday()->toDateString(),
            'end_date' => $this->reportToday()->addDays(3)->toDateString(),
            'assignments' => [
                ['user_id' => $sales->id, 'role_in_event' => 'sales'],
            ],
        ]);

        $response->assertCreated();

        return Event::query()->findOrFail($response->json('data.id'));
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
