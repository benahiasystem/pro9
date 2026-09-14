<?php

namespace App\Services\Fiscal;

use Illuminate\Database\ConnectionInterface;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
final class FiscalReservation
{
    /** Keep the original commercial binding; its physical replacement has no second sale. */
    public static function effective(ConnectionInterface $db, object $original, bool $lock = false): object
    {
        if ($original->status !== 'contingency') return $original;
        $query = $db->table('fiscal_number_reservations')->where('parent_reservation_id', $original->id);
        if ($lock) $query->lockForUpdate();
        $replacement = $query->first();
        if (!$replacement) throw new \DomainException('Falta la reserva vinculada a la contingencia.');
        return $replacement;
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
