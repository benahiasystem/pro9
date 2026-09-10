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
            $show_actions = true;

            // ######### INICIO PROTECCIÓN MÉTODOS INICIALES VENEZUELA #########
            $can_delete = !PaymentMethodType::isInitialPaymentMethodId($row->id);
            // ######### FIN PROTECCIÓN MÉTODOS INICIALES VENEZUELA #########

            if(in_array($row->id, ['01', '05', '08', '09', '04'])){
                $show_actions = false;
            }
            $return = $row->toArray();
            $return['show_actions'] = $show_actions;
            $return['can_delete'] = $can_delete;
            return $return;

            return [
                'id' => $row->id,
                'description' => $row->description,
                'show_actions' => $show_actions
            ];
        });
    }
}
