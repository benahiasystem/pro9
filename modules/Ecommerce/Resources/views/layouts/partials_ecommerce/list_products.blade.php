@foreach ($dataPaginate as $item)
    @php
        $configuration = \App\Models\Tenant\Configuration::first();
        $defaultImage = $configuration->product_default_image ?? 'imagen-no-disponible.jpg';
        $defaultImagePath = $defaultImage === 'imagen-no-disponible.jpg'
            ? asset('logo/imagen-no-disponible.jpg')
            : asset('storage/defaults/' . $defaultImage);

        $imagePath = $item->image !== 'imagen-no-disponible.jpg'
            ? asset('storage/uploads/items/' . $item->image)
            : $defaultImagePath;

        $activeCampaign = null;
        $hasActiveOffer = false;
        $activeOfferPrice = (float) $item->sale_unit_price;
        $compareAtPrice = null;
        if (isset($campaigns) && count($campaigns) > 0) {
            $activeCampaign = $campaigns instanceof \Illuminate\Support\Collection
                ? $campaigns->first()
                : (is_array($campaigns) ? ($campaigns[0] ?? null) : $campaigns);
        }
        if ($activeCampaign && (
            method_exists($activeCampaign, 'hasActiveDiscount')
                ? $activeCampaign->hasActiveDiscount()
                : ($activeCampaign->sp_discount_price && (! $activeCampaign->end_date || $activeCampaign->end_date > now()))
        )) {
            $hasActiveOffer = true;
            // Precio de venta = catálogo; el %/monto arma el precio tachado "antes".
            $activeOfferPrice = method_exists($activeCampaign, 'discountedPrice')
                ? $activeCampaign->discountedPrice((float) $item->sale_unit_price)
                : (float) $item->sale_unit_price;
            $compareAtPrice = method_exists($activeCampaign, 'compareAtPrice')
                ? $activeCampaign->compareAtPrice((float) $item->sale_unit_price)
                : null;
            if (! $compareAtPrice) {
                $hasActiveOffer = false;
            }
        }
        $campaignPricing = app(\Modules\Ecommerce\Services\CampaignPriceService::class)->forItem($item);
        $activeOfferPrice = $campaignPricing['final_price'];
        $compareAtPrice = $campaignPricing['compare_at_price'];
        $hasActiveOffer = $campaignPricing['has_social_proof_price'] || $campaignPricing['has_real_discount'];
    @endphp
    <div class="col-6 mb-2 {{ \Route::currentRouteName() == 'tenant.ecommerce.index' ? 'col-md-3' : 'col-md-4' }}">
        <div class="product product-style h-100 m-0 d-flex flex-column {{ stock($item, $configuration) ? 'productdisabled' : '' }}">
            <figure class="product-image-container product-image-container-ecommerce h-auto">

                <a href="/ecommerce/item/{{ $item->id }}/{{ \Illuminate\Support\Str::slug($item->description) }}" class="product-image product-image-list">
                    <img src="{{ $imagePath }}" class="image" alt="{{ $item->description }}">
                </a>
                <a href="{{route('item_partial', ['id' => $item->id])}}" class="btn-quickview"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-zoom-scan"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M4 8v-2a2 2 0 0 1 2 -2h2" /><path d="M4 16v2a2 2 0 0 0 2 2h2" /><path d="M16 4h2a2 2 0 0 1 2 2v2" /><path d="M16 20h2a2 2 0 0 0 2 -2v-2" /><path d="M8 11a3 3 0 1 0 6 0a3 3 0 0 0 -6 0" /><path d="M16 16l-2.5 -2.5" /></svg> Vista Rápida</a>
                {{-- <span class="product-label label-sale">-20%</span> --}}
                @if(json_encode($item->is_new) == 1)
                    <span class="product-label label-hot">Nuevo</span>
                @endif
            </figure>
            <div class="product-details-ecommerce d-flex flex-column" style="flex: 1 1 auto">
                <div class="product-information">
                    <h2 class="product-title-ecommerce">
                        <a href="/ecommerce/item/{{ $item->id }}/{{ \Illuminate\Support\Str::slug($item->description) }}">{{ $item->description }}</a>
                    </h2>

                    @if($activeCampaign && $activeCampaign->sp_rating)
                        <div class="sp-list-rating mb-1" style="font-size:13px; line-height:1.2;">
                            <span style="color:#f5b301; letter-spacing:1px;">★★★★★</span>
                            <span class="text-muted" style="font-size:11px; margin-left:4px;">5.0</span>
                        </div>
                    @endif

                    @if(isset($preferences['show_description']) && $preferences['show_description'] == 1)
                        @if ($item->name)
                            <p class="text-muted product-description">
                                {{ strip_tags($item->name) }}
                            </p>
                        @else
                            <p class="text-muted product-description" style="opacity: .5">
                                Sin descripción disponible.
                            </p>
                        @endif
                    @endif

                    @if(isset($preferences['show_stock']) && $preferences['show_stock'] == 1)
                        @if($item->stock > 0)
                        <h3 class="product-stock">Disponible:
                            <span>{{ number_format($item->getStockByWarehouseMain(), 0) }}</span>
                        </h3>
                        @else
                        <h3 class="product-stock text-danger">
                            Sin stock
                        </h3>
                        @endif
                    @endif
                </div>
                <div class="product-price-ecommerce mt-auto">
                    @if($storefront_show_prices ?? true)
                    <div class="price-box-ecommerce">
                        @if($compareAtPrice)
                            <span class="old-price">{{ $item->currency_type['symbol'] }} {{ number_format($compareAtPrice, 2) }}</span>
                        @endif
                        <span class="product-price-ecommerce">{{ $item->currency_type['symbol'] }} {{ number_format($activeOfferPrice, 2) }}</span>
                    </div>
                    @endif
                    <div class="product-action">
                        @if(stock($item, $configuration))
                    <span class="product-danger">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-box-off"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M17.765 17.757l-5.765 3.243l-8 -4.5v-9l2.236 -1.258m2.57 -1.445l3.194 -1.797l8 4.5v8.5" /><path d="M14.561 10.559l5.439 -3.059" /><path d="M12 12v9" /><path d="M12 12l-8 -4.5" /><path d="M3 3l18 18" /></svg> Agotado
                    </span>
               @else
                        @php
                            $cartProductPayload = [
                                'id' => $item->id,
                                'description' => $item->description,
                                'sale_unit_price' => $hasActiveOffer ? $activeOfferPrice : (float) $item->sale_unit_price,
                                'original_price' => (float) $item->sale_unit_price,
                                'compare_at_price' => $compareAtPrice,
                                'discount_campaign_id' => $campaignPricing['discount_campaign_id'],
                                'discount_campaign_name' => $campaignPricing['discount_campaign_name'],
                                'campaign_discount_percent' => $campaignPricing['real_discount_percentage'],
                                'campaign_discount_embedded' => $campaignPricing['has_real_discount'],
                                'has_discount' => $hasActiveOffer,
                                'discount_percent' => $campaignPricing['real_discount_percentage'],
                                'image' => $item->image,
                                'image_small' => $item->image_small ?? $item->image,
                                'image_medium' => $item->image_medium ?? $item->image,
                                'currency_type_id' => $item->currency_type_id ?? 'PEN',
                                'currency_type_symbol' => $item->currency_type['symbol'] ?? 'S/',
                                'sale_affectation_igv_type_id' => $item->sale_affectation_igv_type_id ?? '10',
                                'unit_type_id' => $item->unit_type_id ?? 'NIU',
                                'internal_id' => $item->internal_id ?? '',
                                'stock' => (int) $item->getStockByWarehouseMain(),
                            ];
                        @endphp
                        <a href="javascript:void(0)" role="button" class="paction add-cart" data-product='@json($cartProductPayload)' title="Add to Cart">
                            <svg clip-rule="evenodd" fill-rule="evenodd" width="22" height="22" stroke-linejoin="round" stroke-miterlimit="2" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg" id="fi_4893746"><path d="m211.892 383.468c24.344 0 44.108 19.764 44.108 44.108s-19.764 44.108-44.108 44.108-44.108-19.764-44.108-44.108 19.764-44.108 44.108-44.108zm176.22 0c24.344 0 44.108 19.764 44.108 44.108s-19.764 44.108-44.108 44.108-44.108-19.764-44.108-44.108 19.764-44.108 44.108-44.108zm-288.464-273.226s63.534 222.705 63.534 222.705c6.591 23.103 27.703 39.034 51.727 39.034h157.478c33.502 0 61.98-24.47 67.023-57.59 4.821-31.664 11.838-77.75 17.065-112.081 2.869-18.84-2.626-37.994-15.046-52.449-12.42-14.454-30.529-22.769-49.586-22.769h-235.394l-8.72-30.567c-7.633-26.757-32.085-45.209-59.91-45.209-23.033 0-51.825 0-51.825 0-13.798 0-25 11.202-25 25s11.202 25 25 25h51.825c5.494 0 10.321 3.643 11.829 8.926zm71.066 66.85h221.129c4.482 0 8.741 1.956 11.663 5.355 2.921 3.4 4.213 7.905 3.539 12.337 0 0-17.066 112.081-17.066 112.081-1.323 8.693-8.798 15.116-17.592 15.116h-157.478c-1.693 0-3.181-1.122-3.645-2.751 0 0-40.55-142.138-40.55-142.138z"></path></svg>
                            <span>Agregar a Carrito</span>
                        </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

