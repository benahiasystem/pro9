<?php

namespace Modules\Finance\Services;

use App\Models\Tenant\{BankAccount, Cash};
use Modules\Finance\Models\GlobalPayment;

/** Current catalogue reference, separate from the frozen destination label. */
final class CurrentDestinationReference
{
    public static function forPayment(GlobalPayment $payment): ?string
    {
        if ($payment->destination_type === Cash::class) return 'Caja #'.$payment->destination_id;
        if ($payment->destination_type !== BankAccount::class) return null;
        $number = $payment->destination?->number;
        return $number === null || $number === '' ? null : (string) $number;
    }
}
