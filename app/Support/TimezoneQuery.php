<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TimezoneQuery
{
    public const TIMEZONE = 'Asia/Jakarta';

    public static function whereDateEquals(Builder $query, string $column, string $date): Builder
    {
        if (self::usesPostgresTimezone()) {
            return $query->whereRaw(
                "DATE({$column} AT TIME ZONE ?) = ?",
                [self::TIMEZONE, $date],
            );
        }

        return $query->whereDate($column, $date);
    }

    public static function whereDateFrom(Builder $query, string $column, string $date): Builder
    {
        if (self::usesPostgresTimezone()) {
            return $query->whereRaw(
                "DATE({$column} AT TIME ZONE ?) >= ?",
                [self::TIMEZONE, $date],
            );
        }

        return $query->whereDate($column, '>=', $date);
    }

    public static function whereDateTo(Builder $query, string $column, string $date): Builder
    {
        if (self::usesPostgresTimezone()) {
            return $query->whereRaw(
                "DATE({$column} AT TIME ZONE ?) <= ?",
                [self::TIMEZONE, $date],
            );
        }

        return $query->whereDate($column, '<=', $date);
    }

    public static function todayDateString(): string
    {
        return now(self::TIMEZONE)->toDateString();
    }

    private static function usesPostgresTimezone(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }
}
