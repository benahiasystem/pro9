<?php
// ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########

namespace App\CoreFacturalo\Requests\Inputs;

use Illuminate\Support\Str;
use App\Models\Tenant\{
    Company,
    Summary
};

class SummaryInput
{
    public static function set($inputs) {
        $company = Company::active();
        $fiscal_environment = $company->fiscal_environment;
        
        $date_of_reference = $inputs['date_of_reference'];
        $date_of_issue = date('Y-m-d');
        $summary_status_type_id = $inputs['summary_status_type_id'];
        
        $identifier = Functions::identifier($fiscal_environment, $date_of_issue, Summary::class);
        $filename = $company->number.'-'.$identifier;
        $inputs['type'] = 'summary';
        
        return [
            'type' => $inputs['type'],
            'user_id' => auth()->id(),
            'external_id' => Str::uuid(),
            'fiscal_environment' => $fiscal_environment,
            'state_type_id' => '01',
            'summary_status_type_id' => $summary_status_type_id,
            'ubl_version' => '2.0',
            'date_of_issue' => $date_of_issue,
            'date_of_reference' => $date_of_reference,
            'identifier' => $identifier,
            'filename' => $filename,
            'unique_filename' => $filename,
            'documents' => $inputs['documents']
        ];
    }
}
// ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
