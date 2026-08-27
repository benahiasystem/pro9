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
    <div class="vmodal__dialog" role="dialog" aria-modal="true" aria-labelledby="variations-modal-title">
        <button type="button" class="vmodal__close" data-variations-close aria-label="Cerrar">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
        </button>

        <div class="vmodal__head">
            <div class="vmodal__thumb">
                <img id="variations-modal-image" src="" alt="">
            </div>
            <div class="vmodal__headinfo">
                <span class="vmodal__eyebrow">Elige tus opciones</span>
                <h4 class="vmodal__title" id="variations-modal-title"></h4>
                <div class="vmodal__price" id="variations-modal-price"></div>
            </div>
        </div>

        <div class="vmodal__body" id="variations-modal-groups"></div>

        <div class="vmodal__footer">
            <p class="vmodal__hint" id="variations-modal-hint"></p>
            <button type="button" class="vmodal__cta" id="variations-modal-submit" disabled>
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                <span>Agregar al carrito</span>
            </button>
            <a class="vmodal__detail" id="variations-modal-detail" href="#">Ver detalle del producto</a>
        </div>
    </div>
</div>

<style>
    .variation-price-from { font-size: 12px; color: #888; margin-right: 4px; }

    .vmodal {
        /* Se arma desde los componentes HSL del tema, cada uno con su propio
           fallback: si el tenant no configuro color, --primary-color resolveria
           a un hsl() invalido y el boton se quedaria sin fondo. */
        --vm-accent: hsl(var(--primary-h, 28), var(--primary-s, 92%), var(--primary-l, 55%));
        --vm-ink: #1f2937;
        --vm-muted: #6b7280;
        --vm-line: #e5e7eb;
        position: fixed; inset: 0; z-index: 1090;
        display: flex; align-items: center; justify-content: center; padding: 20px;
        font-size: 14px; line-height: 1.45;
    }
    .vmodal[hidden] { display: none; }
    .vmodal__backdrop { position: absolute; inset: 0; background: rgba(17, 24, 39, .55); backdrop-filter: blur(2px); animation: vm-fade .18s ease-out; }
    .vmodal__dialog {
        position: relative; background: #fff; border-radius: 16px; width: 100%; max-width: 440px;
        max-height: 88vh; display: flex; flex-direction: column; overflow: hidden;
        box-shadow: 0 24px 60px rgba(0, 0, 0, .28); animation: vm-pop .22s cubic-bezier(.2, .9, .3, 1);
    }
    @keyframes vm-fade { from { opacity: 0 } to { opacity: 1 } }
    @keyframes vm-pop { from { opacity: 0; transform: translateY(14px) scale(.97) } to { opacity: 1; transform: none } }

    .vmodal__close {
        position: absolute; top: 12px; right: 12px; z-index: 2;
        width: 34px; height: 34px; border: 0; border-radius: 50%; background: rgba(255, 255, 255, .9);
        color: var(--vm-muted); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: .18s;
    }
    .vmodal__close:hover { background: #f3f4f6; color: var(--vm-ink); }

    .vmodal__head { display: flex; gap: 14px; padding: 20px 52px 16px 20px; border-bottom: 1px solid var(--vm-line); }
    .vmodal__thumb {
        width: 76px; height: 76px; flex: 0 0 76px; border-radius: 12px; overflow: hidden;
        background: #f5f5f5; display: flex; align-items: center; justify-content: center;
    }
    .vmodal__thumb img { width: 100%; height: 100%; object-fit: cover; }
    .vmodal__headinfo { min-width: 0; flex: 1 1 auto; }
    .vmodal__eyebrow { display: block; font-size: 11px; letter-spacing: .08em; text-transform: uppercase; color: var(--vm-muted); margin-bottom: 2px; }
    .vmodal__title {
        margin: 0 0 6px; font-size: 15px; font-weight: 700; color: var(--vm-ink); line-height: 1.3;
        display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
    }
    .vmodal__price { display: flex; align-items: baseline; flex-wrap: wrap; gap: 8px; }
    .vmodal__price .now { font-size: 20px; font-weight: 800; color: var(--vm-ink); }
    .vmodal__price .from { font-size: 12px; color: var(--vm-muted); margin-right: -4px; }
    .vmodal__price .was { font-size: 13px; color: #9ca3af; text-decoration: line-through; }
    .vmodal__price .off {
        font-size: 11px; font-weight: 700; color: #fff; background: #dc2626;
        border-radius: 4px; padding: 2px 6px; letter-spacing: .02em;
    }

    .vmodal__body { padding: 18px 20px 4px; overflow-y: auto; flex: 1 1 auto; }
    .vmodal__group { margin-bottom: 18px; }
    .vmodal__grouplabel { display: block; font-size: 13px; color: var(--vm-muted); margin-bottom: 9px; }
    .vmodal__grouplabel b { color: var(--vm-ink); font-weight: 700; }
    .vmodal__options { display: flex; flex-wrap: wrap; gap: 9px; }

    .vmodal__chip {
        position: relative; display: inline-flex; align-items: center; justify-content: center;
        min-width: 46px; height: 42px; padding: 0 14px; border: 1.5px solid var(--vm-line); border-radius: 8px;
        background: #fff; font-size: 13px; font-weight: 600; color: var(--vm-ink); cursor: pointer; transition: .16s;
    }
    .vmodal__chip:hover:not(:disabled) { border-color: #9ca3af; }
    .vmodal__chip.is-active { border-color: var(--vm-accent); box-shadow: inset 0 0 0 1px var(--vm-accent); color: var(--vm-accent); }

    .vmodal__chip--color { width: 42px; min-width: 42px; padding: 0; border-radius: 50%; }
    .vmodal__chip--color .dot { width: 100%; height: 100%; border-radius: 50%; border: 2px solid #fff; box-shadow: inset 0 0 0 1px rgba(0, 0, 0, .12); }
    .vmodal__chip--color.is-active { box-shadow: 0 0 0 2px var(--vm-accent); border-color: #fff; }

    /* combinacion inexistente o sin stock: se marca tachada */
    .vmodal__chip:disabled { cursor: not-allowed; color: #b6b9be; border-color: #eceef1; }
    .vmodal__chip:disabled::after {
        content: ''; position: absolute; inset: 0; border-radius: inherit;
        background: linear-gradient(to top left, transparent calc(50% - 1px), #d1d5db calc(50% - 1px), #d1d5db calc(50% + 1px), transparent calc(50% + 1px));
    }
    .vmodal__chip--color:disabled { opacity: .5; }

    .vmodal__footer { padding: 14px 20px 18px; border-top: 1px solid var(--vm-line); background: #fff; }
    .vmodal__hint { margin: 0 0 10px; font-size: 12.5px; color: var(--vm-muted); min-height: 18px; }
    .vmodal__hint.is-urgent { color: #dc2626; font-weight: 600; }
    .vmodal__cta {
        width: 100%; height: 48px; border: 0; border-radius: 10px; cursor: pointer;
        background: var(--vm-accent); color: #fff; font-size: 15px; font-weight: 700;
        display: flex; align-items: center; justify-content: center; gap: 8px; transition: .18s;
    }
    .vmodal__cta:hover:not(:disabled) { filter: brightness(.93); }
    .vmodal__cta:disabled { background: #e5e7eb; color: #9ca3af; cursor: not-allowed; }
    .vmodal__detail { display: block; text-align: center; margin-top: 12px; font-size: 13px; color: var(--vm-muted); text-decoration: underline; }
    .vmodal__detail:hover { color: var(--vm-ink); }

    /* movil: hoja inferior */
    @media (max-width: 575.98px) {
        .vmodal { padding: 0; align-items: flex-end; }
        .vmodal__dialog { max-width: 100%; border-radius: 18px 18px 0 0; max-height: 92vh; animation: vm-sheet .26s cubic-bezier(.2, .9, .3, 1); }
        @keyframes vm-sheet { from { transform: translateY(100%) } to { transform: none } }
        .vmodal__footer { position: sticky; bottom: 0; box-shadow: 0 -6px 18px rgba(0, 0, 0, .06); }
    }
</style>

<script>
// Modal de variantes del listado. Misma logica de disponibilidad que el selector
// de la ficha (record.blade.php), en JS plano porque el listado no monta Vue.
(function () {
    var modal = document.getElementById('variations-modal');
    if (!modal) return;

    var titleEl = document.getElementById('variations-modal-title');
    var imageEl = document.getElementById('variations-modal-image');
    var priceEl = document.getElementById('variations-modal-price');
    var groupsEl = document.getElementById('variations-modal-groups');
    var hintEl = document.getElementById('variations-modal-hint');
    var submitEl = document.getElementById('variations-modal-submit');
    var detailEl = document.getElementById('variations-modal-detail');

    var selector = null;
    var selected = {};
    var lastFocused = null;

    function money(symbol, value) {
        return (symbol || 'S/') + ' ' + Number(value).toFixed(2);
    }

    function inStock(combination) {
        return Number(combination.stock) > 0;
    }

    // Un valor esta disponible si alguna combinacion con stock lo incluye junto a
    // lo ya elegido en las demas variables.
    function valueAvailable(variableId, valueId) {
        return selector.combinations.some(function (combination) {
            if (combination.value_ids.indexOf(valueId) === -1) return false;
            if (!inStock(combination)) return false;
            return Object.keys(selected).every(function (otherVariableId) {
                if (String(otherVariableId) === String(variableId)) return true;
                return combination.value_ids.indexOf(selected[otherVariableId]) !== -1;
            });
        });
    }

    function matchedCombination() {
        var ids = Object.keys(selected).map(function (key) { return Number(selected[key]); }).sort(function (a, b) { return a - b; });
        if (ids.length !== selector.variables.length) return null;

        return selector.combinations.find(function (combination) {
            return combination.value_ids.length === ids.length &&
                combination.value_ids.every(function (id, index) { return id === ids[index]; });
        }) || null;
    }

    function selectedValueName(variable) {
        var valueId = selected[variable.id];
        if (!valueId) return null;
        var value = variable.values.find(function (candidate) { return candidate.id === valueId; });
        return value ? value.value : null;
    }

    function renderGroups() {
        groupsEl.innerHTML = '';

        selector.variables.forEach(function (variable) {
            var group = document.createElement('div');
            group.className = 'vmodal__group';

            var label = document.createElement('span');
            label.className = 'vmodal__grouplabel';
            var chosen = selectedValueName(variable);
            label.innerHTML = variable.name + (chosen ? ': <b>' + chosen + '</b>' : '');
            group.appendChild(label);

            var options = document.createElement('div');
            options.className = 'vmodal__options';
            var isColor = variable.value_type === 'color';

            variable.values.forEach(function (value) {
                var chip = document.createElement('button');
                chip.type = 'button';
                chip.className = 'vmodal__chip' + (isColor && value.color ? ' vmodal__chip--color' : '');
                chip.title = value.value;
                if (selected[variable.id] === value.id) chip.classList.add('is-active');
                chip.disabled = !valueAvailable(variable.id, value.id);

                if (isColor && value.color) {
                    var dot = document.createElement('span');
                    dot.className = 'dot';
                    dot.style.background = value.color;
                    chip.appendChild(dot);
                } else {
                    chip.appendChild(document.createTextNode(value.value));
                }

                chip.addEventListener('click', function () {
                    if (selected[variable.id] === value.id) {
                        delete selected[variable.id];
                    } else {
                        selected[variable.id] = value.id;
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
        var symbol = selector.currency_type_symbol;

        if (!combination) {
            var available = selector.combinations.filter(inStock);
            var cheapest = available.length
                ? available.reduce(function (min, row) { return Number(row.price) < Number(min.price) ? row : min; })
                : null;

            priceEl.innerHTML = cheapest
                ? '<span class="from">Desde</span><span class="now">' + money(symbol, cheapest.price) + '</span>'
                : '';
            imageEl.src = selector.product_image_url || '';
            hintEl.textContent = 'Elige una opción de cada característica.';
            hintEl.classList.remove('is-urgent');
            submitEl.disabled = true;
            detailEl.href = selector.product_url || '#';
            return;
        }

        var html = '<span class="now">' + money(combination.currency_type_symbol, combination.price) + '</span>';
        if (combination.compare_at_price && Number(combination.compare_at_price) > Number(combination.price)) {
            var off = Math.round((1 - Number(combination.price) / Number(combination.compare_at_price)) * 100);
            html += '<span class="was">' + money(combination.currency_type_symbol, combination.compare_at_price) + '</span>';
            if (off > 0) html += '<span class="off">-' + off + '%</span>';
        }
        priceEl.innerHTML = html;

        if (combination.image_url) imageEl.src = combination.image_url;
        detailEl.href = combination.url || selector.product_url || '#';

        var stock = Math.floor(Number(combination.stock));
        if (stock <= 0) {
            hintEl.textContent = 'Esta combinación está agotada.';
            hintEl.classList.add('is-urgent');
            submitEl.disabled = true;
            return;
        }

        if (stock <= 5) {
            hintEl.textContent = stock === 1 ? '¡Última unidad disponible!' : '¡Solo quedan ' + stock + ' unidades!';
            hintEl.classList.add('is-urgent');
        } else {
            hintEl.textContent = stock + ' unidades disponibles';
            hintEl.classList.remove('is-urgent');
        }
        submitEl.disabled = false;
    }

    function render() {
        renderGroups();
        renderSummary();
    }

    function open(data) {
        selector = data;
        selected = {};
        lastFocused = document.activeElement;

        titleEl.textContent = data.product_description || 'Elige una opción';
        imageEl.alt = data.product_description || '';

        // con una sola variable y una sola opcion con stock, se preselecciona
        if (data.variables.length === 1) {
            var only = data.variables[0].values.filter(function (value) {
                return data.combinations.some(function (combination) {
                    return combination.value_ids.indexOf(value.id) !== -1 && inStock(combination);
                });
            });
            if (only.length === 1) selected[data.variables[0].id] = only[0].id;
        }

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
        var combination = matchedCombination();
        if (!combination || !inStock(combination)) return;

        if (typeof cartAddOrUpdateItem === 'function') {
            cartAddOrUpdateItem(combination.cart, { quantity: 1 });
        } else if (typeof cart_add === 'function') {
            cart_add(combination.cart);
        }
        close();
    });
})();
</script>
@endonce
