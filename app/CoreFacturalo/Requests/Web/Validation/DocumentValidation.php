<?php

namespace App\CoreFacturalo\Requests\Web\Validation;

use App\Services\SalesDocumentTypePolicy;

class DocumentValidation
{
    public static function validation($inputs) {
        // ########## INICIO CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
        SalesDocumentTypePolicy::assertNewFiscalDocumentAllowed($inputs['document_type_id'] ?? null);
        // ######### FIN CAMBIO SOLO FACTURAS Y NOTAS DE VENTA

        // ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
        $inputs = \App\Services\Fiscal\FiscalWebDocumentContext::prepare($inputs);
        // ######## FIN NUMERACIÓN FISCAL VENEZUELA ########

        Functions::DNI($inputs);
        Functions::identityDocumentTypeInvoice($inputs);

        return $inputs;
    }
}
