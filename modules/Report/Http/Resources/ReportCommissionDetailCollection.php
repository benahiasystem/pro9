<?php

namespace Modules\Report\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Modules\Report\Services\DetailedProfitReport;

class ReportCommissionDetailCollection extends ResourceCollection
{
    public function toArray($request)
    {
        return DetailedProfitReport::rows($this->collection);
    }
}
