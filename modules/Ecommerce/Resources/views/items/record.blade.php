@extends('ecommerce::layouts.layout_ecommerce_item.record')

@section('content')

@php
    $configurationModel = \App\Models\Tenant\Configuration::first();
    $ecommerceConfiguration = \App\Models\Tenant\ConfigurationEcommerce::first();
    $phoneWhatsapp = $ecommerceConfiguration->phone_whatsapp ?? $configurationModel->phone_whatsapp ?? null;
    $defaultImage = $configurationModel->product_default_image ?? 'imagen-no-disponible.jpg';
    $defaultImagePath = $defaultImage === 'imagen-no-disponible.jpg'
        ? asset('logo/imagen-no-disponible.jpg')
        : asset('storage/defaults/' . $defaultImage);
    $mainImagePath = ($record->image && $record->image !== 'imagen-no-disponible.jpg')
        ? asset('storage/uploads/items/'.$record->image)
        : $defaultImagePath;
@endphp

@php
    $productSpecs = collect([
        'Código interno'   => $record->internal_id,
        'Código de barras' => $record->barcode,
        'Marca'            => optional($record->brand)->name,
        'Colección'        => $record->model,
        'Línea'            => $record->line,
        'Categoría'        => optional($record->category)->name,
        'Nombre de fábrica'=> $record->second_name,
        'Unidad de medida' => optional($record->unit_type)->description,
    ])->filter(fn($value) => filled($value));
    $hasSpecs = filled($record->technical_specifications) || $productSpecs->isNotEmpty();
@endphp

