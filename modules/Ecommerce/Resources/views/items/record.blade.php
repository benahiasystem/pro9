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

<div class="product-single-container product-single-default pdp">
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

                    <div class="pdp-rating" v-if="socialProofConfig.sp_rating" v-cloak>
                        <span class="pdp-rating-stars" aria-hidden="true">★★★★★</span>
                        <span class="pdp-rating-score">5.0</span>
                        <span class="pdp-rating-count">(@{{ sp_rating_count }} opiniones)</span>
                    </div>
                </div>

                @if($storefront_show_prices ?? true)
                <div class="pdp-price-block" v-cloak>
                    <div class="pdp-price-row">
                        <span class="pdp-price">@{{ product.currency_type_symbol }} @{{ Number(activeOfferPrice).toFixed(2) }}</span>
                        <template v-if="discountPercent">
                            <span class="pdp-price-old">@{{ product.currency_type_symbol }} @{{ Number(compareAtPrice).toFixed(2) }}</span>
                            <span class="pdp-price-off">-@{{ discountPercent }}%</span>
                        </template>
                    </div>
                    @if($record->has_igv)
                        <p class="pdp-price-note">Precio con IGV incluido</p>
                    @endif
                </div>
                @endif

                <div class="pdp-signals" v-if="hasSignals" v-cloak>
                    <div class="pdp-signal pdp-signal--deadline" v-if="offerExpiresAt && !sp_countdown_ended">
                        <span class="pdp-signal-icon"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
                        <span class="pdp-signal-text">La oferta termina en <strong>@{{ sp_countdown_text }}</strong></span>
                    </div>

                    <div class="pdp-signal pdp-signal--stock" v-if="socialProofConfig.sp_stock_alert && stock > 0 && stock <= stockThreshold">
                        <span class="pdp-signal-icon"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3c1.5 3 4.5 4.5 4.5 8a4.5 4.5 0 1 1-9 0c0-1.4.5-2.4 1.3-3.3.3 1 .9 1.6 1.7 1.8C10 7.5 10.6 5 12 3z"/></svg></span>
                        <span class="pdp-signal-text">Últimas <strong>@{{ Math.round(stock) }} unidades</strong> en stock</span>
                    </div>

                    <div class="pdp-signal" v-if="socialProofConfig.sp_views_count">
                        <span class="pdp-signal-icon"><span class="pdp-live-dot"></span></span>
                        <span class="pdp-signal-text"><strong v-text="sp_viewers"></strong> personas viendo este producto ahora</span>
                    </div>

                    <div class="pdp-signal" v-if="socialProofConfig.sp_purchase_count">
                        <span class="pdp-signal-icon"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 7h12l-1 13H7L6 7z"/><path d="M9 7V5a3 3 0 0 1 6 0v2"/></svg></span>
                        <span class="pdp-signal-text"><strong v-text="sp_purchases"></strong> compras en los últimos 7 días</span>
                    </div>
                </div>

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
                                <span class="pdp-stock pdp-stock--in">En stock</span>
                                <span class="pdp-stock-qty">{{ number_format($record->stock, 0) }} unid.</span>
                            @else
                                <span class="pdp-stock pdp-stock--out">Sin stock</span>
                            @endif
                        </dd>
                    </div>
                </dl>

                @if(filled(strip_tags($record->name)))
                <div class="pdp-description product-description-wrapper">
                    <div id="productShortDescription" class="product-description-clamp">
                        {!! $record->name !!}
                    </div>

                    <a href="javascript:void(0);"
                       id="toggleProductDescription"
                       class="product-description-toggle"
                       style="display:none;">
                        Ver todo
                    </a>
                </div>
                @endif

                @if(!empty($record->attributes))
                <ul class="pdp-attrs">
                    @foreach($record->attributes as $at)
                        <li><span class="pdp-attr-label">{{ $at->description }}</span><span class="pdp-attr-value">{{ $at->value }}</span></li>
                    @endforeach
                </ul>
                @endif

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
                            <input type="number" class="input-quantity" v-model.number="quantity" min="1" @change="onQuantityInput" aria-label="Cantidad">
                            <button @click.stop.prevent="incrementQuantity(product)" title="Aumentar cantidad">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
                            </button>
                        </div>

                        <button class="paction add-cart pdp-cta" @click.stop.prevent="addOrUpdateCart(product)">
                            <span v-if="getCartQuantity(product.id)">Actualizar cantidad</span>
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
    $hasSpecsTab = filled($record->technical_specifications) || $productSpecs->isNotEmpty();
