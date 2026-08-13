@php
    $configurationModel = \App\Models\Tenant\Configuration::first();
    $ecommerceConfiguration = $ecommerceConfiguration ?? ($configEcommerce ?? \App\Models\Tenant\ConfigurationEcommerce::first());
    $phoneWhatsapp = $ecommerceConfiguration->phone_whatsapp ?? $configurationModel->phone_whatsapp ?? null;
    $defaultImage = $configurationModel->product_default_image ?? 'imagen-no-disponible.jpg';
    $defaultImagePath = $defaultImage === 'imagen-no-disponible.jpg'
        ? asset('logo/imagen-no-disponible.jpg')
        : asset('storage/defaults/' . $defaultImage);
    $mainImagePath = ($record->image && $record->image !== 'imagen-no-disponible.jpg')
        ? asset('storage/uploads/items/'.$record->image)
        : $defaultImagePath;
@endphp
<div class="product-single-container product-single-default product-quick-view container position-relative p-0">
    <div class="row mx-0">
        <div class="col-lg-6 col-md-6 product-single-gallery px-5 py-5 preview-img-container">
            <div class="product-slider-container product-item">
                <div class="product-single-carousel owl-carousel owl-theme">
                    <div class="product-item">
                        <img class="product-single-image" src="{{ $mainImagePath }}"
                             data-zoom-image="{{ $mainImagePath }}" alt="{{ $record->description }}" />
                    </div>

                    @foreach($record->images as $row)

                    <div class="product-item">
                        @php
                            $loopImagePath = ($row->image && $row->image !== 'imagen-no-disponible.jpg')
                                ? asset('storage/uploads/items/'.$row->image)
                                : $defaultImagePath;
                        @endphp
                        <img class="product-single-image"
                             src="{{ $loopImagePath }}"
                             data-zoom-image="{{ $loopImagePath }}" alt="{{ $record->description }}" />
                    </div>

                    @endforeach

                    <!--<div class="product-item">
                        <img class="product-single-image"
                            src="{{ asset('storage/uploads/items/'.$record->image_medium) }}"
                            data-zoom-image="{{ asset('storage/uploads/items/'.$record->image_medium) }}" />
                    </div> -->

                </div>

            </div>
            @if($record->images->count() > 0)
            <div class="prod-thumbnail row owl-dots" id='carousel-custom-dots'>
                <div class="col-3 owl-dot">
                    <img class="img-miniature" src="{{ $mainImagePath }}" alt="{{ $record->description }}" />
                </div>

                @foreach($record->images as $row)

                    <div class="col-3 owl-dot">
                        @php
                            $thumbImagePath = ($row->image && $row->image !== 'imagen-no-disponible.jpg')
                                ? asset('storage/uploads/items/'.$row->image)
                                : $defaultImagePath;
                        @endphp
                        <img src="{{ $thumbImagePath }}" alt="{{ $record->description }}" />
                    </div>

                @endforeach

                <!--<div class="col-3 owl-dot">
                    <img src="{{ asset('porto_ecommerce/ajax/assets/images/products/zoom/product-2.html') }}" />
                </div>
                <div class="col-3 owl-dot">
                    <img src="{{ asset('porto_ecommerce/ajax/assets/images/products/zoom/product-3.html') }}" />
                </div>
                <div class="col-3 owl-dot">
                    <img src="{{ asset('porto_ecommerce/ajax/assets/images/products/zoom/product-4.html') }}" />
                </div>-->
            </div>
            @endif
        </div><!-- End .col-lg-7 -->

        <div class="col-lg-6 col-md-6 px-5 py-5">
            <div class="product-single-details mt-0">
                @if ($record->category && $record->category->name)
                <span class="tag-ecommerce primary text-uppercase">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-tag mr-1" style="margin-top: -2px"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M6.5 7.5a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" /><path d="M3 6v5.172a2 2 0 0 0 .586 1.414l7.71 7.71a2.41 2.41 0 0 0 3.408 0l5.592 -5.592a2.41 2.41 0 0 0 0 -3.408l-7.71 -7.71a2 2 0 0 0 -1.414 -.586h-5.172a3 3 0 0 0 -3 3" /></svg>
                    {{$record->category->name}}
                </span>
                @endif

                <h1 class="product-title tony mt-1">{{$record->description}}</h1>

                @php
                    $activeCampaign = null;
                    $hasActiveOffer = false;
                    $activeOfferPrice = (float) $record->sale_unit_price;
                    $compareAtPrice = null;
                    $offerExpiresAt = null;
                    $stock = (float) $record->getStockByWarehouseMain();
                    $stockThreshold = 10;

                    if (isset($campaigns) && count($campaigns) > 0) {
                        $activeCampaign = $campaigns instanceof \Illuminate\Support\Collection
                            ? $campaigns->first()
                            : (is_array($campaigns) ? ($campaigns[0] ?? null) : $campaigns);
                    }

                    if ($activeCampaign) {
                        $stockThreshold = method_exists($activeCampaign, 'stockThreshold')
                            ? $activeCampaign->stockThreshold()
                            : (int) ($activeCampaign->sp_stock_threshold ?: 10);

                        if (method_exists($activeCampaign, 'hasActiveDiscount')
                            ? $activeCampaign->hasActiveDiscount()
                            : ($activeCampaign->sp_discount_price && (! $activeCampaign->end_date || $activeCampaign->end_date > now()))
                        ) {
                            $hasActiveOffer = true;
                            $activeOfferPrice = method_exists($activeCampaign, 'discountedPrice')
                                ? $activeCampaign->discountedPrice((float) $record->sale_unit_price)
                                : (float) $record->sale_unit_price;
                            $compareAtPrice = method_exists($activeCampaign, 'compareAtPrice')
                                ? $activeCampaign->compareAtPrice((float) $record->sale_unit_price)
                                : null;
                            if (! $compareAtPrice) {
                                $hasActiveOffer = false;
                            }
                        }

                        // Evergreen: si venció, rollForwardCountdownIfNeeded ya sumó +1 día en el modelo.
                        if (method_exists($activeCampaign, 'hasActiveCountdown')
                            ? $activeCampaign->hasActiveCountdown()
                            : ($activeCampaign->sp_countdown && $activeCampaign->end_date && $activeCampaign->end_date > now())
                        ) {
                            $offerExpiresAt = $activeCampaign->end_date;
                        }
                    }

                    $campaignPricing = app(\Modules\Ecommerce\Services\CampaignPriceService::class)->forItem($record);
                    $activeOfferPrice = $campaignPricing['final_price'];
                    $compareAtPrice = $campaignPricing['compare_at_price'];
                    $hasActiveOffer = $campaignPricing['has_social_proof_price'] || $campaignPricing['has_real_discount'];
                    $displayPrice = (float) $activeOfferPrice;
                    $oldPrice = ($hasActiveOffer && $compareAtPrice) ? (float) $compareAtPrice : null;
                    $savings = $oldPrice !== null ? max(0, $oldPrice - $displayPrice) : 0;
                    $showPrices = $storefront_show_prices ?? true;
                    $ratingCount = rand(45, 120);
                    $purchaseCount = $activeCampaign
                        ? rand((int) $activeCampaign->sp_purchase_min, max((int) $activeCampaign->sp_purchase_min, (int) $activeCampaign->sp_purchase_max))
                        : 0;
                    $viewersCount = $activeCampaign
                        ? rand((int) $activeCampaign->sp_views_min, max((int) $activeCampaign->sp_views_min, (int) $activeCampaign->sp_views_max))
                        : 0;

                    $qvProduct = [
                        'id' => $record->id,
                        'description' => $record->description,
                        'sale_unit_price' => $displayPrice,
                        'original_price' => (float) $record->sale_unit_price,
                        'compare_at_price' => $compareAtPrice,
                        'discount_campaign_id' => $campaignPricing['discount_campaign_id'],
                        'discount_campaign_name' => $campaignPricing['discount_campaign_name'],
                        'campaign_discount_percent' => $campaignPricing['real_discount_percentage'],
                        'campaign_discount_embedded' => $campaignPricing['has_real_discount'],
                        'has_discount' => $hasActiveOffer,
                        'discount_percent' => $campaignPricing['real_discount_percentage'],
                        'image' => $record->image,
                        'image_small' => $record->image_small ?? $record->image,
                        'image_medium' => $record->image_medium ?? $record->image,
                        'currency_type_id' => $record->currency_type_id ?? 'PEN',
                        'currency_type_symbol' => optional($record->currency_type)->symbol ?? 'S/',
                        'sale_affectation_igv_type_id' => $record->sale_affectation_igv_type_id ?? '10',
                        'unit_type_id' => $record->unit_type_id ?? 'NIU',
                        'internal_id' => $record->internal_id,
                        'stock' => (int) $stock,
                    ];
                @endphp

                <div class="social-proof-container mb-2">
                    @if($activeCampaign && $activeCampaign->sp_rating)
                    <div class="card-rating-social-proof mb-1" style="font-size: 14px;">
                        <span style="color: #333; font-weight: bold; margin-right: 4px;">5.0</span>
                        <span style="color: #ffc107;">★★★★★</span>
                        <span style="color: #777; margin-left: 4px; font-size: 12px;">({{ $ratingCount }} opiniones)</span>
                    </div>
                    @endif

                    @if($showPrices)
                    <div class="price-box preview d-flex align-items-end justify-content-start w-100 mb-1" style="gap: 10px">
                        <span class="product-price">{{ optional($record->currency_type)->symbol ?? 'S/' }} {{ number_format($displayPrice, 2) }}</span>
                        @if($oldPrice !== null)
                            <span class="old-price">{{ optional($record->currency_type)->symbol ?? 'S/' }} {{ number_format($oldPrice, 2) }}</span>
                            @if($savings > 0)
                            <span class="tag-ecommerce warning">
                                Ahorras {{ optional($record->currency_type)->symbol ?? 'S/' }} {{ number_format($savings, 2) }}
                            </span>
                            @endif
                        @endif
                    </div>
                    @endif

                    @if($offerExpiresAt)
                    <div id="sp-countdown-qv-{{ $record->id }}" class="countdown-badge alert alert-warning p-2 mb-2 d-inline-block shadow-sm" style="border-radius: 8px; font-size: 0.85rem; border-left: 4px solid #dc3545;">
                        <i class="far fa-clock text-danger"></i> ¡Termina en:
                        <strong class="time-left">Cargando...</strong>!
                    </div>
                    @endif

                    @if($activeCampaign && $activeCampaign->sp_stock_alert && $stock > 0 && $stock <= $stockThreshold)
                    <div class="text-danger font-weight-bold small mb-1" style="animation: sp-qv-pulse 2s infinite;">
                        <i class="fas fa-fire"></i> ¡Se agota rápido! Solo quedan {{ number_format($stock, 0) }} unidades.
                    </div>
                    @endif

                    @if($activeCampaign && $activeCampaign->sp_views_count)
                    <div class="text-muted small mb-1">
                        <i class="far fa-eye text-info"></i> <strong id="sp-viewers-qv-{{ $record->id }}">{{ $viewersCount }}</strong> personas están viendo este producto.
                    </div>
                    @endif

                    @if($activeCampaign && $activeCampaign->sp_purchase_count)
                    <div class="text-success small mb-1 font-weight-bold">
                        <i class="fas fa-shopping-cart"></i> {{ $purchaseCount }} personas lo compraron en los últimos 7 días.
                    </div>
                    @endif
                </div>
                <style>@keyframes sp-qv-pulse{0%{opacity:1}50%{opacity:.75}100%{opacity:1}}</style>

                <div class="stock-row mb-1">
                    <?php
                    if($stock > 0){?>
                        <span class="stock-dot success"></span>
                        <span><b>En stock</b> · {{number_format($stock, 0)}} unidades disponibles</span>
                    <?php
                    }else{?>
                        <span class="stock-dot danger"></span>
                        <span><b class="text-danger">Sin stock</b> · temporalmente agotado</span>
                    <?php
                    }
                    ?>
                </div>
                <div class="product-desc pb-4 mb-2">
                    <p class="mb-0">{!! $record->name !!}</p>
                </div><!-- End .product-desc -->



                <div class="product-action w-100 d-flex align-items-center justify-content-between" style="gap: 10px"
                    data-qv-scope
                    data-unit-price="{{ $displayPrice }}"
                    data-symbol="{{ optional($record->currency_type)->symbol ?? 'S/' }}"
                    data-qv-product="{{ e(json_encode($qvProduct)) }}">
                    @php
                        $stockQv = $stock;
                        $showWhatsapp = ($configurationModel->enable_whatsapp ?? false) && !empty($phoneWhatsapp);
                        if ($showWhatsapp) {
                            $waPhoneRaw = preg_replace('/\D+/', '', $phoneWhatsapp);
                            $waPhone = (strlen($waPhoneRaw) == 9 && str_starts_with($waPhoneRaw, '9')) ? '51'.$waPhoneRaw : $waPhoneRaw;
                            $symbol = optional($record->currency_type)->symbol ?? 'S/';
                            $waText = rawurlencode(
                                ($storefront_show_prices ?? true)
                                    ? "Buenas, deseo consultar acerca del producto *{$record->description}*, con precio de {$symbol}{$displayPrice}. ¿Podrían brindarme más información?"
                                    : "Buenas, deseo consultar acerca del producto *{$record->description}*. ¿Podrían brindarme más información?"
                            );
                            $waLink = "https://wa.me/{$waPhone}?text={$waText}";
                        }
                    @endphp
                    @if($stockQv > 0)
                    <div class="input-group input-group-sm modern-quantity-container w-auto">
                        <div class="input-group-prepend">
                            <button class="btn btn-outline-secondary btn-input-group" type="button" onclick="qvStep(this, -1)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l14 0" /></svg>
                            </button>
                        </div>
                        <input class="input-quantity text-center qv-quantity" type="number" min="1" value="1"
                            oninput="qvUpdatePrice(qvScope(this))" onchange="qvSync(this)">
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary btn-input-group" type="button" onclick="qvStep(this, 1)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
                            </button>
                        </div>
                    </div>

                    <a href="javascript:void(0)" role="button" onclick="event.preventDefault(); qvAddToCart(this); return false;" class="paction add-cart w-auto m-0"
                        title="Add to Cart" style="flex: 1; height: 39px; padding: 0 12px;">
                        <svg clip-rule="evenodd" fill-rule="evenodd" height="24" stroke-linejoin="round" stroke-miterlimit="2" viewBox="0 0 512 512" width="24" xmlns="http://www.w3.org/2000/svg" id="fi_4893746"><path d="m211.892 383.468c24.344 0 44.108 19.764 44.108 44.108s-19.764 44.108-44.108 44.108-44.108-19.764-44.108-44.108 19.764-44.108 44.108-44.108zm176.22 0c24.344 0 44.108 19.764 44.108 44.108s-19.764 44.108-44.108 44.108-44.108-19.764-44.108-44.108 19.764-44.108 44.108-44.108zm-288.464-273.226s63.534 222.705 63.534 222.705c6.591 23.103 27.703 39.034 51.727 39.034h157.478c33.502 0 61.98-24.47 67.023-57.59 4.821-31.664 11.838-77.75 17.065-112.081 2.869-18.84-2.626-37.994-15.046-52.449-12.42-14.454-30.529-22.769-49.586-22.769h-235.394l-8.72-30.567c-7.633-26.757-32.085-45.209-59.91-45.209-23.033 0-51.825 0-51.825 0-13.798 0-25 11.202-25 25s11.202 25 25 25h51.825c5.494 0 10.321 3.643 11.829 8.926zm71.066 66.85h221.129c4.482 0 8.741 1.956 11.663 5.355 2.921 3.4 4.213 7.905 3.539 12.337 0 0-17.066 112.081-17.066 112.081-1.323 8.693-8.798 15.116-17.592 15.116h-157.478c-1.693 0-3.181-1.122-3.645-2.751 0 0-40.55-142.138-40.55-142.138z"></path></svg>
                        <span class="font-weight-bold qv-add-label" style="white-space: nowrap;">
                            @if($showPrices ?? ($storefront_show_prices ?? true))
                                Agregar a Carrito · {{ optional($record->currency_type)->symbol ?? 'S/' }} {{ number_format($displayPrice, 2) }}
                            @else
                                Agregar a Carrito
                            @endif
                        </span>
                    </a>
                    @else
                    <div class="d-flex flex-column w-100" style="gap: 10px">
                        <button class="btn btn-disabled">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-mood-sad" style="margin-top: -2px"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" /><path d="M9 10l.01 0" /><path d="M15 10l.01 0" /><path d="M9.5 15.25a3.5 3.5 0 0 1 5 0" /></svg>
                            Agotado por ahora
                        </button>

                        <button type="button" class="btn btn-outline-primary" onclick="jQuery.magnificPopup.close()">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-search" style="margin-top: -2px"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M3 10a7 7 0 1 0 14 0a7 7 0 1 0 -14 0" /><path d="M21 21l-6 -6" /></svg>
                            Seguir buscando
                        </button>
                    </div>
                    @endif

                    @if($showWhatsapp)
                        <a href="{{ $waLink }}" class="btn-whatsapp" target="_blank" rel="noopener" title="Consultar por WhatsApp">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-brand-whatsapp" style="margin-top: -3px"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 21l1.65 -3.8a9 9 0 1 1 3.4 2.9l-5.05 .9" /><path d="M9 10a.5 .5 0 0 0 1 0v-1a.5 .5 0 0 0 -1 0v1a5 5 0 0 0 5 5h1a.5 .5 0 0 0 0 -1h-1a.5 .5 0 0 0 0 1" /></svg>
                            <span>Consultar por WhatsApp</span>
                        </a>
                    @endif

                </div><!-- End .product-action -->

            </div><!-- End .product-single-details -->
        </div><!-- End .col-lg-5 -->
    </div><!-- End .row -->
