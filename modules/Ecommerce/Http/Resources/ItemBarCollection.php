<?php

namespace Modules\Ecommerce\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Models\Tenant\ConfigurationEcommerce;
use App\Support\Exact\Decimal as D;
use Modules\Ecommerce\Services\CampaignPriceService;
use Illuminate\Support\Str;

class ItemBarCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function toArray($request)
    {

        // ######## INICIO CALCULOS EXACTOS SAB ########
        $showPrices = ConfigurationEcommerce::storefrontShowsPrices();
        $pricingService = app(CampaignPriceService::class);
        return $this->collection->transform(function($row, $key) use($showPrices, $pricingService){
            // ########## INICIO CAMBIO AFECTACIÓN IVA
            $has_igv_description = $row->sale_affectation_igv_type_id === '20' ? 'No'
                : ($row->sale_affectation_igv_type_id === '10' ? ((bool)$row->has_igv ? 'Si' : 'No') : null);
            // ######### FIN CAMBIO AFECTACIÓN IVA
            $pricing = $showPrices ? $pricingService->forItem($row) : null;
            $sale_unit_price = $pricing['final_price'] ?? null;

            return [
                'id' => $row->id,
                'slug' => $row->id . '/' . Str::slug($row->description),
                'unit_type_id' => $row->unit_type_id,
                'description' => $row->description,
                'brand' => $row->brand && $row->brand->id ? [
                    'id' => $row->brand->id,
                    'name' => $row->brand->name,
                    'slug' => Str::slug($row->brand->name),
                ] : null,
                'name' => $row->name,
                'second_name' => $row->second_name,
                'warehouse_id' => $row->warehouse_id,
                'internal_id' => $row->internal_id,
                'item_code' => $row->item_code,
                'item_code_gs1' => $row->item_code_gs1,
                'stock' => D::represent($row->getStockByWarehouseMain()),
                'stock_min' => $row->stock_min,
                'currency_type_id' => $row->currency_type_id,
                'currency_type_symbol' => $row->currency_type->symbol,
                'sale_affectation_igv_type_id' => $row->sale_affectation_igv_type_id,
                'amount_sale_unit_price' => $sale_unit_price,
                'original_price' => $pricing['base_price'] ?? null,
                'compare_at_price' => $pricing['compare_at_price'] ?? null,
                'discount_campaign_id' => $pricing['discount_campaign_id'] ?? null,
                'discount_campaign_name' => $pricing['discount_campaign_name'] ?? null,
                'campaign_discount_percent' => $pricing['real_discount_percentage'] ?? null,
                'campaign_discount_embedded' => $pricing['has_real_discount'] ?? false,
                'campaign_pricing_snapshot' => $pricing['calculation_snapshot'] ?? null,
                'calculate_quantity' => (bool) $row->calculate_quantity,
                'has_igv' => (bool) $row->has_igv,
                'has_igv_description' => $has_igv_description,
                // ######## INICIO MIGRACIÓN MONEDA VENEZUELA ########
                'sale_unit_price' => $showPrices ? sprintf(
                    '%s %s',
                    $row->currency_type->symbol ?? \App\Support\Venezuela\Localization::currencySymbol($row->currency_type_id),
                    D::format(D::readRequired($pricing,'final_price'))
                ) : null,
                // ######## FIN MIGRACIÓN MONEDA VENEZUELA ########
                // 'sale_unit_price' => "{$row->currency_type->symbol} {$row->sale_unit_price}",
                'created_at' => ($row->created_at) ? $row->created_at->format('Y-m-d H:i:s') : '',
                'updated_at' => ($row->created_at) ? $row->updated_at->format('Y-m-d H:i:s') : '',
                'warehouses' => collect($row->warehouses)->transform(function($row) {
                    return [
                        'warehouse_description' => $row->warehouse->description,
                        'stock' => $row->stock,
                    ];
                }),
                'apply_store' => (bool)$row->apply_store,
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
                'image' => $row->image,
                'image_small' => $row->image_small,
                'image_url' => ($row->image !== 'imagen-no-disponible.jpg') ? tenant_asset('storage'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'items'.DIRECTORY_SEPARATOR.$row->image) : tenant_asset("/logo/{$row->image}"),
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
                'image_url_medium' => ($row->image_medium !== 'imagen-no-disponible.jpg') ? tenant_asset('storage'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'items'.DIRECTORY_SEPARATOR.$row->image_medium) : tenant_asset("/logo/{$row->image_medium}"),
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
                'image_url_small' => ($row->image_small !== 'imagen-no-disponible.jpg') ? tenant_asset('storage'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'items'.DIRECTORY_SEPARATOR.$row->image_small) : tenant_asset("/logo/{$row->image_small}"),
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
                'tags' => $row->tags,
                'tags_id' => $row->tags->pluck('tag_id'),



            ];
        });
        // ######## FIN CALCULOS EXACTOS SAB ########
    }


}
