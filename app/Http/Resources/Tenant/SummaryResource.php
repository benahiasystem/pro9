<?php
// ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Resources\Json\JsonResource;

class SummaryResource extends JsonResource
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



        return [
            'id' => $this->id,
            'identifier' => $this->identifier,
            'date_of_issue' => $this->date_of_issue->format('Y-m-d'),
            'response_message' => $response_message,
            'response_type' => $response_type,
            'download_cdr' => $this->download_external_cdr,
            'unknown_error_status_response' => $this->unknown_error_status_response,
            'manually_regularized' => $this->manually_regularized,
            'error_manually_regularized' => $this->error_manually_regularized,

        ];
    }
}
// ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
