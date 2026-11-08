<?php

namespace Modules\Report\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class CashCollection extends ResourceCollection
{
    public function toArray($request) { return $this->collection; }
}