@endforeach

<?php

    function stock($item, $config)
    {
        if($config) {
            $stock=0;
            foreach ($item->warehouses as $key => $value) {
                $stock += $value->stock;
            }
            return ($stock > 0) ? false : true;
        }
    }
?>

<style>
    /* .product-style {
        border-style: solid;
        border-width: 1px;
        border-color: "#ddd";
        margin: 10px 1px;
    } */
    .product-image-list {
        max-height: 210px;
        min-height: 210px;
    }
    .image {
        max-height: 210px;
    }
    .productdisabled
    {
        /* pointer-events: none; */
        /* opacity: 0.7; */
    }
    .product-title-ecommerce{
        min-height: 48px; /* 2 líneas */
        line-height: 24px;
        overflow: hidden;
    }

    .price-box-ecommerce{
        white-space: nowrap; /* evita que S/ y el monto se separen */
    }

    .product-price-ecommerce{
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        flex-wrap: nowrap;
    }

    @media (max-width: 767.98px) {
        .product.product-style > .product-image-container-ecommerce {
            width: 100% !important;
            max-width: 100% !important;
            margin-left: 0;
            margin-right: 0;
            border-radius: 32px 32px 0 0;
            overflow: hidden;
        }

        .product.product-style > .product-image-container-ecommerce .product-image-list {
            display: flex;
            width: 100%;
            max-width: 100%;
            border-radius: inherit;
            overflow: hidden;
        }

        .product.product-style > .product-image-container-ecommerce .image {
            display: block;
            width: 100%;
            max-width: none;
            height: 210px;
            object-fit: cover;
            object-position: center;
        }
    }
</style>
