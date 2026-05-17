<?php

namespace Tests\Feature;

use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerifyPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(UserSeeder::class);
    }

    public function test_owner_can_verify_password_and_receive_cost_view_token(): void
    {
        $login = $this->postJson('/api/login', [
            'email' => 'owner@aksana.id',
            'password' => 'password',
        ]);

        $response = $this->withToken($login->json('data.token'))
            ->postJson('/api/verify-password', [
                'password' => 'password',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['cost_view_token', 'expires_at'],
            ]);
    }

    public function test_non_owner_cannot_verify_password(): void
    {
        $login = $this->postJson('/api/login', [
            'email' => 'admin@aksana.id',
            'password' => 'password',
        ]);

        $response = $this->withToken($login->json('data.token'))
            ->postJson('/api/verify-password', [
                'password' => 'password',
            ]);

        $response->assertForbidden()
            ->assertJsonPath('message', 'Akses ditolak');
    }

    public function test_wrong_password_returns_422(): void
    {
        $login = $this->postJson('/api/login', [
            'email' => 'owner@aksana.id',
            'password' => 'password',
        ]);

        $response = $this->withToken($login->json('data.token'))
            ->postJson('/api/verify-password', [
                'password' => 'wrong-password',
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Password tidak sesuai');
    }
}