@endphp

<div class="product-single-tabs pdp-tabs">
    <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active"  id="product-tab-desc" data-toggle="tab" href="#product-desc-content" role="tab"
                aria-controls="product-desc-content" aria-selected="true">Descripción</a>
        </li>
        @if($hasSpecsTab)
        <li class="nav-item">
            <a class="nav-link" id="product-tab-specs" data-toggle="tab" href="#product-specs-content" role="tab"
                aria-controls="product-specs-content" aria-selected="false">Ficha técnica</a>
        </li>
        @endif
        <li class="nav-item pdp-tab-reviews">
            <a class="nav-link" onclick="getRating('{{ $record->id}}')" id="product-tab-reviews" data-toggle="tab" href="#product-reviews-content" role="tab"
                aria-controls="product-reviews-content" aria-selected="false">Opiniones</a>
        </li>
        {{-- <li class="nav-item">
            <a class="nav-link" id="product-tab-especTecn" data-toggle="tab" href="#product-especTecn-content" role="tab" aria-controls="product-especTecn-content" aria-selected="true">Especificaciones Técnicas</a>
        </li> --}}
    </ul>
    <div class="tab-content">
        <div class="tab-pane fade show active" id="product-desc-content" role="tabpanel"
            aria-labelledby="product-tab-desc">
            <div class="product-desc-content">
                @if(filled($record->name))
                    {!! $record->name !!}
                @else
                    <p class="text-muted mb-0">Este producto todavía no tiene una descripción.</p>
                @endif
            </div><!-- End .product-desc-content -->
        </div><!-- End .tab-pane -->

        @if($hasSpecsTab)
        <div class="tab-pane fade" id="product-specs-content" role="tabpanel" aria-labelledby="product-tab-specs">
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
        </div><!-- End .tab-pane -->
        @endif

        <div class="tab-pane fade" id="product-reviews-content" role="tabpanel" aria-labelledby="product-tab-reviews">
            <div class="product-reviews-content">
                <div class="collateral-box">

                    <div class="page">
                        <div class="page__demo">

                            <div class="page__group">
                                <div class="rating">
                                    <input type="radio" name="rating-star2" class="rating__control" id="rc6" onclick="sendRating(1,{{$record->id}})">
                                    <input type="radio" name="rating-star2" class="rating__control" id="rc7" onclick="sendRating(2,{{$record->id}})">
                                    <input type="radio" name="rating-star2" class="rating__control" id="rc8" onclick="sendRating(3,{{$record->id}})">
                                    <input type="radio" name="rating-star2" class="rating__control" id="rc9" onclick="sendRating(4,{{$record->id}})">
                                    <input type="radio" name="rating-star2" class="rating__control" id="rc10" onclick="sendRating(5,{{$record->id}})" >
                                    <label for="rc6" class="rating__item">
                                        <svg class="rating__star">
                                            <use xlink:href="#star"></use>
                                        </svg>
                                        <span class="rating__label">1</span>
                                    </label>
                                    <label for="rc7" class="rating__item">
                                        <svg class="rating__star">
                                            <use xlink:href="#star"></use>
                                        </svg>
                                        <span class="rating__label">2</span>
                                    </label>
                                    <label for="rc8" class="rating__item">
                                        <svg class="rating__star">
                                            <use xlink:href="#star"></use>
                                        </svg>
                                        <span class="rating__label">3</span>
                                    </label>
                                    <label for="rc9" class="rating__item">
                                        <svg class="rating__star">
                                            <use xlink:href="#star"></use>
                                        </svg>
                                        <span class="rating__label">4</span>
                                    </label>
                                    <label for="rc10" class="rating__item">
                                        <svg class="rating__star">
                                            <use xlink:href="#star"></use>
                                        </svg>
                                        <span class="rating__label">5</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <svg xmlns="http://www.w3.org/2000/svg" style="display: none">
                        <symbol id="star" viewBox="0 0 26 28">
                            <path
                                d="M26 10.109c0 .281-.203.547-.406.75l-5.672 5.531 1.344 7.812c.016.109.016.203.016.313 0 .406-.187.781-.641.781a1.27 1.27 0 0 1-.625-.187L13 21.422l-7.016 3.687c-.203.109-.406.187-.625.187-.453 0-.656-.375-.656-.781 0-.109.016-.203.031-.313l1.344-7.812L.39 10.859c-.187-.203-.391-.469-.391-.75 0-.469.484-.656.875-.719l7.844-1.141 3.516-7.109c.141-.297.406-.641.766-.641s.625.344.766.641l3.516 7.109 7.844 1.141c.375.063.875.25.875.719z" />
                        </symbol>
                    </svg>

                </div>

            </div>
        </div>

        <div class="tab-pane fade" id="product-especTecn-content" role="tabpanel" aria-labelledby="product-tab-especTecn">
            <div class="product-especTecn-content">
                <p> {!! $record->technical_specifications !!} </p>
            </div><!-- End .product-desc-content -->
        </div><!-- End .tab-pane -->
    </div>
