<?php
// ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########

namespace Modules\Inventory\Observers;

use App\Models\Tenant\Company;
use Illuminate\Support\Str;
use Modules\Inventory\Models\Guide;

class GuideObserver
{
    public function creating(Guide $guide)
    {
        $company = Company::query()->first();

        $guide->user_id = auth()->id();
        $guide->external_id = Str::uuid()->toString();
        $guide->fiscal_environment = $company->fiscal_environment;

        $number = $this->getNumberDocument($guide);
        $filename = join('-', [$company->number, $guide->document_type_id, $guide->series, $number]);
        $guide->number = $number;
        $guide->filename = $filename;
    }

    private function getNumberDocument($guide)
    {
        if ($guide->number === '#') {
            $record = Guide::query()
                ->select('number')
                ->where('fiscal_environment', $guide->fiscal_environment)
                ->where('document_type_id', $guide->document_type_id)
                ->where('series', $guide->series)
                ->orderBy('number', 'desc')
                ->first();

            return ($record) ? $record->number + 1 : 1;
        }

        return $guide->number;
    }
}
// ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
