<?php

namespace App\Services;

/**
 * PasswordVerificationService
 *
 * Responsibilities:
 * - verify password for Owner-only flows (e.g. harga modal / cost view token)
 * - integrate with POST /api/verify-password
 *
 * Implemented in: later milestone (auth + token issuance)
 */
class PasswordVerificationService
{
    // TODO: wire to User model + hashing + token TTL
}
