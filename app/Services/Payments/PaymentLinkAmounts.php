<?php

namespace App\Services\Payments;

use App\Models\Tenant\Document;
use App\Support\Exact\Decimal as D;
use Illuminate\Validation\ValidationException;

/** Payment links agree cent settlements; the invoice retains its original fraction. */
final class PaymentLinkAmounts
{
    public static function input($value, string $field = 'total'): string
    {
        $amount = D::of(D::input($value, $field));
        $settlement = D::represent($amount, 2, true);
        if ($amount->isNegative() || D::of($settlement)->isLessThanOrEqualTo(0)) {
            throw ValidationException::withMessages([$field => 'Indique un importe a cobrar mayor a cero.']);
        }
        return $settlement;
    }

    public static function pending(Document $document): string
    {
        return D::represent(D::max(0, $document->exactBalance()), 2, true);
    }
}
