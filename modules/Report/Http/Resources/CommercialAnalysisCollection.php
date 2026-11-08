<?php

namespace Modules\Report\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Modules\Item\Models\Category;
use Modules\Report\Services\CommercialAnalysisAmounts;

class CommercialAnalysisCollection extends ResourceCollection
{
    public function toArray($request)
    {
        $categories=Category::all()->pluck('name');
        (new \Illuminate\Database\Eloquent\Collection($this->collection->all()))
            ->loadMissing(['documents.items.relation_item.category','documents.note','country','department','province','district','identity_document_type']);
        return $this->collection->map(fn ($customer) => CommercialAnalysisAmounts::row($customer,$categories));
    }
}