<div class="product-single-container product-single-default pdp-page">
    <div class="row pdp-row">
        <div class="col-lg-7 col-md-6 product-single-gallery pdp-gallery">
            <div class="product-slider-container product-item">
                <div class="product-single-carousel owl-carousel owl-theme">
                    <div class="product-item">
                        <img class="product-single-image" src="{{ $mainImagePath }}"
                            data-zoom-image="{{ $mainImagePath }}" />
                            
                    </div>
                    @foreach($record->images as $row)

                        <div class="product-item">
                            @php
                                $loopImagePath = ($row->image && $row->image !== 'imagen-no-disponible.jpg')
                                    ? asset('storage/uploads/items/'.$row->image)
                                    : $defaultImagePath;
                            @endphp
                            <img class="product-single-image" src="{{ $loopImagePath }}"
                                 data-zoom-image="{{ $loopImagePath }}" alt="{{ $record->description }}" />
                        </div>

                    @endforeach
                    <!--<div class="product-item">
                        <img class="product-single-image" src="assets/images/products/zoom/product-2.jpg"
                            data-zoom-image="assets/images/products/zoom/product-2-big.jpg" />
                    </div>
                    <div class="product-item">
                        <img class="product-single-image" src="assets/images/products/zoom/product-3.jpg"
                            data-zoom-image="assets/images/products/zoom/product-3-big.jpg" />
                    </div>
                    <div class="product-item">
                        <img class="product-single-image" src="assets/images/products/zoom/product-4.jpg"
                            data-zoom-image="assets/images/products/zoom/product-4-big.jpg" />
                    </div>-->
                </div>
                <!-- End .product-single-carousel -->
                <span class="prod-full-screen">
                    <i class="icon-plus"></i>
                </span>
            </div>
            @if($record->images->count() > 0)
            <div class="prod-thumbnail row owl-dots" id='carousel-custom-dots'>
                <div class="col-3 owl-dot">
                    <img src="{{ $mainImagePath }}" alt="{{ $record->description }}" />
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
                    <img src="assets/images/products/zoom/product-2.jpg" />
                </div>
                <div class="col-3 owl-dot">
                    <img src="assets/images/products/zoom/product-3.jpg" />
                </div>
                <div class="col-3 owl-dot">
                    <img src="assets/images/products/zoom/product-4.jpg" />
                </div> -->
            </div>
            @endif
        </div><!-- End .col-lg-7 -->

        <div class="col-lg-5 col-md-6 pdp-aside">
            <div id="product-detail-vue" class="product-single-details pdp-panel">

                @php
                    $activeCampaign = null;
                    $hasActiveOffer = false;
                    $activeOfferPrice = (float) $record->sale_unit_price;
                    $compareAtPrice = null;
                    $offerExpiresAt = null;
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
                    $activeOfferPrice = $campaignPricing['final_price'];
                    $compareAtPrice = $campaignPricing['compare_at_price'];
                    $hasActiveOffer = $campaignPricing['has_social_proof_price'] || $campaignPricing['has_real_discount'];
                @endphp

                <style>[v-cloak]{display:none}</style>

                <div class="pdp-head">
                    @if(($record->brand && $record->brand->id) || !empty($record->internal_id))
                    <div class="pdp-eyebrow">
                        @if ($record->brand && $record->brand->id)
                            <a class="pdp-brand" href="{{ route('tenant.ecommerce.brand', ['id' => $record->brand->id, 'slug' => \Illuminate\Support\Str::slug($record->brand->name)]) }}">{{ $record->brand->name }}</a>
                        @endif
                        @if(!empty($record->internal_id))
                            <span class="pdp-sku">SKU {{ $record->internal_id }}</span>
                        @endif
                    </div>
                    @endif

                    <h1 class="product-title pdp-title">{{ $record->description }}</h1>

                    <div class="pdp-score" v-if="socialProofConfig.sp_rating" v-cloak>
                        <span class="pdp-score-stars" aria-hidden="true"><svg class="pdp-star" viewBox="0 0 24 24" width="15" height="15" fill="currentColor" aria-hidden="true"><path d="M8.243 7.34l-6.38 .925l-.113 .023a1 1 0 0 0 -.44 1.684l4.622 4.499l-1.09 6.355l-.013 .11a1 1 0 0 0 1.464 .944l5.706 -3l5.693 3l.1 .046a1 1 0 0 0 1.352 -1.1l-1.091 -6.355l4.624 -4.5l.078 -.085a1 1 0 0 0 -.633 -1.62l-6.38 -.926l-2.852 -5.78a1 1 0 0 0 -1.794 0l-2.853 5.78z"/></svg><svg class="pdp-star" viewBox="0 0 24 24" width="15" height="15" fill="currentColor" aria-hidden="true"><path d="M8.243 7.34l-6.38 .925l-.113 .023a1 1 0 0 0 -.44 1.684l4.622 4.499l-1.09 6.355l-.013 .11a1 1 0 0 0 1.464 .944l5.706 -3l5.693 3l.1 .046a1 1 0 0 0 1.352 -1.1l-1.091 -6.355l4.624 -4.5l.078 -.085a1 1 0 0 0 -.633 -1.62l-6.38 -.926l-2.852 -5.78a1 1 0 0 0 -1.794 0l-2.853 5.78z"/></svg><svg class="pdp-star" viewBox="0 0 24 24" width="15" height="15" fill="currentColor" aria-hidden="true"><path d="M8.243 7.34l-6.38 .925l-.113 .023a1 1 0 0 0 -.44 1.684l4.622 4.499l-1.09 6.355l-.013 .11a1 1 0 0 0 1.464 .944l5.706 -3l5.693 3l.1 .046a1 1 0 0 0 1.352 -1.1l-1.091 -6.355l4.624 -4.5l.078 -.085a1 1 0 0 0 -.633 -1.62l-6.38 -.926l-2.852 -5.78a1 1 0 0 0 -1.794 0l-2.853 5.78z"/></svg><svg class="pdp-star" viewBox="0 0 24 24" width="15" height="15" fill="currentColor" aria-hidden="true"><path d="M8.243 7.34l-6.38 .925l-.113 .023a1 1 0 0 0 -.44 1.684l4.622 4.499l-1.09 6.355l-.013 .11a1 1 0 0 0 1.464 .944l5.706 -3l5.693 3l.1 .046a1 1 0 0 0 1.352 -1.1l-1.091 -6.355l4.624 -4.5l.078 -.085a1 1 0 0 0 -.633 -1.62l-6.38 -.926l-2.852 -5.78a1 1 0 0 0 -1.794 0l-2.853 5.78z"/></svg><svg class="pdp-star" viewBox="0 0 24 24" width="15" height="15" fill="currentColor" aria-hidden="true"><path d="M8.243 7.34l-6.38 .925l-.113 .023a1 1 0 0 0 -.44 1.684l4.622 4.499l-1.09 6.355l-.013 .11a1 1 0 0 0 1.464 .944l5.706 -3l5.693 3l.1 .046a1 1 0 0 0 1.352 -1.1l-1.091 -6.355l4.624 -4.5l.078 -.085a1 1 0 0 0 -.633 -1.62l-6.38 -.926l-2.852 -5.78a1 1 0 0 0 -1.794 0l-2.853 5.78z"/></svg></span>
                        <span class="pdp-score-value">5.0</span>
                        <span class="pdp-score-count"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20l-3 -3h-2a3 3 0 0 1 -3 -3v-6a3 3 0 0 1 3 -3h10a3 3 0 0 1 3 3v6a3 3 0 0 1 -3 3h-2l-3 3z"/></svg>@{{ sp_rating_count }} opiniones</span>
                    </div>
                </div>

                @if($storefront_show_prices ?? true)
                <div class="pdp-amount-block" v-cloak>
                    <div class="pdp-amount-row">
                        <span class="pdp-amount">@{{ product.currency_type_symbol }} @{{ Number(activeOfferPrice).toFixed(2) }}</span>
                        <template v-if="discountPercent">
                            <span class="pdp-amount-old">@{{ product.currency_type_symbol }} @{{ Number(compareAtPrice).toFixed(2) }}</span>
                            <span class="pdp-amount-off">-@{{ discountPercent }}%</span>
                        </template>
                    </div>

                    <div class="pdp-timer" v-if="offerExpiresAt && !sp_countdown_ended">
                        <span class="pdp-timer-label"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>La oferta termina en</span>
                        <span class="pdp-timer-clock">
                            <span class="pdp-timer-unit"><b>@{{ countdown.days }}</b><small>días</small></span>
                            <span class="pdp-timer-unit"><b>@{{ countdown.hours }}</b><small>hrs</small></span>
                            <span class="pdp-timer-unit"><b>@{{ countdown.minutes }}</b><small>min</small></span>
                            <span class="pdp-timer-unit"><b>@{{ countdown.seconds }}</b><small>seg</small></span>
                        </span>
                    </div>
                </div>
                @endif

                @if(!empty($record->variation_selector))
                <div class="variation-selector">
                    <div v-for="variable in variationSelector.variables"
                         :key="'variation-group-' + variable.id"
                         class="variation-group">
                        <span class="variation-group-name">@{{ variable.name }}</span>
                        <div class="variation-options">
                            <button type="button"
                                    v-for="value in variable.values"
                                    :key="'variation-value-' + value.id"
                                    class="variation-chip"
                                    :class="{active: isVariationValueSelected(variable.id, value.id)}"
                                    :disabled="!variationValueAvailable(variable.id, value.id)"
                                    @click.prevent="selectVariationValue(variable.id, value.id)">
                                <span v-if="variable.value_type === 'color' && value.color"
                                      class="variation-swatch"
                                      :style="{background: value.color}"></span>@{{ value.value }}
                            </button>
                        </div>
                    </div>
                </div>
                @endif

                <dl class="pdp-meta">
                    @if ($record->category && $record->category->name)
                    <div class="pdp-meta-item">
                        <dt>Categoría</dt>
                        <dd><a href="{{ route('tenant.ecommerce.category', \Illuminate\Support\Str::slug($record->category->name, '-')) }}">{{ $record->category->name }}</a></dd>
                    </div>
                    @endif
                    @if (!empty($record->model))
                    <div class="pdp-meta-item">
                        <dt>Colección</dt>
                        <dd>{{ $record->model }}</dd>
                    </div>
                    @endif
                    <div class="pdp-meta-item">
                        <dt>Disponibilidad</dt>
                        <dd>
                            @if($record->stock > 0)
                                <span class="pdp-avail pdp-avail--in">En stock</span>
                                <span class="pdp-avail-qty">{{ number_format($record->stock, 0) }} unid.</span>
                                <span class="pdp-avail-low" v-if="socialProofConfig.sp_stock_alert && stock > 0 && stock <= stockThreshold" v-cloak>últimas unidades</span>
                            @else
                                <span class="pdp-avail pdp-avail--out">Sin stock</span>
                            @endif
                        </dd>
                    </div>
                </dl>

                @if(filled(strip_tags($record->name)) || $hasSpecs)
                <div class="pdp-description product-description-wrapper">
                    @if(filled(strip_tags($record->name)))
                    <div id="productShortDescription" class="product-description-clamp">
                        {!! $record->name !!}
                    </div>
                    @endif

                    @if($hasSpecs)
                    <div id="productSpecsInline" class="pdp-specs-inline" hidden>
                        <h2 class="pdp-specs-title">Ficha técnica</h2>
                        @if(filled($record->technical_specifications))
                            <p class="pdp-specs-lead">{{ $record->technical_specifications }}</p>
                        @endif
                        @if($productSpecs->isNotEmpty())
                        <dl class="pdp-specs">
                            @foreach($productSpecs as $label => $value)
                                <div class="pdp-spec">
                                    <dt>{{ $label }}</dt>
                                    <dd>{{ $value }}</dd>
                                </div>
                            @endforeach
                        </dl>
                        @endif
                    </div>
                    @endif

                    @php
                        // Sin descripción, "Ver todo" no dice nada: el botón nombra lo que abre.
                        $toggleShow = filled(strip_tags($record->name)) ? 'Ver todo' : 'Ver ficha técnica';
                        $toggleHide = filled(strip_tags($record->name)) ? 'Ver menos' : 'Ocultar ficha técnica';
                    @endphp
                    <a href="javascript:void(0);"
                       id="toggleProductDescription"
                       class="product-description-toggle"
                       data-label-show="{{ $toggleShow }}"
                       data-label-hide="{{ $toggleHide }}"
                       style="display:none;">
                        {{ $toggleShow }}
                    </a>
                </div>
                @endif

                @if(!empty($record->attributes))
                <ul class="pdp-extra">
                    @foreach($record->attributes as $at)
                        <li><span class="pdp-extra-label">{{ $at->description }}</span><span class="pdp-extra-value">{{ $at->value }}</span></li>
                    @endforeach
                </ul>
                @endif

                <p class="pdp-activity" v-if="hasActivity" v-cloak>
                    <span class="pdp-live-dot" aria-hidden="true"></span>
                    <span class="pdp-activity-item" v-if="socialProofConfig.sp_views_count"><strong v-text="sp_viewers"></strong> viendo ahora</span>
                    <span class="pdp-activity-sep" v-if="socialProofConfig.sp_views_count && socialProofConfig.sp_purchase_count">·</span>
                    <span class="pdp-activity-item" v-if="socialProofConfig.sp_purchase_count">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 7h12l-1 13H7L6 7z"/><path d="M9 7V5a3 3 0 0 1 6 0v2"/></svg><strong v-text="sp_purchases"></strong> compras esta semana
                    </span>
                </p>

                <div class="pdp-actions product-action product-all-icons">
                    <div class="pdp-buy">
                        <div class="quantity-container pdp-qty">
                            <button v-if="quantity <= 1 && getCartQuantity(product.id)" @click.stop.prevent="removeFromCart(product)" title="Quitar del carrito">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 7l16 0" /><path d="M10 11l0 6" /><path d="M14 11l0 6" /><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" /><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" /></svg>
                            </button>
                            <button
                                @click.stop.prevent="decrementQuantity(product)"
                                v-if="!getCartQuantity(product.id) || quantity > 1"
                                title="Disminuir cantidad">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l14 0" /></svg>
                            </button>
                            <input type="number" class="input-quantity" v-model.number="quantity" min="1" @change="onQuantityInput(product)" aria-label="Cantidad">
                            <button @click.stop.prevent="incrementQuantity(product)" title="Aumentar cantidad">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
                            </button>
                        </div>

                        <button class="paction add-cart pdp-cta" @click.stop.prevent="addOrUpdateCart(product)">
                            <span v-if="cartQuantity">@{{ cartQuantity }} @{{ cartQuantity === 1 ? 'producto añadido' : 'productos añadidos' }}</span>
                            <span v-else>Agregar a Carrito</span>
                        </button>
                    </div>

                    @php
                        $showWhatsapp = ($configurationModel->enable_whatsapp ?? false) && !empty($phoneWhatsapp);
                    @endphp
                    @if($showWhatsapp)
                        @php
                            $waPhoneRaw = preg_replace('/\D+/', '', $phoneWhatsapp);
                            $waPhone = (strlen($waPhoneRaw) == 9 && str_starts_with($waPhoneRaw, '9')) ? '51'.$waPhoneRaw : $waPhoneRaw;
                            $waText = rawurlencode(
                                ($storefront_show_prices ?? true)
                                    ? "Buenas, deseo consultar acerca del producto *{$record->description}*, con precio de {$record->currency_type['symbol']}{$record->sale_unit_price}. ¿Podrían brindarme más información?"
                                    : "Buenas, deseo consultar acerca del producto *{$record->description}*. ¿Podrían brindarme más información?"
                            );
                            $waLink = "https://wa.me/{$waPhone}?text={$waText}";
                        @endphp
                        <a href="{{ $waLink }}" class="btn-whatsapp pdp-whatsapp" target="_blank" rel="noopener" title="Consultar por WhatsApp">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 21l1.65 -3.8a9 9 0 1 1 3.4 2.9l-5.05 .9" /><path d="M9 10a.5 .5 0 0 0 1 0v-1a.5 .5 0 0 0 -1 0v1a5 5 0 0 0 5 5h1a.5 .5 0 0 0 0 -1h-1a.5 .5 0 0 0 0 1" /></svg>
                            <span>Consultar por WhatsApp</span>
                        </a>
                    @endif
                </div><!-- End .pdp-actions -->
            </div><!-- End .product-single-details -->

            <div class="pdp-assurances">
                <div id="product-trust-badges"></div>
            </div>
        </div><!-- End .col-lg-5 -->
    </div><!-- End .row -->
