<?php

namespace Modules\Report\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class StockRunningOutCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function toArray($request)
    {
        return $this->collection->transform(function ($row, $key) {
            return [
                'id' => $row->id,
                'internal_id' => optional($row->item)->internal_id,
                'product' => optional($row->item)->description,
                'stock' => number_format($row->stock, 2, ".", ""),
                'warehouse' => optional($row->warehouse)->description,
                'establishment' => optional(optional($row->warehouse)->establishment)->description ?? '',
                'state' => ($row->stock <= 0) ? 'Agotado' : 'Pocas unidades',
                'state_code' => ($row->stock <= 0) ? '01' : '02',
            ];
        });
    }
}