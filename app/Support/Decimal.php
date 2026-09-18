<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Exact decimal arithmetic for money values.
 *
 * `bcmath` isn't installed on this environment (see
 * docs/IMPLEMENTATION_PLAN.md, Required Change #3), and spec §6 forbids PHP
 * float for accounting figures. Every value here already came out of a
 * NUMERIC(20,2) column as an exact decimal string (confirmed empirically:
 * pdo_pgsql returns SUM() over numeric as a string, not a float — see
 * JournalPostingService). These helpers keep it that way: the handful of
 * remaining operations reports need (adding two totals together, comparing
 * Assets to Liabilities+Equity) are pushed through Postgres as a scalar
 * `::numeric` expression instead of being computed in PHP.
 */
class Decimal
{
    public static function add(string $a, string $b): string
    {
        return (string) DB::selectOne('select (?::numeric + ?::numeric) as result', [$a, $b])->result;
    }

    public static function subtract(string $a, string $b): string
    {
        return (string) DB::selectOne('select (?::numeric - ?::numeric) as result', [$a, $b])->result;
    }

    public static function equals(string $a, string $b): bool
    {
        return (bool) DB::selectOne('select (?::numeric = ?::numeric) as result', [$a, $b])->result;
    }
}
