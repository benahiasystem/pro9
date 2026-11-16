<?php

namespace Modules\Finance\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

// ######## INICIO CALCULOS EXACTOS SAB ########
class UnpaidCollection extends ResourceCollection
{
    public function toArray($request)
    {
        return $this->collection->values()->all();
    }
}
// ######## FIN CALCULOS EXACTOS SAB ########
