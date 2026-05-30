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
                'nik' => 'OWN001',
                'position' => 'Owner',
            ],
            [
                'name' => 'Sari Dewi',
                'email' => 'admin@aksana.id',
                'role' => 'admin',
                'nik' => 'ADM001',
                'position' => 'Admin',
            ],
            [
                'name' => 'Agus Wijaya',
                'email' => 'gudang@aksana.id',
                'role' => 'admin_gudang',
                'nik' => 'EMP001',
                'position' => 'Admin Gudang',
            ],
            [
                'name' => 'Rina Kusuma',
                'email' => 'picbazar@aksana.id',
                'role' => 'pic_bazar',
                'nik' => 'EMP002',
                'position' => 'PIC Bazar',
            ],
            [
                'name' => 'Doni Pratama',
                'email' => 'sales@aksana.id',
                'role' => 'sales',
                'nik' => 'EMP003',
                'position' => 'Sales',
            ],
            [
                'name' => 'Maya Sari',
                'email' => 'maya@aksana.id',
                'role' => 'pic_bazar',
                'nik' => 'EMP004',
                'position' => 'PIC Bazar',
            ],
        ];

        foreach ($users as $user) {
            if (User::where('email', $user['email'])->exists()) {
                User::where('email', $user['email'])->update([
                    'nik' => $user['nik'],
                    'position' => $user['position'],
                ]);

                continue;
            }

            $now = now();

            DB::table('users')->insert([
                'id' => (string) Str::uuid(),
                'name' => $user['name'],
                'email' => $user['email'],
                'password' => bcrypt('password'),
                'role' => $user['role'],
                'nik' => $user['nik'],
                'position' => $user['position'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
