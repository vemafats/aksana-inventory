<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            CategorySeeder::class,
            BrandSeeder::class,
            ColorSeeder::class,
            SizeSeeder::class,
            EmployeeSeeder::class,
            LocationSeeder::class,
            ProductModelSeeder::class,
            SettingSeeder::class,
            LocationAssignmentSeeder::class,
        ]);
    }
}
