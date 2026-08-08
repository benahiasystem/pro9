<?php

namespace Modules\Item\Http\Controllers;

use App\Models\Tenant\Item;
use Exception;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Modules\Item\Http\Requests\ItemVariationBulkRequest;
use Modules\Item\Services\ItemVariationService;

class ItemVariationController extends Controller
{
    /**
     * Variaciones registradas de un producto principal.
     */
    public function records($item_id)
    {
        $parent = Item::findOrFail($item_id);

        $variations = $parent->variations()
            ->with(['variationValues.value', 'variationValues.variable'])
            ->orderBy('id')
            ->get();

        return [
            'data' => $variations->map(function (Item $row) {
                return [
                    'id' => $row->id,
                    'description' => $row->description,
                    'internal_id' => $row->internal_id,
                    'barcode' => $row->barcode,
                    'stock' => $row->stock,
                    'sale_unit_price' => $row->sale_unit_price,
                    'active' => (bool) $row->active,
                    'variation_label' => $row->variation_label,
                    'variable_value_ids' => $row->variationValues->pluck('product_variable_value_id'),
                ];
            }),
        ];
    }

    /**
     * Crea en lote las variaciones de un producto principal.
     */
    public function bulk(ItemVariationBulkRequest $request, $item_id)
    {
        $parent = Item::findOrFail($item_id);

        try {
            $created_ids = (new ItemVariationService())->bulkCreate($parent, $request->input('variations'));

            return [
                'success' => true,
                'message' => count($created_ids) . ' variaciones creadas con éxito',
                'ids' => $created_ids,
            ];
        } catch (Exception $e) {
            Log::error('Error al crear variaciones del item ' . $parent->id . ': ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'No se pudieron crear las variaciones: ' . $e->getMessage(),
            ], 500);
        }
    }
}
