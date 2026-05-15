<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()
            ->where('email', $request->validated('email'))
            ->where('is_active', true)
            ->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Email tidak ditemukan atau akun tidak aktif',
            ], 401);
        }

        if (! Hash::check($request->validated('password'), $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password salah',
            ], 401);
        }

        $user->tokens()->delete();

        $expiresAt = now()->addDays(30);
        $token = $user->createToken('aksana-mobile', ['*'], $expiresAt);

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'data' => [
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $expiresAt->toDateTimeString(),
                'user' => $this->formatUser($user),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'is_active' => $user->is_active,
                'assigned_locations' => $this->getAssignedLocations($user),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'is_active' => $user->is_active,
        ];
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    private function getAssignedLocations(User $user): array
    {
        $employee = Employee::query()
            ->where('name', $user->name)
            ->first();

        if (! $employee) {
            return [];
        }

        return $employee->locationAssignments()
            ->where('is_active', true)
            ->with('location:id,location_name')
            ->get()
            ->map(fn ($assignment) => [
                'id' => $assignment->location->id,
                'name' => $assignment->location->location_name,
            ])
            ->values()
            ->all();
    }
}
