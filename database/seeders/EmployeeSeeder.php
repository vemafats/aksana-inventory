<?php

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $employees = [
            [
                'employee_code' => 'EMP001',
                'name' => 'Agus Wijaya',
                'phone' => '081234567890',
                'email' => 'gudang@aksana.id',
            ],
            [
                'employee_code' => 'EMP002',
                'name' => 'Rina Kusuma',
                'phone' => '081234567891',
                'email' => 'picbazar@aksana.id',
            ],
            [
                'employee_code' => 'EMP003',
                'name' => 'Doni Pratama',
                'phone' => '081234567892',
                'email' => 'sales@aksana.id',
            ],
            [
                'employee_code' => 'EMP004',
                'name' => 'Maya Sari',
                'phone' => '081234567893',
                'email' => 'maya@aksana.id',
            ],
        ];

        foreach ($employees as $employee) {
            Employee::updateOrCreate(
                ['employee_code' => $employee['employee_code']],
                [
                    'name' => $employee['name'],
                    'phone' => $employee['phone'],
                    'email' => $employee['email'],
                    'is_active' => true,
                ],
            );
        }
    }
}