</div><!-- End .product-single-container -->

@if($offerExpiresAt)
<script>
(function () {
    var targetDate = {{ (int) \Carbon\Carbon::parse($offerExpiresAt)->getTimestamp() }} * 1000;
    var dayMs = 24 * 60 * 60 * 1000;
    var root = document.getElementById('sp-countdown-qv-{{ $record->id }}');
    if (!root) return;
    var countdownEl = root.querySelector('.time-left');
    var timer = setInterval(function () {
        // Evergreen: al vencer, +1 día a la misma hora y el contador sigue.
        while (targetDate <= Date.now()) {
            targetDate += dayMs;
        }
        var difference = targetDate - Date.now();
        var days = Math.floor(difference / dayMs);
        var hours = Math.floor((difference % dayMs) / (1000 * 60 * 60));
        var minutes = Math.floor((difference % (1000 * 60 * 60)) / (1000 * 60));
        var seconds = Math.floor((difference % (1000 * 60)) / 1000);
        if (countdownEl) {
            countdownEl.innerHTML = days + 'd ' + hours + 'h ' + minutes + 'm ' + seconds + 's';
        }
    }, 1000);
})();
</script>
@endif

<style>
.price-box.preview{
    display: flex;
    flex-wrap: wrap; /* permite bajar los elementos completos */
    gap: 10px;
}

.price-box.preview .product-price,
.price-box.preview .old-price,
.price-box.preview .tag-ecommerce{
    white-space: nowrap; /* evita S/ arriba y el monto abajo */
}
@media (max-width: 576px){
    .price-box.preview{
        flex-direction: column;
        align-items: flex-start !important;
        gap: 4px;
    }
}
.product-action{
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.modern-quantity-container{
    flex: 0 0 auto;
}

.paction.add-cart{
    flex: 1;
    min-width: 0;
}

.btn-whatsapp{
    flex: 1 1 100%;
}

@media (max-width: 768px){
    .product-action{
        flex-direction: column;
    }

    .modern-quantity-container,
    .paction.add-cart,
    .btn-whatsapp{
        width: 100%;
    }

    .modern-quantity-container{
        justify-content: center;
    }
}
</style>
