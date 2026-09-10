<?php
// ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Resources\Json\JsonResource;

class RetentionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request
     * @return array
     */
    public function toArray($request)
    {

        $response_message = null;
        $response_type = null;
        $code = null;



        return [
            'id' => $this->id,
            'number_full' => $this->number_full,
            'state_type_id' => $this->state_type_id,
            'supplier_email' => $this->supplier->email,
            'response_message' => in_array($this->state_type_id, ['07', '09']) ? ($code ? "{$code} - " : '')."{$response_message}" : $response_message,
            'response_type' => $response_type,
            'download_cdr' => $this->download_external_cdr,
        ];
        
    }
}
// ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
