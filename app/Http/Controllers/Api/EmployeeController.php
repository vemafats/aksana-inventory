<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $employees = Employee::query()
            ->where('is_active', true)
            ->with([
                'locationAssignments' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('created_at'),
            ])
            ->orderBy('name')
            ->get(['id', 'employee_code', 'name']);

        return response()->json([
            'success' => true,
            'data' => $employees->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'employee_name' => $employee->name,
                'nik' => $employee->employee_code,
                'role' => $employee->locationAssignments->first()?->role,
            ])->values(),
        ]);
    }
}
