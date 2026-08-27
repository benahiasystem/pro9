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

        // Producto con variantes: el padre no es vendible, el stock y el precio
        // salen de sus variaciones y la compra pasa por el modal de opciones.
        $variationSelector = ($variationSelectors ?? [])[$item->id] ?? null;
        $variationCombinations = $variationSelector ? collect($variationSelector['combinations']) : null;
        $variationStock = $variationCombinations ? (float) $variationCombinations->sum('stock') : 0;
        $variationMinPrice = $variationCombinations && $variationCombinations->count()
            ? (float) $variationCombinations->min('price')
            : null;
        $isOutOfStock = $variationSelector
            ? ($configuration ? $variationStock <= 0 : false)
            : stock($item, $configuration);
    @endphp
    <div class="col-6 mb-2 {{ \Route::currentRouteName() == 'tenant.ecommerce.index' ? 'col-md-3' : 'col-md-4' }}">
        <div class="product product-style h-100 m-0 d-flex flex-column {{ $isOutOfStock ? 'productdisabled' : '' }}"
             @if($variationSelector) data-variation-selector='@json($variationSelector)' @endif>
            <figure class="product-image-container product-image-container-ecommerce h-auto">

                @if($variationSelector)
                <a href="javascript:void(0)" role="button" class="product-image product-image-list open-variations" title="Elegir opciones">
                    <img src="{{ $imagePath }}" class="image" alt="{{ $item->description }}">
                </a>
                @else
                <a href="/ecommerce/item/{{ $item->id }}/{{ \Illuminate\Support\Str::slug($item->description) }}" class="product-image product-image-list">
                    <img src="{{ $imagePath }}" class="image" alt="{{ $item->description }}">
                </a>
                @endif
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
                        @php
                            $displayStock = $variationSelector ? $variationStock : $item->getStockByWarehouseMain();
                        @endphp
                        @if($displayStock > 0)
                        <h3 class="product-stock">Disponible:
                            <span>{{ number_format($displayStock, 0) }}</span>
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
                        @if(!is_null($variationMinPrice))
                            <span class="variation-price-from">Desde</span>
                            <span class="product-price-ecommerce">{{ $item->currency_type['symbol'] }} {{ number_format($variationMinPrice, 2) }}</span>
                        @else
                            @if($compareAtPrice)
                                <span class="old-price">{{ $item->currency_type['symbol'] }} {{ number_format($compareAtPrice, 2) }}</span>
                            @endif
                            <span class="product-price-ecommerce">{{ $item->currency_type['symbol'] }} {{ number_format($activeOfferPrice, 2) }}</span>
                        @endif
                    </div>
                    @endif
                    <div class="product-action">
                        @if($isOutOfStock)
                    <span class="product-danger">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-box-off"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M17.765 17.757l-5.765 3.243l-8 -4.5v-9l2.236 -1.258m2.57 -1.445l3.194 -1.797l8 4.5v8.5" /><path d="M14.561 10.559l5.439 -3.059" /><path d="M12 12v9" /><path d="M12 12l-8 -4.5" /><path d="M3 3l18 18" /></svg> Agotado
                    </span>
               @elseif($variationSelector)
                        <a href="javascript:void(0)" role="button"
                           class="paction add-cart open-variations"
                           title="Elegir opciones">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 6h16" /><path d="M4 12h16" /><path d="M4 18h16" /><path d="M8 4v4" /><path d="M14 10v4" /><path d="M9 16v4" /></svg>
                            <span>Elegir opciones</span>
                        </a>
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
            return ($stock > 0) || optional($item)->variations_stock ? false : true;
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

