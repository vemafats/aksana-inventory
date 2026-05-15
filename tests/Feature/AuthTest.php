<?php

namespace Tests\Feature;

use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(UserSeeder::class);
    }

    public function test_user_can_login_with_correct_credentials(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'owner@aksana.id',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'token_type',
                    'expires_at',
                    'user' => ['id', 'name', 'email', 'role', 'is_active'],
                ],
            ])
            ->assertJsonPath('data.user.role', 'owner');
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'owner@aksana.id',
            'password' => 'wrongpassword',
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath('message', 'Password salah');
    }

    public function test_login_fails_with_inactive_user(): void
    {
        DB::table('users')->insert([
            'id' => (string) Str::uuid(),
            'name' => 'Inactive User',
            'email' => 'inactive@aksana.id',
            'password' => bcrypt('password'),
            'role' => 'sales',
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'inactive@aksana.id',
            'password' => 'password',
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath('message', 'Email tidak ditemukan atau akun tidak aktif');
    }

    public function test_authenticated_user_can_get_profile(): void
    {
        $login = $this->postJson('/api/login', [
            'email' => 'owner@aksana.id',
            'password' => 'password',
        ]);

        $token = $login->json('data.token');

        $response = $this->withToken($token)->getJson('/api/me');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'owner@aksana.id')
            ->assertJsonPath('data.role', 'owner');
    }

    public function test_user_can_logout(): void
    {
        $login = $this->postJson('/api/login', [
            'email' => 'owner@aksana.id',
            'password' => 'password',
        ]);

        $token = $login->json('data.token');

        $this->withToken($token)
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logout berhasil');

        Auth::forgetGuards();

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertUnauthorized();
    }
}
