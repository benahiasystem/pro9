<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\Configuration;
use Illuminate\Http\Resources\Json\ResourceCollection;
use phpDocumentor\Reflection\Types\True_;

/**
 * Class DispatchItemCollection
 *
 * @package App\Http\Resources\Tenant
 * @mixin ResourceCollection
 */
class DispatchItemCollection extends ResourceCollection
{
    private ?array $resolved = null;

    public function withResolved(array $resolved): self
    {
        $this->resolved = $resolved;
        return $this;
    }
	/**
	 * Transform the resource collection into an array.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return mixed
	 */
	public function toArray($request)
	{
        if ($this->resolved !== null) return $this->collection->map(fn($row)=>$this->resolved[$row->id]);
        $configuration =  Configuration::first();
		return $this->collection->transform(function ($row, $key) use($configuration) {
		    /** @var \App\Models\Tenant\DispatchItem $row */
            return $row->getCollectionData($configuration);

		});
	}
}