@once
<div class="vmodal" id="variations-modal" hidden>
    <div class="vmodal__backdrop" data-variations-close></div>
    <div class="vmodal__dialog" role="dialog" aria-modal="true" aria-labelledby="variations-modal-toptitle">
        <div class="vmodal__topbar">
            <h4 class="vmodal__toptitle" id="variations-modal-toptitle">Variaciones · <span id="variations-modal-product"></span></h4>
            <div class="vmodal__tabs" role="tablist">
                <button type="button" class="vmodal__tab is-active" data-vtab="attrs" role="tab">Atributos</button>
                <button type="button" class="vmodal__tab" data-vtab="list" role="tab">Lista</button>
            </div>
            <button type="button" class="vmodal__close" data-variations-close aria-label="Cerrar">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>

        <div class="vmodal__content">
            <div class="vmodal__media">
                <img id="variations-modal-image" src="" alt="">
            </div>

            <div class="vmodal__panel">
                <div id="variations-modal-attrs">
                    <h3 class="vmodal__variant" id="variations-modal-variant"></h3>
                    <div class="vmodal__price" id="variations-modal-price"></div>
                    <div id="variations-modal-groups"></div>
                    <hr class="vmodal__divider">
                    <div class="vmodal__stock" id="variations-modal-stock"></div>
                    <div class="vmodal__codes" id="variations-modal-codes"></div>
                </div>

                <div id="variations-modal-list" class="vmodal__list" hidden></div>
            </div>
        </div>

        <div class="vmodal__footer">
            <button type="button" class="vmodal__btn vmodal__btn--ghost" data-variations-close>Cerrar</button>
            <button type="button" class="vmodal__btn vmodal__btn--primary" id="variations-modal-submit">Agregar</button>
        </div>
    </div>
</div>

