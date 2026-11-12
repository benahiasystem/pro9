<?php

namespace App\Services\Sellnow;

use App\Models\Tenant\Cash;

/** Explicit, already-authorized cash for one VendeYa transaction only. */
final class CashContext
{
    private static ?Cash $cash = null;

    public static function current(): ?Cash
    {
        return self::$cash;
    }

    public static function run(Cash $cash, callable $callback)
    {
        $previous = self::$cash;
        self::$cash = $cash;
        try {
            return $callback();
        } finally {
            self::$cash = $previous;
        }
    }
}
