<?php

namespace Modules\Sale\Http\Resources;

use App\Models\Tenant\PaymentMethodType;
use Illuminate\Http\Resources\Json\ResourceCollection;

class PaymentMethodTypeCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function toArray($request)
    {

        return $this->collection->transform(function($row, $key) {

            /** @var \App\Models\Tenant\PaymentMethodType  $row */
            // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
            $return = $row->toArray();
            $return['show_actions'] = true;
            $return['can_delete'] = !PaymentMethodType::isInitialPaymentMethodId($row->id);
            // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES
            return $return;
        });
    }
}
