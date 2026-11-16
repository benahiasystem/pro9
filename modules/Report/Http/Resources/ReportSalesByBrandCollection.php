<?php

namespace Modules\Report\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class ReportSalesByBrandCollection extends ResourceCollection
{
    /**
     * Transforma la colección de recursos en un array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        // ######## INICIO CALCULOS EXACTOS SAB ########
        return \Modules\Report\Services\BrandSalesAmounts::presentation($this->collection)['rows'];
        // ######## FIN CALCULOS EXACTOS SAB ########
    }
}