</div>

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
                sp_countdown_ended: false,
                _countdownTimer: null,
                _viewersTimer: null,
                variationSelector: @json($record->variation_selector ?? null),
                selectedVariationValues: {},
            },
            computed: {
                // Evita que la tarjeta de señales se dibuje vacía cuando la campaña
                // no tiene ninguna activa.
                hasSignals() {
                    const config = this.socialProofConfig;
                    return Boolean(
                        (this.offerExpiresAt && !this.sp_countdown_ended)
                        || (config.sp_stock_alert && this.stock > 0 && this.stock <= this.stockThreshold)
                        || config.sp_views_count
                        || config.sp_purchase_count
                    );
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
                        this.sp_countdown_text = days + 'd ' + hours + 'h ' + minutes + 'm ' + seconds + 's';
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
                addOrUpdateCart(item) {
                    let array = localStorage.getItem('products_cart');
                    array = array ? JSON.parse(array) : [];
                    let found = array.find(x => x.id == item.id);
                    const cartItem = {
                        ...item,
                        sale_unit_price: this.activeOfferPrice,
                        original_price: parseFloat(item.sale_unit_price),
                        has_discount: this.hasActiveOffer,
                        quantity: this.quantity,
                        stock: Math.round(this.stock),
                    };

                    if (typeof cartAddOrUpdateItem === 'function') {
                        cartAddOrUpdateItem(cartItem, {
                            quantity: this.quantity,
                            replaceQuantity: true,
                            mode: found ? 'exists' : 'added',
                        });
                        this.cartQuantities = Object.assign({}, this.cartQuantities, { [item.id]: this.quantity });
                        return;
                    }

                    const price = this.activeOfferPrice;
                    if (found) {
                        found.quantity = this.quantity;
                        found.sale_unit_price = price;
                    } else {
                        array.push({
                            ...item,
                            sale_unit_price: price,
                            quantity: this.quantity
                        });
                    }
                    localStorage.setItem('products_cart', JSON.stringify(array));
                    this.cartQuantities = Object.assign({}, this.cartQuantities, { [item.id]: this.quantity });
                    window.dispatchEvent(new Event('productAddedToCart'));
                },
                getCartQuantity(id) {
                    return this.cartQuantities[id] || 0;
                },
                onQuantityInput() {
                    if (this.quantity < 1) this.quantity = 1;
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
                    this.quantity++;
                },
                decrementQuantity(item) {
                    if (this.quantity > 1) this.quantity--;
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
    const toggleBtn = document.getElementById('toggleProductDescription');

    if (description && toggleBtn) {
        const isOverflowing = description.scrollHeight > description.clientHeight + 2;

        if (isOverflowing) {
            toggleBtn.style.display = 'inline-block';
        }

        toggleBtn.addEventListener('click', function() {
            description.classList.toggle('expanded');

            if (description.classList.contains('expanded')) {
                toggleBtn.textContent = 'Ver menos';
            } else {
                toggleBtn.textContent = 'Ver todo';
            }
        });
    }
});
</script>
@endpush
