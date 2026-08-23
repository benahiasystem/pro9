<?php

namespace App\CoreFacturalo\Requests\Web\Validation;

use App\Services\SalesDocumentTypePolicy;

class DocumentValidation
{
    public static function validation($inputs) {
        // ########## INICIO CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
        SalesDocumentTypePolicy::assertNewFiscalDocumentAllowed($inputs['document_type_id'] ?? null);
        // ######### FIN CAMBIO SOLO FACTURAS Y NOTAS DE VENTA

        $series = Functions::findSeries($inputs);
        $inputs['series'] = $series->number;
        unset($inputs['series_id']);

        Functions::DNI($inputs);
        Functions::identityDocumentTypeInvoice($inputs);

        return $inputs;
    }
}