<style>
    .variation-price-from { font-size: 12px; color: #888; margin-right: 4px; }

    .vmodal {
        --vm-navy: #1b2653;
        --vm-ink: #1f2430;
        --vm-muted: #9aa1ad;
        --vm-line: #e6e8ec;
        position: fixed; inset: 0; z-index: 1090;
        display: flex; align-items: center; justify-content: center; padding: 24px;
        font-size: 14px; line-height: 1.5; color: var(--vm-ink);
    }
    .vmodal[hidden] { display: none; }
    .vmodal__backdrop { position: absolute; inset: 0; background: rgba(17, 20, 28, .5); animation: vm-fade .18s ease-out; }
    .vmodal__dialog {
        position: relative; background: #fff; border-radius: 14px; width: 100%; max-width: 1040px;
        max-height: 90vh; display: flex; flex-direction: column; overflow: hidden;
        box-shadow: 0 24px 70px rgba(0, 0, 0, .3); animation: vm-pop .22s cubic-bezier(.2, .9, .3, 1);
    }
    @keyframes vm-fade { from { opacity: 0 } to { opacity: 1 } }
    @keyframes vm-pop { from { opacity: 0; transform: translateY(12px) scale(.98) } to { opacity: 1; transform: none } }

    .vmodal__topbar { display: flex; align-items: center; gap: 16px; padding: 18px 20px 18px 28px; }
    .vmodal__toptitle {
        margin: 0; flex: 1 1 auto; min-width: 0; font-size: 19px; font-weight: 600; color: var(--vm-ink);
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .vmodal__tabs { display: flex; flex: 0 0 auto; border: 1px solid var(--vm-line); border-radius: 8px; overflow: hidden; }
    .vmodal__tab {
        border: 0; background: #fff; color: var(--vm-muted); font-size: 14px; font-weight: 500;
        padding: 9px 22px; cursor: pointer; transition: .16s;
    }
    .vmodal__tab + .vmodal__tab { border-left: 1px solid var(--vm-line); }
    .vmodal__tab.is-active { background: var(--vm-navy); color: #fff; font-weight: 600; }
    .vmodal__close {
        flex: 0 0 auto; width: 32px; height: 32px; border: 0; background: transparent; color: #b3b8c2;
        display: flex; align-items: center; justify-content: center; cursor: pointer; border-radius: 6px; transition: .16s;
    }
    .vmodal__close:hover { background: #f3f4f6; color: var(--vm-ink); }

    .vmodal__content { display: flex; gap: 32px; padding: 0 28px 8px; overflow-y: auto; flex: 1 1 auto; }
    .vmodal__media { flex: 0 0 42%; max-width: 42%; }
    .vmodal__media img {
        width: 100%; aspect-ratio: 1 / 1; object-fit: cover; border-radius: 10px;
        background: #f4f5f7; border: 1px solid var(--vm-line); display: block;
    }
    .vmodal__panel { flex: 1 1 auto; min-width: 0; padding-top: 4px; }

    .vmodal__variant { margin: 0 0 10px; font-size: 20px; font-weight: 700; line-height: 1.3; color: var(--vm-ink); }
    .vmodal__price { display: flex; align-items: baseline; flex-wrap: wrap; gap: 10px; margin-bottom: 26px; }
    .vmodal__price .now { font-size: 27px; font-weight: 800; letter-spacing: -.01em; }
    .vmodal__price .was { font-size: 15px; color: var(--vm-muted); text-decoration: line-through; }
    .vmodal__price .off { font-size: 12px; font-weight: 700; color: #fff; background: #dc2626; border-radius: 4px; padding: 3px 7px; }

    .vmodal__group { margin-bottom: 22px; }
    .vmodal__grouplabel {
        display: block; font-size: 12px; font-weight: 600; letter-spacing: .08em;
        text-transform: uppercase; color: var(--vm-muted); margin-bottom: 12px;
    }
    .vmodal__options { display: flex; flex-wrap: wrap; gap: 12px; }

    .vmodal__chip {
        position: relative; display: inline-flex; align-items: center; justify-content: center; gap: 9px;
        min-width: 58px; height: 46px; padding: 0 22px; border: 1px solid var(--vm-line); border-radius: 999px;
        background: #fff; font-size: 14.5px; font-weight: 500; color: var(--vm-ink); cursor: pointer; transition: .16s;
    }
    .vmodal__chip:hover:not(:disabled) { border-color: #c3c8d1; }
    .vmodal__chip.is-active { border-color: var(--vm-ink); border-width: 2px; font-weight: 600; }
    .vmodal__chip .dot { width: 15px; height: 15px; border-radius: 50%; flex: 0 0 15px; box-shadow: inset 0 0 0 1px rgba(0, 0, 0, .16); }

    /* combinacion inexistente o sin stock: se marca tachada */
    .vmodal__chip:disabled { cursor: not-allowed; color: #c6cad2; border-color: #f0f1f4; }
    .vmodal__chip:disabled .dot { opacity: .4; }
    .vmodal__chip:disabled::after {
        content: ''; position: absolute; inset: 0; border-radius: inherit;
        background: linear-gradient(to top left, transparent calc(50% - .8px), #dcdfe4 calc(50% - .8px), #dcdfe4 calc(50% + .8px), transparent calc(50% + .8px));
    }

    .vmodal__divider { border: 0; border-top: 1px solid var(--vm-line); margin: 26px 0 18px; }
    .vmodal__stock { display: flex; align-items: center; gap: 12px; font-size: 15px; font-weight: 600; }
    .vmodal__badge { font-size: 12.5px; font-weight: 600; border-radius: 999px; padding: 4px 12px; }
    .vmodal__badge--ok { background: #eaf6ea; color: #3d8b40; border: 1px solid #cfe8d0; }
    .vmodal__badge--low { background: #fff4e5; color: #b26a00; border: 1px solid #ffe0b2; }
    .vmodal__badge--out { background: #fdecec; color: #c62828; border: 1px solid #f7cdcd; }
    .vmodal__codes { margin-top: 10px; font-size: 13px; color: var(--vm-muted); }

    .vmodal__list { display: flex; flex-direction: column; gap: 8px; padding-top: 4px; }
    .vmodal__row {
        display: flex; align-items: center; gap: 12px; width: 100%;
        border: 1px solid var(--vm-line); border-radius: 10px; background: #fff; padding: 10px 14px; transition: .16s;
    }
    .vmodal__row:hover { border-color: #c3c8d1; background: #fafbfc; }
    .vmodal__row.is-active { border-color: var(--vm-ink); border-width: 2px; padding: 9px 13px; }
    .vmodal__row.is-out { opacity: .6; }
    .vmodal__rowmain {
        display: flex; align-items: center; gap: 14px; flex: 1 1 auto; min-width: 0;
        border: 0; background: transparent; padding: 0; text-align: left; cursor: pointer; color: inherit; font: inherit;
    }
    .vmodal__rowmain:disabled { cursor: default; }
    .vmodal__row img { width: 46px; height: 46px; flex: 0 0 46px; border-radius: 8px; object-fit: cover; background: #f4f5f7; }
    .vmodal__rowinfo { flex: 1 1 auto; min-width: 0; }
    .vmodal__rowlabel { display: block; font-weight: 600; font-size: 14.5px; }
    .vmodal__rowcode { display: block; font-size: 12.5px; color: var(--vm-muted); }
    .vmodal__rowprice { flex: 0 0 auto; text-align: right; }
    .vmodal__rowprice .now { display: block; font-weight: 700; font-size: 15px; }
    .vmodal__rowprice .st { display: block; font-size: 12px; color: var(--vm-muted); }
    .vmodal__rowadd {
        flex: 0 0 auto; height: 38px; padding: 0 18px; border-radius: 8px; cursor: pointer; transition: .16s;
        background: var(--vm-navy); border: 1px solid var(--vm-navy); color: #fff; font-size: 13.5px; font-weight: 600;
    }
    .vmodal__rowadd:hover:not(:disabled) { filter: brightness(1.18); }
    .vmodal__rowadd:disabled { background: #eef0f3; border-color: #eef0f3; color: #a6abb5; cursor: not-allowed; }
    .vmodal__rowadd.is-done { background: #eaf6ea; border-color: #cfe8d0; color: #3d8b40; }

    .vmodal__footer { display: flex; justify-content: flex-end; gap: 12px; padding: 18px 28px 24px; }
    .vmodal__btn {
        min-width: 118px; height: 46px; padding: 0 26px; border-radius: 8px; font-size: 15px; font-weight: 600;
        cursor: pointer; transition: .16s; border: 1px solid var(--vm-line); background: #fff; color: var(--vm-ink);
    }
    .vmodal__btn--ghost:hover { border-color: #c3c8d1; background: #f7f8fa; }
    .vmodal__btn--primary { background: var(--vm-navy); border-color: var(--vm-navy); color: #fff; }
    .vmodal__btn--primary:hover:not(:disabled) { filter: brightness(1.18); }
    .vmodal__btn--primary:disabled { background: #e5e7eb; border-color: #e5e7eb; color: #a6abb5; cursor: not-allowed; }

    @media (max-width: 767.98px) {
        .vmodal { padding: 0; align-items: flex-end; }
        .vmodal__dialog { max-width: 100%; max-height: 94vh; border-radius: 16px 16px 0 0; animation: vm-sheet .26s cubic-bezier(.2, .9, .3, 1); }
        @keyframes vm-sheet { from { transform: translateY(100%) } to { transform: none } }
        .vmodal__topbar { padding: 16px; flex-wrap: wrap; }
        .vmodal__toptitle { order: 1; flex: 1 1 100%; font-size: 16px; white-space: normal; }
        .vmodal__tabs { order: 2; flex: 1 1 auto; }
        .vmodal__tab { flex: 1 1 50%; }
        .vmodal__close { order: 0; position: absolute; top: 12px; right: 12px; }
        .vmodal__content { flex-direction: column; gap: 18px; padding: 0 16px 8px; }
        .vmodal__media { flex: 0 0 auto; max-width: 100%; }
        .vmodal__media img { aspect-ratio: 4 / 3; }
        .vmodal__variant { font-size: 17px; }
        .vmodal__price .now { font-size: 23px; }
        .vmodal__footer { padding: 14px 16px 20px; position: sticky; bottom: 0; background: #fff; box-shadow: 0 -6px 18px rgba(0, 0, 0, .06); }
        .vmodal__btn { flex: 1 1 50%; min-width: 0; }
        .vmodal__row { flex-wrap: wrap; }
        .vmodal__rowmain { flex: 1 1 100%; }
        .vmodal__rowadd { flex: 1 1 100%; margin-top: 10px; }
    }
</style>

<script>
// Modal de variantes del listado. Misma logica de disponibilidad que el selector
// de la ficha (record.blade.php), en JS plano porque el listado no monta Vue.
(function () {
    var modal = document.getElementById('variations-modal');
    if (!modal) return;

    var productEl = document.getElementById('variations-modal-product');
    var imageEl = document.getElementById('variations-modal-image');
    var variantEl = document.getElementById('variations-modal-variant');
    var priceEl = document.getElementById('variations-modal-price');
    var groupsEl = document.getElementById('variations-modal-groups');
    var stockEl = document.getElementById('variations-modal-stock');
    var codesEl = document.getElementById('variations-modal-codes');
    var attrsView = document.getElementById('variations-modal-attrs');
    var listView = document.getElementById('variations-modal-list');
    var submitEl = document.getElementById('variations-modal-submit');
    var tabs = modal.querySelectorAll('.vmodal__tab');

    var selector = null;
    var selected = {};
    var lastFocused = null;

    function money(symbol, value) {
        return (symbol || 'S/') + ' ' + Number(value).toFixed(2);
    }

    function inStock(combination) {
        return Number(combination.stock) > 0;
    }

    // No todas las variantes usan todas las variables: puede existir una "Talla L"
    // suelta, sin color. Una variable que la variante no usa no la restringe.
    function combinationUses(combination, variable) {
        return variable.values.some(function (value) {
            return combination.value_ids.indexOf(value.id) !== -1;
        });
    }

    // Disponibilidad en cascada: cada variable se evalua solo contra lo elegido en
    // las variables anteriores. Asi la primera (ej: Color) nunca se bloquea y no hay
    // callejones sin salida; elegir un color re-filtra las tallas, no al reves.
    function valueAvailable(variableIndex, valueId) {
        var prior = selector.variables.slice(0, variableIndex);

        return selector.combinations.some(function (combination) {
            if (combination.value_ids.indexOf(valueId) === -1) return false;
            if (!inStock(combination)) return false;
            return prior.every(function (variable) {
                var chosen = selected[variable.id];
                if (chosen === undefined) return true;
                if (!combinationUses(combination, variable)) return true;
                return combination.value_ids.indexOf(chosen) !== -1;
            });
        });
    }

    // Variante que mejor encaja al pulsar un valor: la que conserva mas de lo ya
    // elegido. Elegir "Talla L" (sin color) aterriza en esa variante y suelta el color.
    function bestCombinationFor(variableId, valueId) {
        var best = null;
        var bestScore = -1;

        selector.combinations.forEach(function (combination) {
            if (combination.value_ids.indexOf(valueId) === -1) return;
            if (!inStock(combination)) return;

            var score = 0;
            selector.variables.forEach(function (variable) {
                if (String(variable.id) === String(variableId)) return;
                var chosen = selected[variable.id];
                if (chosen === undefined) return;
                if (combination.value_ids.indexOf(chosen) !== -1) score++;
            });

            if (score > bestScore) {
                bestScore = score;
                best = combination;
            }
        });

        return best;
    }

    // Coincide la variante cuyos valores son exactamente los elegidos, sin exigir
    // un valor por variable: una variante de una sola variable matchea con uno solo.
    function matchedCombination() {
        var ids = Object.keys(selected).map(function (key) { return Number(selected[key]); }).sort(function (a, b) { return a - b; });
        if (!ids.length) return null;

        return selector.combinations.find(function (combination) {
            return combination.value_ids.length === ids.length &&
                combination.value_ids.every(function (id, index) { return id === ids[index]; });
        }) || null;
    }

    function selectCombination(combination) {
        selected = {};
        selector.variables.forEach(function (variable) {
            var match = variable.values.find(function (value) {
                return combination.value_ids.indexOf(value.id) !== -1;
            });
            if (match) selected[variable.id] = match.id;
        });
    }

    function selectedValueName(variable) {
        var valueId = selected[variable.id];
        if (valueId === undefined) return null;
        var value = variable.values.find(function (candidate) { return candidate.id === valueId; });
        return value ? value.value : null;
    }

    function currentLabel() {
        return selector.variables
            .map(selectedValueName)
            .filter(function (name) { return !!name; })
            .join(' / ');
    }

    function addToCart(combination) {
        if (!combination || !inStock(combination)) return false;

        if (typeof cartAddOrUpdateItem === 'function') {
            cartAddOrUpdateItem(combination.cart, { quantity: 1 });
        } else if (typeof cart_add === 'function') {
            cart_add(combination.cart);
        } else {
            return false;
        }
        return true;
    }

    function stockBadge(stock) {
        if (stock <= 0) return '<span class="vmodal__badge vmodal__badge--out">Sin stock</span>';
        if (stock <= 5) return '<span class="vmodal__badge vmodal__badge--low">Últimas unidades</span>';
        return '<span class="vmodal__badge vmodal__badge--ok">En stock</span>';
    }

    function renderGroups() {
        groupsEl.innerHTML = '';

        selector.variables.forEach(function (variable, position) {
            var group = document.createElement('div');
            group.className = 'vmodal__group';

            var label = document.createElement('span');
            label.className = 'vmodal__grouplabel';
            label.textContent = variable.name;
            group.appendChild(label);

            var options = document.createElement('div');
            options.className = 'vmodal__options';

            variable.values.forEach(function (value) {
                var chip = document.createElement('button');
                chip.type = 'button';
                chip.className = 'vmodal__chip';
                chip.title = value.value;
                if (selected[variable.id] === value.id) chip.classList.add('is-active');
                chip.disabled = !valueAvailable(position, value.id);

                if (variable.value_type === 'color' && value.color) {
                    var dot = document.createElement('span');
                    dot.className = 'dot';
                    dot.style.background = value.color;
                    chip.appendChild(dot);
                }
                chip.appendChild(document.createTextNode(value.value));

                chip.addEventListener('click', function () {
                    // volver a pulsar el chip activo lo deselecciona
                    if (selected[variable.id] === value.id) {
                        delete selected[variable.id];
                    } else {
                        var best = bestCombinationFor(variable.id, value.id);
                        if (best) {
                            selectCombination(best);
                        } else {
                            selected[variable.id] = value.id;
                        }
                    }
                    render();
                });

                options.appendChild(chip);
            });

            group.appendChild(options);
            groupsEl.appendChild(group);
        });
    }

    function renderSummary() {
        var combination = matchedCombination();
        var label = currentLabel();

        variantEl.textContent = selector.product_description + (label ? ' · ' + label : '');

        if (!combination) {
            priceEl.innerHTML = '';
            stockEl.innerHTML = '<span>Elige una opción de cada característica</span>';
            codesEl.textContent = '';
            imageEl.src = selector.product_image_url || '';
            submitEl.disabled = true;
            return;
        }

        var symbol = combination.currency_type_symbol;
        var html = '<span class="now">' + money(symbol, combination.price) + '</span>';
        if (combination.compare_at_price && Number(combination.compare_at_price) > Number(combination.price)) {
            var off = Math.round((1 - Number(combination.price) / Number(combination.compare_at_price)) * 100);
            html += '<span class="was">' + money(symbol, combination.compare_at_price) + '</span>';
            if (off > 0) html += '<span class="off">-' + off + '%</span>';
        }
        priceEl.innerHTML = html;

        if (combination.image_url) imageEl.src = combination.image_url;

        var stock = Math.floor(Number(combination.stock));
        stockEl.innerHTML = '<span>Disponible: ' + stock + '</span>' + stockBadge(stock);

        var codes = [combination.internal_id, combination.barcode].filter(function (code) { return !!code; });
        codesEl.textContent = codes.join(' · ');

        submitEl.disabled = stock <= 0;
    }

    function renderList() {
        listView.innerHTML = '';
        var current = matchedCombination();

        selector.combinations.forEach(function (combination) {
            var stock = Math.floor(Number(combination.stock));
            var available = stock > 0;
            var codes = [combination.internal_id, combination.barcode].filter(function (code) { return !!code; });

            var row = document.createElement('div');
            row.className = 'vmodal__row' + (available ? '' : ' is-out');
            if (current && current.variation_id === combination.variation_id) row.classList.add('is-active');

            // zona principal: lleva la combinacion a la vista de atributos
            var main = document.createElement('button');
            main.type = 'button';
            main.className = 'vmodal__rowmain';
            main.disabled = !available;
            main.innerHTML =
                '<img src="' + (combination.image_url || selector.product_image_url || '') + '" alt="">' +
                '<span class="vmodal__rowinfo">' +
                    '<span class="vmodal__rowlabel">' + (combination.label || '—') + '</span>' +
                    '<span class="vmodal__rowcode">' + codes.join(' · ') + '</span>' +
                '</span>' +
                '<span class="vmodal__rowprice">' +
                    '<span class="now">' + money(combination.currency_type_symbol, combination.price) + '</span>' +
                    '<span class="st">' + (available ? stock + ' disp.' : 'Agotado') + '</span>' +
                '</span>';
            main.addEventListener('click', function () {
                selectCombination(combination);
                setTab('attrs');
                render();
            });

            // agregar sin salir de la lista: el modal de confirmacion del carrito
            // se muestra encima (z-index 11020) y al cerrarlo se sigue en la lista
            var add = document.createElement('button');
            add.type = 'button';
            add.className = 'vmodal__rowadd';
            add.disabled = !available;
            add.textContent = available ? 'Agregar' : 'Agotado';
            add.addEventListener('click', function () {
                if (!addToCart(combination)) return;
                selectCombination(combination);
                renderGroups();
                renderSummary();

                listView.querySelectorAll('.vmodal__row').forEach(function (other) {
                    other.classList.remove('is-active');
                });
                row.classList.add('is-active');
                add.textContent = 'Agregado';
                add.classList.add('is-done');
                setTimeout(function () {
                    if (!add.isConnected) return;
                    add.textContent = 'Agregar';
                    add.classList.remove('is-done');
                }, 1800);
            });

            row.appendChild(main);
            row.appendChild(add);
            listView.appendChild(row);
        });
    }

    function render() {
        renderGroups();
        renderSummary();
        renderList();
    }

    function setTab(name) {
        tabs.forEach(function (tab) {
            tab.classList.toggle('is-active', tab.getAttribute('data-vtab') === name);
        });
        attrsView.hidden = name !== 'attrs';
        listView.hidden = name !== 'list';
    }

    function open(data) {
        selector = data;
        selected = {};
        lastFocused = document.activeElement;

        productEl.textContent = data.product_description || '';
        imageEl.alt = data.product_description || '';

        // se abre con la primera combinacion disponible ya elegida
        var first = data.combinations.find(inStock) || data.combinations[0];
        if (first) selectCombination(first);

        setTab('attrs');
        render();
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        modal.querySelector('.vmodal__close').focus();
    }

    function close() {
        modal.hidden = true;
        document.body.style.overflow = '';
        selector = null;
        selected = {};
        if (lastFocused && lastFocused.focus) lastFocused.focus();
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () { setTab(tab.getAttribute('data-vtab')); });
    });

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest ? event.target.closest('.open-variations') : null;
        if (!trigger) return;

        // el JSON del selector se declara una sola vez, en la card contenedora
        var card = trigger.closest('[data-variation-selector]');
        if (!card) return;

        event.preventDefault();
        try {
            open(JSON.parse(card.getAttribute('data-variation-selector')));
        } catch (e) {
            console.error('Selector de variantes inválido', e);
        }
    });

    modal.addEventListener('click', function (event) {
        if (event.target.closest('[data-variations-close]')) close();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !modal.hidden) close();
    });

    submitEl.addEventListener('click', function () {
        if (addToCart(matchedCombination())) close();
    });
})();
</script>
@endonce
