<?php

namespace App\Services\Fiscal;

// ######## INICIO RETIRADA DE EXCEPCION ADMINISTRATIVA IGTF ########
/** No caller, including an administrator, can exempt a new collection. */
final class RetiredIgtfException
{
    public static function assertNew(array $input): void
    {
        if (($input['igtf_status'] ?? null) === 'exempt') {
            FiscalAmounts::error('igtf_status', 'La excepción administrativa de IGTF ya no está disponible. El cobro se clasifica automáticamente.');
        }
        foreach (['exemption_reason', 'exception_basis'] as $field) {
            if (array_key_exists($field, $input) && $input[$field] !== null
                && (!is_string($input[$field]) || trim($input[$field]) !== '')) {
                FiscalAmounts::error($field, 'Este campo de excepción administrativa de IGTF ya no está disponible.');
            }
        }
    }
}
// ######## FIN RETIRADA DE EXCEPCION ADMINISTRATIVA IGTF ########