</div><!-- End .product-single-container -->

@if($record->components->isNotEmpty())
<section class="pack-components mb-4" aria-labelledby="pack-components-title">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <p class="text-muted text-uppercase mb-1" style="font-size: 12px; letter-spacing: .08em;">Pack compuesto</p>
            <h2 id="pack-components-title" class="mb-0">Este pack incluye</h2>
        </div>
        <span class="badge badge-light">{{ $record->components->count() }} productos</span>
    </div>
    <div class="row">
        @foreach($record->components as $component)
            @php
                $componentImage = ($component->image && $component->image !== 'imagen-no-disponible.jpg')
                    ? asset('storage/uploads/items/'.$component->image)
                    : $defaultImagePath;
            @endphp
            <article class="col-12 col-sm-6 mb-3">
                <div class="d-flex h-100 p-3 border rounded bg-white">
                    <img src="{{ $componentImage }}" alt="{{ $component->name }}"
                         class="mr-3 rounded" style="width: 88px; height: 88px; object-fit: contain;">
                    <div class="flex-grow-1">
                        <div class="font-weight-bold mb-1">{{ $component->name }}</div>
                        <div class="text-primary font-weight-bold mb-1">
                            {{ rtrim(rtrim(number_format($component->quantity, 2, '.', ''), '0'), '.') }} unidad(es)
                        </div>
                        @if($component->description)
                            <p class="text-muted mb-0" style="line-height: 1.4;">{{ strip_tags($component->description) }}</p>
                        @endif
                    </div>
                </div>
            </article>
        @endforeach
    </div>
