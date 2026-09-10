<?php
// ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\Dispatch;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Class DispatchCollection
 *
 * @package App\Http\Resources\Tenant
 * @mixin ResourceCollection
 */
class DispatchCollection extends ResourceCollection
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
            /** @var Dispatch $row */
            return $row->getCollectionData();
		});
	}
}
// ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
