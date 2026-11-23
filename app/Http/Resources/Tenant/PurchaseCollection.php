<?php
// ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;

/**
 * Class PurchaseCollection
 *
 * @package App\Http\Resources\Tenant
 */
class PurchaseCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return Collection
     */
    public function toArray($request)
    {
        return $this->collection->transform(function($row, $key) {

            /** @var \App\Models\Tenant\Purchase  $row */
            return $row->getCollectionData();
        });
    }

}
// ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
