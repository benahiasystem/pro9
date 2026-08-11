<?php

namespace Modules\LevelAccess\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class SystemActivityTransactionCollection extends ResourceCollection
{
    public $collects = SystemActivityTransactionResource::class;

    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return $this->collection->map->toArray($request)->all();
    }
}
