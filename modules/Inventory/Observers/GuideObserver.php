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
        $guide->establishment_id = \Modules\Inventory\Models\Warehouse::query()->findOrFail($guide->warehouse_id)->establishment_id;
        $guide->series = \App\Services\SeriesNumbering::normalizeCode($guide->series);
        $guide->external_id = Str::uuid()->toString();
        $guide->fiscal_environment = $company->fiscal_environment;

        $number = $this->getNumberDocument($guide);
        $filename = \App\CoreFacturalo\Requests\Inputs\Functions::filename($company, $guide->document_type_id, $guide->series, $number, $guide->establishment_id);
        $guide->number = $number;
        $guide->filename = $filename;
    }

    private function getNumberDocument($guide)
    {
        return \App\Services\SeriesNumbering::next($guide, $guide->document_type_id, $guide->series, $guide->number, $guide->establishment_id);

    }
}
// ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
