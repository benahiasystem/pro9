<?php
// ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########

namespace App\CoreFacturalo\Requests\Api\Validation;

use App\Models\Tenant\Company;
use App\Models\Tenant\Document;
use Exception;

class SummaryValidation
{
    public static function validation($inputs)
    {
        if($inputs['summary_status_type_id'] === '3') {
            $inputs['documents'] = Functions::voidedDocuments($inputs, 'summary');
        } else {
            $inputs['documents'] = self::findDocuments($inputs);
        }
        return $inputs;
    }

    private static function findDocuments($inputs)
    {
        $company = Company::active();
        
        $documents = Document::filterDocumentsForSummary($inputs['date_of_reference'], $company->fiscal_environment)->get();

        // $documents = Document::where('date_of_issue', $inputs['date_of_reference'])
        //                     ->where('fiscal_environment', $company->fiscal_environment)
        //                     ->where('group_id', '02')
        //                     ->where('state_type_id', '01')
        //                     ->take(500)
        //                     ->get();

        if($documents->count() === 0) {
            throw new Exception("No se encontraron documentos con fecha de emisión {$inputs['date_of_reference']}.");
        }

        $docs = [];
        foreach ($documents as $row)
        {
            $docs[] = [
                'document_id' => $row->id
            ];
        }
        return $docs;
    }
}
// ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
