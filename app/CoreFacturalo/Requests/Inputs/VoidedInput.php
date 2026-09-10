<?php
// ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########

namespace App\CoreFacturalo\Requests\Inputs;

use App\Models\Tenant\Company;
use App\Models\Tenant\Voided;
use Illuminate\Support\Str;

class VoidedInput
{
    public static function set($inputs)
    {
        $company = Company::active();
        $fiscal_environment = $company->fiscal_environment;

        $date_of_reference = $inputs['date_of_reference'];
        $date_of_issue = date('Y-m-d');

        $identifier = Functions::identifier($fiscal_environment, $date_of_issue, Voided::class);
        $filename = $company->number.'-'.$identifier;
        $inputs['type'] = 'voided';

        return [
            'type' => $inputs['type'],
            'user_id' => auth()->id(),
            'external_id' => Str::uuid(),
            'fiscal_environment' => $fiscal_environment,
            'state_type_id' => '01',
            'ubl_version' => '2.0',
            'date_of_issue' => $date_of_issue,
            'date_of_reference' => $date_of_reference,
            'identifier' => $identifier,
            'filename' => $filename,
            'documents' => $inputs['documents']
//            'actions' => ActionInput::set($inputs),
        ];
    }
}
// ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
