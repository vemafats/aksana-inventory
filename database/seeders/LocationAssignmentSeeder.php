<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Location;
use App\Models\LocationAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LocationAssignmentSeeder extends Seeder
{
    public function run(): void
    {
        $assignments = [
            [
                'employee_code' => 'EMP001',
                'location_code' => 'GUD-001',
                'role' => 'admin_gudang',
            ],
            [
                'employee_code' => 'EMP002',
                'location_code' => 'BAZ-001',
                'role' => 'pic_bazar',
            ],
            [
                'employee_code' => 'EMP003',
                'location_code' => 'BAZ-001',
                'role' => 'sales',
            ],
            [
                'employee_code' => 'EMP004',
                'location_code' => 'BAZ-002',
                'role' => 'pic_bazar',
            ],
        ];

        foreach ($assignments as $assignment) {
            $employee = Employee::where('employee_code', $assignment['employee_code'])->first();
            $location = Location::where('location_code', $assignment['location_code'])->first();

            if (! $employee || ! $location) {
                continue;
            }

            $user = User::query()
                ->where(function ($query) use ($employee): void {
                    $query->where('email', $employee->email)
                        ->orWhere('name', $employee->name);
                })
                ->first();

            $record = LocationAssignment::firstOrNew([
                'location_id' => $location->id,
                'employee_id' => $employee->id,
            ]);

            if (! $record->exists) {
                $record->id = (string) Str::uuid();
            }

            $record->fill([
                'user_id' => $user?->id,
                'role' => $assignment['role'],
                'start_date' => null,
                'end_date' => null,
                'is_active' => true,
            ]);
            $record->save();
        }
    }
}
