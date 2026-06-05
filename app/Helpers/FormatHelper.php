<?php

namespace App\Helpers;

class FormatHelper
{
    public static function price(float|int|string|null $amount): string
    {
        $amount = (float) ($amount ?? 0);

        return 'Rp '.number_format($amount, 0, ',', '.');
    }

    /** @deprecated Use price() — kept for backward compatibility */
    public static function priceShort(float|int|string|null $amount): string
    {
        return self::price($amount);
    }
}
