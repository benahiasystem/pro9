<?php

namespace App\Services\Fiscal;

use App\Models\Tenant\User;
use Illuminate\Database\ConnectionInterface;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
final class FiscalSaleNotePaymentGuard
{
    public static function lockEditable(ConnectionInterface $db, int $id, ?User $actor): object
    {
        return FiscalSaleNoteMutationGuard::lockEditable($db, $id, $actor);
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
