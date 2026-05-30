<?php

namespace App\Helpers;

class FormatHelper
{
    public static function price(float|int|string|null $amount): string
    {
        $amount = (float) ($amount ?? 0);
        if ($amount >= 1_000_000_000) {
            return 'Rp '.number_format($amount / 1_000_000_000, 1).'M';
        }
        if ($amount >= 1_000_000) {
            return 'Rp '.number_format($amount / 1_000_000, 1).' JT';
        }
        if ($amount >= 1_000) {
            return 'Rp '.number_format($amount / 1_000, 0).' RB';
        }

        return 'Rp '.number_format($amount, 0);
    }

    public static function priceShort(float|int|string|null $amount): string
    {
        $amount = (float) ($amount ?? 0);
        if ($amount >= 1_000_000) {
            return number_format($amount / 1_000_000, 1).' JT';
        }
        if ($amount >= 1_000) {
            return number_format($amount / 1_000, 0).' RB';
        }

        return number_format($amount, 0);
    }
}
