<?php

namespace Modules\Item\Observers;

use App\Models\Tenant\Item;

class ItemObserver
{
    public function saving(Item $item)
    {
        $text = [];
        if(!is_null($item->name) && $item->name !== '') {
            $text[] = $item->name;
        }
        if(!is_null($item->second_name) && $item->second_name !== '') {
            $text[] = $item->second_name;
        }
        if(!is_null($item->description) && $item->description !== '') {
            $text[] = $item->description;
        }
        if(!is_null($item->model) && $item->model !== '') {
            $text[] = $item->model;
        }
        if(!is_null($item->barcode) && $item->barcode !== '') {
            $text[] = $item->barcode;
        }
        if(!is_null($item->internal_id) && $item->internal_id !== '') {
            $text[] = $item->internal_id;
        }
        if(!is_null($item->category_id)) {
            $text[] = $item->category->name;
        }
        if(!is_null($item->brand_id)) {
            $text[] = $item->brand->name;
        }
        // Las variaciones incluyen sus valores (Talla M, Rojo...) para ser buscables
        if(!is_null($item->parent_item_id) && $item->exists) {
            $variation_values = $item->variationValues()
                ->with('value')
                ->get()
                ->map(function ($row) {
                    return $row->value ? $row->value->value : null;
                })
                ->filter()
                ->all();

            if(count($variation_values) > 0) {
                $text = array_merge($text, $variation_values);
            }
        }

        $item->text_filter = join(' ', $text);
    }
}