</section>
@endif

<div id="product-frequently-bought" class="mt-4 mb-2"
     data-item-id="{{ $record->id }}"></div>

@endsection

<style>
    .variation-selector { margin: 10px 0 6px; }
    .variation-group { margin-bottom: 10px; }
    .variation-group-name { display: block; font-weight: 600; margin-bottom: 6px; }
    .variation-chip {
        border: 1px solid #d7dae3; border-radius: 20px; padding: 6px 14px;
        background: #fff; margin: 0 6px 6px 0; cursor: pointer; font-size: 13px;
    }
    .variation-chip.active { border-color: #1b2653; color: #1b2653; font-weight: 700; box-shadow: inset 0 0 0 1px #1b2653; }
    .variation-chip:disabled { opacity: .35; cursor: not-allowed; }
    .variation-swatch {
        display: inline-block; width: 12px; height: 12px; border-radius: 50%;
        margin-right: 5px; vertical-align: middle; border: 1px solid rgba(0,0,0,.2);
    }
</style>

@push('scripts')¿
<script>
window.__socialProofBoot = {
    itemId: {{ (int) $record->id }},
    trustBadgesEnabled: {{ ($trustBadgesEnabled ?? true) ? 'true' : 'false' }},
    trustBadges: @json($trustBadges ?? []),
    fbtLimit: 8,
    showFbtCount: false
};
</script>
@vite('modules/Ecommerce/Resources/assets/js/frontend/product-social-app.js')
<script>
window.__socialProofBoot = {
    itemId: {{ (int) $record->id }},
    trustBadgesEnabled: {{ ($trustBadgesEnabled ?? true) ? 'true' : 'false' }},
    trustBadges: @json($trustBadges ?? []),
    fbtLimit: 8,
    showFbtCount: false
};
</script>
@vite('modules/Ecommerce/Resources/assets/js/frontend/product-social-app.js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('product-detail-vue')) {
        const viewsMin = {{ $activeCampaign ? (int) $activeCampaign->sp_views_min : 0 }};
        const viewsMax = {{ $activeCampaign ? (int) $activeCampaign->sp_views_max : 0 }};
        const purchaseMin = {{ $activeCampaign ? (int) $activeCampaign->sp_purchase_min : 0 }};
        const purchaseMax = {{ $activeCampaign ? (int) $activeCampaign->sp_purchase_max : 0 }};
        const viewsSpan = Math.max(1, viewsMax - viewsMin + 1);
        const purchaseSpan = Math.max(1, purchaseMax - purchaseMin + 1);

        new Vue({
            el: '#product-detail-vue',
            data: {
                product: {
                    id: {{ $record->id }},
                    description: @json($record->description),
                    sale_unit_price: {{ $record->sale_unit_price }},
                    sale_unit_price_display: @json($record->sale_unit_price),
                    image_small: @json($record->image ?? 'imagen-no-disponible.jpg'),
                    image: @json($record->image ?? 'imagen-no-disponible.jpg'),
                    sale_affectation_igv_type_id: @json($record->sale_affectation_igv_type_id ?? '10'),
                    currency_type_id: @json($record->currency_type_id ?? 'PEN'),
                    currency_type_symbol: @json($record->currency_type['symbol'] ?? 'S/'),
                    unit_type_id: @json($record->unit_type_id ?? 'NIU'),
                    internal_id: @json($record->internal_id ?? ''),
                    original_price: {{ number_format((float) $record->sale_unit_price, 2, '.', '') }},
                    compare_at_price: {{ $compareAtPrice !== null ? number_format((float) $compareAtPrice, 2, '.', '') : 'null' }},
                    discount_campaign_id: {{ $campaignPricing['discount_campaign_id'] ?: 'null' }},
                    discount_campaign_name: @json($campaignPricing['discount_campaign_name']),
                    campaign_discount_percent: {{ number_format((float) $campaignPricing['real_discount_percentage'], 2, '.', '') }},
                    campaign_discount_embedded: {{ $campaignPricing['has_real_discount'] ? 'true' : 'false' }},
                },
                cartQuantities: {},
                quantity: 1,
                stock: {{ (float) $record->stock }},
                stockThreshold: {{ (int) $stockThreshold }},
                hasActiveOffer: {{ $hasActiveOffer ? 'true' : 'false' }},
                activeOfferPrice: {{ number_format((float) $activeOfferPrice, 2, '.', '') }},
                compareAtPrice: {{ $compareAtPrice !== null ? number_format((float) $compareAtPrice, 2, '.', '') : 'null' }},
                offerExpiresAt: {{ $offerExpiresAt ? (int) \Carbon\Carbon::parse($offerExpiresAt)->getTimestamp() : 'null' }},
                socialProofConfig: {
                    sp_countdown: {{ ($activeCampaign && $activeCampaign->sp_countdown) ? 'true' : 'false' }},
                    sp_discount_price: {{ ($activeCampaign && $activeCampaign->sp_discount_price) ? 'true' : 'false' }},
                    sp_purchase_count: {{ ($activeCampaign && $activeCampaign->sp_purchase_count) ? 'true' : 'false' }},
                    sp_views_count: {{ ($activeCampaign && $activeCampaign->sp_views_count) ? 'true' : 'false' }},
                    sp_stock_alert: {{ ($activeCampaign && $activeCampaign->sp_stock_alert) ? 'true' : 'false' }},
                    sp_rating: {{ ($activeCampaign && $activeCampaign->sp_rating) ? 'true' : 'false' }}
                },
                sp_viewers: Math.floor(Math.random() * viewsSpan) + viewsMin,
                sp_purchases: Math.floor(Math.random() * purchaseSpan) + purchaseMin,
                sp_rating_count: Math.floor(Math.random() * (120 - 45 + 1)) + 45,
                sp_countdown_text: 'Cargando...',
                countdown: { days: '00', hours: '00', minutes: '00', seconds: '00' },
                sp_countdown_ended: false,
                _countdownTimer: null,
                _viewersTimer: null,
                variationSelector: @json($record->variation_selector ?? null),
                selectedVariationValues: {},
            },
            computed: {
                // La línea de actividad solo aparece si la campaña habilitó alguna
                // de las dos métricas que la componen.
                hasActivity() {
                    const config = this.socialProofConfig;
                    return Boolean(config.sp_views_count || config.sp_purchase_count);
                },
                // Cantidad de este producto que hay en el carrito; de aquí sale la
                // etiqueta del botón.
                cartQuantity() {
                    return this.cartQuantities[this.product.id] || 0;
                },
                discountPercent() {
                    if (!this.compareAtPrice || this.compareAtPrice <= this.activeOfferPrice) return 0;
                    return Math.round((1 - this.activeOfferPrice / this.compareAtPrice) * 100);
                },
            },
            created() {
                this.loadCartQuantities();
                window.addEventListener('productAddedToCart', this.loadCartQuantities);
                this.startCountdown();
                this.startViewersDrift();
            },
            beforeDestroy() {
                if (this._countdownTimer) clearInterval(this._countdownTimer);
                if (this._viewersTimer) clearInterval(this._viewersTimer);
                window.removeEventListener('productAddedToCart', this.loadCartQuantities);
                this.initVariationSelector();
            },
            watch: {
                cartQuantities: {
                    handler(val) {
                        if (this.getCartQuantity(this.product.id)) {
                            this.quantity = this.getCartQuantity(this.product.id);
                        } else {
                            this.quantity = 1;
                        }
                    },
                    deep: true
                }
            },
            methods: {
                startCountdown() {
                    if (!this.offerExpiresAt) {
                        return;
                    }
                    let endMs = Number(this.offerExpiresAt) * 1000;
                    const dayMs = 24 * 60 * 60 * 1000;
                    const tick = () => {
                        // Evergreen: si ya pasó la hora, suma +1 día (misma hora) y sigue.
                        while (endMs <= Date.now()) {
                            endMs += dayMs;
                        }
                        const distance = endMs - Date.now();
                        const days = Math.floor(distance / dayMs);
                        const hours = Math.floor((distance % dayMs) / (1000 * 60 * 60));
                        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                        const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                        this.sp_countdown_ended = false;

                        const pad = value => String(value).padStart(2, '0');
                        this.countdown = {
                            days: pad(days),
                            hours: pad(hours),
                            minutes: pad(minutes),
                            seconds: pad(seconds),
                        };
                        this.sp_countdown_text = days + 'd ' + pad(hours) + 'h ' + pad(minutes) + 'm ' + pad(seconds) + 's';
                    };
                    tick();
                    this._countdownTimer = setInterval(tick, 1000);
                },
                startViewersDrift() {
                    if (!this.socialProofConfig.sp_views_count) {
                        return;
                    }
                    this._viewersTimer = setInterval(() => {
                        const delta = Math.random() > 0.5 ? 1 : -1;
                        this.sp_viewers = Math.max(viewsMin, this.sp_viewers + delta);
                        if (this.sp_viewers > viewsMax) this.sp_viewers = viewsMax;
                    }, 8000);
                },
                initVariationSelector() {
                    if (!this.variationSelector) return;
                    const current = this.variationSelector.current_value_ids || [];
                    this.variationSelector.variables.forEach(variable => {
                        const match = variable.values.find(value => current.indexOf(value.id) !== -1);
                        if (match) this.$set(this.selectedVariationValues, variable.id, match.id);
                    });
                },
                isVariationValueSelected(variableId, valueId) {
                    return this.selectedVariationValues[variableId] === valueId;
                },
                variationValueAvailable(variableId, valueId) {
                    if (!this.variationSelector) return false;
                    // alguna combinación incluye este valor junto a lo elegido en las demás variables
                    return this.variationSelector.combinations.some(combination => {
                        if (combination.value_ids.indexOf(valueId) === -1) return false;
                        return Object.keys(this.selectedVariationValues).every(otherVariableId => {
                            if (String(otherVariableId) === String(variableId)) return true;
                            return combination.value_ids.indexOf(this.selectedVariationValues[otherVariableId]) !== -1;
                        });
                    });
                },
                selectVariationValue(variableId, valueId) {
                    if (this.isVariationValueSelected(variableId, valueId)) return;
                    this.$set(this.selectedVariationValues, variableId, valueId);

                    const ids = Object.values(this.selectedVariationValues).map(Number).sort((a, b) => a - b);
                    if (ids.length !== this.variationSelector.variables.length) return;

                    const match = this.variationSelector.combinations.find(combination =>
                        combination.value_ids.length === ids.length &&
                        combination.value_ids.every((id, index) => id === ids[index])
                    );
                    if (match && match.url) {
                        window.location.href = match.url;
                    }
                },
                // Un producto nuevo entra con la cantidad elegida; si ya está en el
                // carrito, cada clic suma una unidad más (1, 2, 3...).
                addOrUpdateCart(item) {
                    const enCarrito = this.getCartQuantity(item.id);
                    const objetivo = this.limitToStock(enCarrito ? this.quantity + 1 : this.quantity);

                    this.quantity = objetivo;
                    this.writeCart(item, objetivo, { mode: enCarrito ? 'exists' : 'added' });
                },

                /**
                 * Guarda la cantidad en el carrito y refresca el contador del header.
                 * En modo silencioso no abre el modal de confirmación: se usa desde
                 * los botones +/-, donde un modal por cada pulsación sobraría.
                 */
                writeCart(item, quantity, options) {
                    options = options || {};

                    const payload = Object.assign({}, item, {
                        sale_unit_price: this.activeOfferPrice,
                        original_price: parseFloat(item.sale_unit_price),
                        has_discount: this.hasActiveOffer,
                        quantity: quantity,
                        stock: Math.round(this.stock),
                    });

                    if (!options.silent && typeof cartAddOrUpdateItem === 'function') {
                        cartAddOrUpdateItem(payload, {
                            quantity: quantity,
                            replaceQuantity: true,
                            mode: options.mode,
                        });
                    } else if (typeof cartReadCart === 'function' && typeof cartWriteCart === 'function') {
                        const array = cartReadCart();
                        const found = array.find(x => x.id == item.id);
                        if (found) {
                            found.quantity = quantity;
                            found.sale_unit_price = payload.sale_unit_price;
                        } else {
                            array.push(payload);
                        }
                        cartWriteCart(array);
                        if (typeof cartRefreshHeader === 'function') {
                            cartRefreshHeader();
                        }
                    } else {
                        let array = localStorage.getItem('products_cart');
                        array = array ? JSON.parse(array) : [];
                        const found = array.find(x => x.id == item.id);
                        if (found) {
                            found.quantity = quantity;
                            found.sale_unit_price = payload.sale_unit_price;
                        } else {
                            array.push(payload);
                        }
                        localStorage.setItem('products_cart', JSON.stringify(array));
                    }

                    this.cartQuantities = Object.assign({}, this.cartQuantities, { [item.id]: quantity });
                    window.dispatchEvent(new Event('productAddedToCart'));
                },

                // Mientras el producto ya esté en el carrito, el selector lo edita:
                // así el botón, el header y el checkout muestran siempre lo mismo.
                syncCart(item) {
                    if (!this.getCartQuantity(item.id)) return;
                    this.writeCart(item, this.quantity, { silent: true });
                },

                limitToStock(value) {
                    const max = Math.round(this.stock);
                    if (value < 1) return 1;
                    return (max > 0 && value > max) ? max : value;
                },

                getCartQuantity(id) {
                    return this.cartQuantities[id] || 0;
                },
                onQuantityInput(item) {
                    this.quantity = this.limitToStock(this.quantity || 1);
                    this.syncCart(item);
                },
                loadCartQuantities() {
                    let array = localStorage.getItem('products_cart');
                    array = array ? JSON.parse(array) : [];
                    let obj = {};
                    array.forEach(function(item) {
                        obj[item.id] = item.quantity || 1;
                    });
                    this.cartQuantities = obj;
                },
                incrementQuantity(item) {
                    this.quantity = this.limitToStock(this.quantity + 1);
                    this.syncCart(item);
                },
                decrementQuantity(item) {
                    if (this.quantity <= 1) return;
                    this.quantity--;
                    this.syncCart(item);
                },
                removeFromCart(item) {
                    let array = localStorage.getItem('products_cart');
                    array = array ? JSON.parse(array) : [];
                    array = array.filter(x => x.id != item.id);
                    localStorage.setItem('products_cart', JSON.stringify(array));
                    this.$set(this.cartQuantities, item.id, 0);
                    window.dispatchEvent(new Event('productAddedToCart'));
                }
            }
        });
    }

    const description = document.getElementById('productShortDescription');
    const specs = document.getElementById('productSpecsInline');
    const toggleBtn = document.getElementById('toggleProductDescription');

    if (toggleBtn) {
        const isOverflowing = description
            ? description.scrollHeight > description.clientHeight + 2
            : false;

        // El botón también aparece cuando no hay descripción larga pero sí ficha técnica.
        if (isOverflowing || specs) {
            toggleBtn.style.display = 'inline-block';
        }

        toggleBtn.addEventListener('click', function() {
            const expanded = !toggleBtn.classList.contains('is-expanded');

            toggleBtn.classList.toggle('is-expanded', expanded);
            if (description) description.classList.toggle('expanded', expanded);
            if (specs) specs.hidden = !expanded;

            toggleBtn.textContent = expanded
                ? (toggleBtn.dataset.labelHide || 'Ver menos')
                : (toggleBtn.dataset.labelShow || 'Ver todo');
        });
    }
});
</script>
@endpush
