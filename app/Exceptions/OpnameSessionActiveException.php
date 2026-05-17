<?php

namespace App\Exceptions;

use RuntimeException;

class OpnameSessionActiveException extends RuntimeException
{
    public function __construct(
        public readonly string $activeSessionId,
        string $message = '',
    ) {
        parent::__construct(
            $message ?: "Ada sesi opname aktif (ID: {$activeSessionId}). Selesaikan dulu sebelum memulai sesi baru."
        );
    }
}
