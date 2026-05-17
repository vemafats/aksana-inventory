<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class PasswordVerificationService
{
    public function verifyPassword(User $user, string $plainPassword): bool
    {
        return Hash::check($plainPassword, $user->password);
    }

    /**
     * @return array{token: string, expires_at: string}
     */
    public function generateCostViewToken(User $user): array
    {
        $expiresAt = now()->addMinutes(15);

        $payload = json_encode([
            'user_id' => $user->id,
            'expires_at' => $expiresAt->timestamp,
        ], JSON_THROW_ON_ERROR);

        $signature = hash_hmac('sha256', $payload, (string) config('app.key'));

        $token = base64_encode(json_encode([
            'payload' => base64_encode($payload),
            'signature' => $signature,
        ], JSON_THROW_ON_ERROR));

        return [
            'token' => $token,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public function validateCostViewToken(?string $token, User $user): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        try {
            $envelope = json_decode(base64_decode($token, true) ?: '', true, 512, JSON_THROW_ON_ERROR);
            $payloadJson = base64_decode($envelope['payload'] ?? '', true);
            $signature = $envelope['signature'] ?? '';

            if ($payloadJson === false || $signature === '') {
                return false;
            }

            $expectedSignature = hash_hmac('sha256', $payloadJson, (string) config('app.key'));

            if (! hash_equals($expectedSignature, $signature)) {
                return false;
            }

            /** @var array{user_id: string, expires_at: int} $payload */
            $payload = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);

            if ($payload['user_id'] !== $user->id) {
                return false;
            }

            if (now()->timestamp > $payload['expires_at']) {
                return false;
            }

            return true;
        } catch (\JsonException) {
            return false;
        }
    }
}
