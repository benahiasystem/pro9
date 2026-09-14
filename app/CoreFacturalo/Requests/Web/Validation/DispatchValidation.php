<?php

namespace App\CoreFacturalo\Requests\Web\Validation;

use Exception;

class DispatchValidation
{
    public static function validation($inputs)
    {
        // ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
        if (($inputs['document_type_id'] ?? null) !== '09') {
            throw new \DomainException('Tipo de orden de entrega no admitido.');
        }
        return \App\Services\Fiscal\FiscalWebDocumentContext::prepare($inputs);
        // ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
    }
}
