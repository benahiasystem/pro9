<?php

namespace Modules\ExtraServices\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ExtraServicesResource extends JsonResource
{
    /**
     * Transforma el recurso en un array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'isActiveApidocs' => (bool) $this->isActiveApidocs,
            'urlObtainApidocs' => $this->urlObtainApidocs,
            'urlServiceApidocs' => $this->urlServiceApidocs,
        ];
    }
}