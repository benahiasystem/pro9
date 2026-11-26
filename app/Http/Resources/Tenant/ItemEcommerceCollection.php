<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Resources\Json\ResourceCollection;

class ItemEcommerceCollection extends ResourceCollection
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
            return [
                'id' => $row->id,
                'description' => $row->description,
                'name' => $row->name,
                'second_name' => number_format($row->second_name, 2),
                'sale_unit_price' => $row->sale_unit_price,
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
                'image_url' => ($row->image !== 'imagen-no-disponible.jpg') ? tenant_asset('storage'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'items'.DIRECTORY_SEPARATOR.$row->image) : tenant_asset("/logo/{$row->image}"),
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
                'image_url_medium' => ($row->image_medium !== 'imagen-no-disponible.jpg') ? tenant_asset('storage'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'items'.DIRECTORY_SEPARATOR.$row->image_medium) : tenant_asset("/logo/{$row->image_medium}"),
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
                'image_url_small' => ($row->image_small !== 'imagen-no-disponible.jpg') ? tenant_asset('storage'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'items'.DIRECTORY_SEPARATOR.$row->image_small) : tenant_asset("/logo/{$row->image_small}"),
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
            ];
        });
    }
}