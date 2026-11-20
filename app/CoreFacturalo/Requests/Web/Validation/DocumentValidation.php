<?php

namespace App\CoreFacturalo\Requests\Web\Validation;

use App\Services\SalesDocumentTypePolicy;
use App\CoreFacturalo\Requests\Inputs\Common\EstablishmentInput;

class DocumentValidation
{
    public static function validation($inputs) {
        // ########## INICIO CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
        SalesDocumentTypePolicy::assertNewFiscalDocumentAllowed($inputs['document_type_id'] ?? null);
        // ######### FIN CAMBIO SOLO FACTURAS Y NOTAS DE VENTA

        if (in_array($inputs['document_type_id'], ['01', '07', '08'], true)) {
            EstablishmentInput::validatedId($inputs['establishment_id'] ?? null);
        }
        $series = Functions::findSeries($inputs);
        $inputs['series'] = $series->number;
        unset($inputs['series_id']);

        Functions::DNI($inputs);
        Functions::identityDocumentTypeInvoice($inputs);

        return $inputs;
    }
}
