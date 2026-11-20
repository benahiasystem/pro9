<?php

namespace App\CoreFacturalo\Requests\Web\Validation;

use App\CoreFacturalo\Requests\Inputs\Common\EstablishmentInput;

class DocumentUpdateValidation
{
    public static function validation($inputs) {
        if (($inputs['document_type_id'] ?? null) === '01') {
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
