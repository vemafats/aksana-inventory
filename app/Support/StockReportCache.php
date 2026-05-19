<?php

namespace App\Support;

use App\Enums\UserRole;
use Illuminate\Support\Facades\Cache;

class StockReportCache
{
    public static function dashboardCacheKey(string $userId, UserRole $role): string
    {
        $version = (int) Cache::get('dashboard_summary_version', 0);

        return "dashboard_summary_v{$version}_{$userId}_{$role->value}";
    }

    public static function lowStockCacheKey(UserRole $role): string
    {
        return 'low_stock_'.$role->value;
    }

    public static function invalidate(): void
    {
        foreach (UserRole::cases() as $role) {
            Cache::forget(self::lowStockCacheKey($role));
        }

        Cache::put(
            'dashboard_summary_version',
            (int) Cache::get('dashboard_summary_version', 0) + 1,
            now()->addDay(),
        );
    }
}
