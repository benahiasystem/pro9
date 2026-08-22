<?php

namespace Modules\Item\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class ProductVariableCollection extends ResourceCollection
{
    /**
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request)
    {
        return $this->collection->transform(function ($row, $key) {

            return [
                'id' => $row->id,
                'name' => $row->name,
                'value_type' => $row->value_type,
                'active' => (bool) $row->active,
                'values' => $row->values->map(function ($value) {
                    return [
                        'id' => $value->id,
                        'value' => $value->value,
                        'color' => $value->color,
                        'position' => $value->position,
                        'active' => (bool) $value->active,
                    ];
                })->values(),
                'items_count' => $row->itemVariationValues()->distinct('item_id')->count('item_id'),
            ];
        });
    }
}
