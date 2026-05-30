<?php

namespace App\Helpers;

class SupplierCostHelper
{
    /**
     * Normalize placeholder costs (0 or legacy mobile default 1) to 0.
     */
    public static function normalize(float|int|string|null $cost): float
    {
        $cost = (float) ($cost ?? 0);

        return $cost <= 1 ? 0.0 : $cost;
    }

    /**
     * Whether supplier cost is unset / placeholder (0 or legacy Rp 1 from mobile).
     */
    public static function isUnset(float|int|string|null $cost): bool
    {
        return (float) ($cost ?? 0) <= 1;
    }
}
