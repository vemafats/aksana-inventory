<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    /**
     * @deprecated Use users table — kept for mobile backward compatibility.
     */
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->where('is_active', true)
            ->whereIn('role', [
                UserRole::ADMIN_GUDANG,
                UserRole::PIC_BAZAR,
                UserRole::SALES,
            ])
            ->with([
                'locationAssignments' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('created_at'),
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'nik', 'role', 'position']);

        return response()->json([
            'success' => true,
            'data' => $users->map(fn (User $user) => [
                'id' => $user->id,
                'employee_name' => $user->name,
                'nik' => $user->nik,
                'role' => $user->locationAssignments->first()?->role ?? $user->role->value,
            ])->values(),
        ]);
    }
}
