<?php

namespace Modules\Item\Http\Controllers;

use App\Models\Tenant\Item;
use Exception;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Modules\Item\Http\Requests\ItemVariationBulkRequest;
use Modules\Item\Http\Requests\ItemVariationImageRequest;
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
            'data' => $variations->map(function (Item $row) use ($parent) {
                return [
                    'id' => $row->id,
                    'description' => $row->description,
                    'internal_id' => $row->internal_id,
                    'barcode' => $row->barcode,
                    'stock' => $row->stock,
                    'sale_unit_price' => $row->sale_unit_price,
                    'active' => (bool) $row->active,
                    'variation_label' => $row->variation_label,
                    'variation_attributes' => $row->getVariationAttributesData(),
                    'variable_value_ids' => $row->variationValues->pluck('product_variable_value_id'),
                    'image_url' => $row->image ? $row->getImageUrl() : null,
                    'has_own_image' => (bool) ($row->image && $row->image !== $parent->image),
                ];
            }),
        ];
    }

    /**
     * Reemplaza la imagen de una variación ya registrada.
     */
    public function updateImage(ItemVariationImageRequest $request, $item_id, $variation_id)
    {
        $parent = Item::findOrFail($item_id);
        $variation = $parent->variations()->findOrFail($variation_id);

        try {
            $updated = (new ItemVariationService())->updateImage($variation, $request->only(['image', 'temp_path']));

            if (!$updated) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo procesar la imagen enviada',
                ], 422);
            }

            return [
                'success' => true,
                'message' => 'Imagen actualizada con éxito',
                'data' => [
                    'id' => $variation->id,
                    'image_url' => $variation->getImageUrl(),
                    'has_own_image' => (bool) ($variation->image && $variation->image !== $parent->image),
                ],
            ];
        } catch (Exception $e) {
            Log::error('Error al actualizar la imagen de la variación ' . $variation->id . ': ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'No se pudo actualizar la imagen: ' . $e->getMessage(),
            ], 422);
        }
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
