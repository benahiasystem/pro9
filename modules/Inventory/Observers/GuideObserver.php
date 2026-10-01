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
        return \App\Services\SeriesNumbering::next($guide, $guide->document_type_id, $guide->series, $guide->number, auth()->user()->establishment_id);

    }
}
// ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
