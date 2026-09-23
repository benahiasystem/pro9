<?php

namespace App\Services\Fiscal;

use Illuminate\Database\ConnectionInterface;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
final class FiscalReservation
{
    /** Keep the original commercial binding; physical replacements have no second sale. */
    public static function effective(ConnectionInterface $db, object $original, bool $lock = false): object
    {
        $current = $original;
        $visited = [];
        while (true) {
            if (isset($visited[$current->id])) {
                throw new \DomainException('La cadena de reemplazos fiscales es inválida.');
            }
            $visited[$current->id] = true;
            $query = $db->table('fiscal_number_reservations')->where('parent_reservation_id', $current->id);
            if ($lock) $query->lockForUpdate();
            $replacement = $query->first();
            if (!$replacement) {
                if ($current->status === 'contingency') {
                    throw new \DomainException('Falta la reserva vinculada a la contingencia.');
                }
                return $current;
            }
            if (!in_array($current->status, ['contingency', 'inutilized'], true)) {
                throw new \DomainException('El reemplazo físico no corresponde al estado de la reserva anterior.');
            }
            $current = $replacement;
        }
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
