<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TimezoneQuery
{
    public const TIMEZONE = 'Asia/Jakarta';

    /**
     * TIMESTAMP columns only (e.g. sales_transactions.transaction_date, created_at).
     */
    public static function whereTimestampEquals(Builder $query, string $column, string $date): Builder
    {
        if (self::usesPostgresTimezone()) {
            return $query->whereRaw(
                "DATE({$column} AT TIME ZONE ?) = ?",
                [self::TIMEZONE, $date],
            );
        }

        return $query->whereDate($column, $date);
    }

    /**
     * TIMESTAMP columns only.
     */
    public static function whereTimestampFrom(Builder $query, string $column, string $date): Builder
    {
        if (self::usesPostgresTimezone()) {
            return $query->whereRaw(
                "DATE({$column} AT TIME ZONE ?) >= ?",
                [self::TIMEZONE, $date],
            );
        }

        return $query->whereDate($column, '>=', $date);
    }

    /**
     * TIMESTAMP columns only.
     */
    public static function whereTimestampTo(Builder $query, string $column, string $date): Builder
    {
        if (self::usesPostgresTimezone()) {
            return $query->whereRaw(
                "DATE({$column} AT TIME ZONE ?) <= ?",
                [self::TIMEZONE, $date],
            );
        }

        return $query->whereDate($column, '<=', $date);
    }

    /**
     * PostgreSQL DATE columns only (e.g. transfer_date, opname_date). Do not use AT TIME ZONE.
     * SQLite stores dates with a time component; use whereDate there so comparisons work in tests.
     */
    public static function whereDateColumnEquals(Builder $query, string $column, string $date): Builder
    {
        if (self::usesSqlite()) {
            return $query->whereDate($column, $date);
        }

        return $query->where($column, $date);
    }

    /**
     * PostgreSQL DATE columns only.
     */
    public static function whereDateColumnFrom(Builder $query, string $column, string $date): Builder
    {
        if (self::usesSqlite()) {
            return $query->whereDate($column, '>=', $date);
        }

        return $query->where($column, '>=', $date);
    }

    /**
     * PostgreSQL DATE columns only.
     */
    public static function whereDateColumnTo(Builder $query, string $column, string $date): Builder
    {
        if (self::usesSqlite()) {
            return $query->whereDate($column, '<=', $date);
        }

        return $query->where($column, '<=', $date);
    }

    /** @deprecated Use whereTimestampEquals — TIMESTAMP columns only */
    public static function whereDateEquals(Builder $query, string $column, string $date): Builder
    {
        return self::whereTimestampEquals($query, $column, $date);
    }

    /** @deprecated Use whereTimestampFrom — TIMESTAMP columns only */
    public static function whereDateFrom(Builder $query, string $column, string $date): Builder
    {
        return self::whereTimestampFrom($query, $column, $date);
    }

    /** @deprecated Use whereTimestampTo — TIMESTAMP columns only */
    public static function whereDateTo(Builder $query, string $column, string $date): Builder
    {
        return self::whereTimestampTo($query, $column, $date);
    }

    public static function todayDateString(): string
    {
        return now(self::TIMEZONE)->toDateString();
    }

    public static function dateForDaysAgo(int $daysAgo = 0): string
    {
        return now(self::TIMEZONE)->subDays($daysAgo)->toDateString();
    }

    private static function usesPostgresTimezone(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }

    private static function usesSqlite(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite';
    }
}
