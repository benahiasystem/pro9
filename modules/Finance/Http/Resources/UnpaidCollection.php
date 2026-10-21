<?php

namespace Modules\Finance\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
class UnpaidCollection extends ResourceCollection
{
    public function toArray($request)
    {
        return $this->collection->values()->all();
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
