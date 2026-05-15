<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Budi Santoso',
                'email' => 'owner@aksana.id',
                'role' => 'owner',
            ],
            [
                'name' => 'Sari Dewi',
                'email' => 'admin@aksana.id',
                'role' => 'admin',
            ],
            [
                'name' => 'Agus Wijaya',
                'email' => 'gudang@aksana.id',
                'role' => 'admin_gudang',
            ],
            [
                'name' => 'Rina Kusuma',
                'email' => 'picbazar@aksana.id',
                'role' => 'pic_bazar',
            ],
            [
                'name' => 'Doni Pratama',
                'email' => 'sales@aksana.id',
                'role' => 'sales',
            ],
        ];

        foreach ($users as $user) {
            if (User::where('email', $user['email'])->exists()) {
                continue;
            }

            $now = now();

            DB::table('users')->insert([
                'id' => (string) Str::uuid(),
                'name' => $user['name'],
                'email' => $user['email'],
                'password' => bcrypt('password'),
                'role' => $user['role'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
