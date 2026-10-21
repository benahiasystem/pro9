<?php

namespace App\Support\Exact;

use App\Support\Venezuela\Localization;
use Illuminate\Validation\ValidationException;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
final class CheckoutAvailability
{
    /** Enforce the existing enabled-checkout decision on direct POSTs as well. */
    public static function assertAvailable(): void
    {
        if (Localization::nationalCurrencyId() === 'VES') {
            throw ValidationException::withMessages(['currency'=>'Las pasarelas configuradas no admiten cobros en bolívares.']);
        }
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
