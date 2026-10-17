<?php

namespace Modules\Report\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
/** Controller supplies the same exact DTO used by exports and PDFs. */
class SaleConsolidatedCollection extends ResourceCollection
{
    public function toArray($request)
    {
        return $this->collection->all();
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
