<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\PasswordVerificationService;
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

    public function verifyPassword(Request $request, PasswordVerificationService $passwordVerificationService): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if ($user->role !== UserRole::OWNER) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak',
            ], 403);
        }

        if (! $passwordVerificationService->verifyPassword($user, $request->string('password')->toString())) {
            return response()->json([
                'success' => false,
                'message' => 'Password tidak sesuai',
            ], 422);
        }

        $tokenData = $passwordVerificationService->generateCostViewToken($user);

        return response()->json([
            'success' => true,
            'data' => [
                'cost_view_token' => $tokenData['token'],
                'expires_at' => $tokenData['expires_at'],
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load([
            'activeLocationAssignment.location:id,location_name,location_type,status',
        ]);

        $primary = $user->activeLocationAssignment;

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'nik' => $user->nik,
                'position' => $user->position,
                'is_active' => $user->is_active,
                'location_id' => $primary?->location_id,
                'location_name' => $primary?->location?->location_name,
                'location_type' => $primary?->location?->location_type?->value
                    ?? $primary?->location?->location_type,
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
            'nik' => $user->nik,
            'position' => $user->position,
            'is_active' => $user->is_active,
        ];
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    private function getAssignedLocations(User $user): array
    {
        return $user->locationAssignments()
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
