<?php

namespace Modules\Report\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Modules\BusinessTurn\Models\DocumentHotel;
use Modules\Hotel\Models\HotelRent;

class RentHotelCollection extends ResourceCollection
{


    public function toArray($request) {


        return $this->collection->transform(function(HotelRent $row, $key){
            $data = $row->toArray();
            $customer = $row->customer;
            $room = $row->room;
            $items = $row->products->map(function ($product) {
                $data = $product->toArray();
                if ($raw = $product->getRawOriginal('item')) {
                    $item = \App\Support\Exact\JsonDecimals::decode($raw);
                    if (isset($item['total']) || isset($item['calculation_snapshot']['values']['total'])) {
                        $item['total'] = \App\Support\Exact\Decimal::represent(\App\Support\Exact\Decimal::readRequired($item, 'total'));
                    }
                    $data['item'] = $item;
                }
                return $data;
            });
            $data['status'] = ucfirst(strtolower($data['status']));
            $data['customer'] = $customer;
            $data['room'] = $room;
            $data['items'] = $items;
            unset($data['products'], $data['person']);
            return  $data;

        });
    }
}
