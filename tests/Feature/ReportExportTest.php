<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(UserSeeder::class);
    }

    public function test_owner_can_download_gross_profit_export(): void
    {
        $owner = User::query()->where('email', 'owner@aksana.id')->firstOrFail();

        $response = $this->actingAs($owner)->get(route('reports.gross-profit.export', [
            'from' => '2026-01-01',
            'to' => '2026-01-31',
        ]));

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );
        $this->assertStringContainsString(
            'laporan-gross-profit-2026-01-01-2026-01-31.xlsx',
            (string) $response->headers->get('content-disposition'),
        );
    }

    public function test_admin_cannot_download_gross_profit_export(): void
    {
        $admin = User::query()->where('email', 'admin@aksana.id')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('reports.gross-profit.export'))
            ->assertForbidden();
    }
}
