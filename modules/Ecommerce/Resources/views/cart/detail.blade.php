@extends('ecommerce::layouts.layout_ecommerce_cart.index')

@push('styles')
<style>
    #addressModal .modal-dialog {
        max-width: 800px;
    }

    #map {
        height: 400px;
        width: 100%;
        border-radius: 8px;
        border: 2px solid #e0e0e0;
    }

    #addressModal .modal-header {
        background-color: #f8f9fa;
        border-bottom: 2px solid #dee2e6;
    }

    #addressModal .modal-title {
        font-weight: 600;
        color: #333;
    }

    #addressModal .close {
        font-size: 1.5rem;
        font-weight: 700;
        opacity: 0.7;
        transition: opacity 0.2s;
    }

    #addressModal .close:hover {
        opacity: 1;
    }

    .btn-input-group {
        height: 26.6px !important;
        border-radius: 0px !important;
        min-width: 32px !important;
    }

    .card-body-h-auto {
        min-height: auto !important;
    }

    .collapse-arrow {
        margin-left: auto;
        transition: transform 0.25s ease;
        flex-shrink: 0;
    }

    .btn[aria-expanded="false"] .collapse-arrow {
        transform: rotate(-90deg);
    }

    /* Aceptación de términos y condiciones */
    .terms {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 14px 0 12px;
        cursor: pointer;
        user-select: none;
        line-height: 1.35;
        font-size: 13px
    }

    .terms input[type="checkbox"] {
        position: absolute;
        opacity: 0;
        width: 0;
        height: 0;
        pointer-events: none;
    }

    .terms .box {
        flex-shrink: 0;
        width: 24px;
        height: 24px;
        border-radius: 7px;
        border: 2px solid #d5dbe0;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        transition: background-color .15s ease, border-color .15s ease, transform .12s ease;
    }

    .terms .box svg {
        opacity: 0;
        transform: scale(.6);
        transition: opacity .15s ease, transform .15s ease;
    }

    .terms--checked .box svg {
        opacity: 1;
        transform: scale(1);
    }

    .terms-txt a:hover {
        text-decoration: underline;
    }

    /* Desktop - una fila */
    .items-cart {
        display: grid;
        grid-template-columns: 80px 1fr auto auto auto;
        grid-template-areas: "thumb info quantity total delete";
        align-items: center;
        gap: 10px;
        padding: 10px 0;
        border-bottom: 1px solid #eee;
    }

    .items-cart .thumb { grid-area: thumb; }
    .items-cart .info { grid-area: info; }
    .items-cart .modern-quantity-container { grid-area: quantity; }
    .items-cart .total { grid-area: total; }
    .items-cart .delete-item-btn { grid-area: delete; }

    /* Móvil - dos filas */
    @media (max-width: 576px) {
        .items-cart {
            grid-template-columns: 70px 1fr auto auto auto;
            grid-template-areas:
                "thumb info info info info"
                "thumb quantity quantity total delete";
        }
    }

    /* Guest checkout modals */
    .guest-modal .modal-content {
        border: 0;
        border-radius: 14px;
        overflow: hidden;
    }

    .guest-modal .modal-header {
        border-bottom: 1px solid #eef1f4;
        padding: 1.25rem 1.5rem 1rem;
    }

    .guest-modal .modal-title {
        font-weight: 700;
        color: #1f2937;
    }

    .guest-modal .modal-body {
        padding: 0 1.5rem 1.25rem;
        color: #4b5563;
        line-height: 1.55;
    }

    .guest-modal .modal-footer {
        border-top: 1px solid #eef1f4;
        padding: 1rem 1.5rem 1.25rem;
        gap: .5rem;
    }

    .guest-modal__intro {
        margin-bottom: 1rem;
    }

    .guest-modal__actions {
        display: flex;
        flex-direction: column;
        gap: .75rem;
    }

    .guest-modal__warning-list {
        margin: 0 0 1rem;
        padding-left: 1.15rem;
    }

    .guest-modal__warning-list li {
        margin-bottom: .45rem;
    }

    .guest-modal__check {
        display: flex;
        align-items: flex-start;
        gap: .65rem;
        margin: 0;
        cursor: pointer;
        user-select: none;
    }

    .guest-modal__check input {
        margin-top: .2rem;
    }

    .guest-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
    }

    .guest-form-grid .field-full {
        grid-column: 1 / -1;
    }

    @media (max-width: 767px) {
        .guest-form-grid {
            grid-template-columns: 1fr;
        }
    }

    .pay-method-panel {
        padding: 15px;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        margin-bottom: 12px;
        background: #fafafa;
    }

    .pay-method-panel p {
        font-size: 13px;
        color: #555;
        margin-bottom: 0;
        white-space: pre-line;
    }

    .pay-method-action {
        width: 100%;
        margin-top: 12px;
    }

    .checkout-hint {
        font-size: 13px;
        color: #666;
        text-align: center;
        margin: 0 0 8px;
        line-height: 1.4;
    }
</style>
@endpush

@section('content')

@php
    $configurationModel = \App\Models\Tenant\Configuration::first();
    $ecommerceConfiguration = $configuration ?? \App\Models\Tenant\ConfigurationEcommerce::first();
    $phoneWhatsapp = $ecommerceConfiguration->phone_whatsapp ?? $configurationModel->phone_whatsapp ?? null;
    $showWhatsapp = ($configurationModel->enable_whatsapp ?? false) && !empty($phoneWhatsapp);
    $defaultImage = $configurationModel->product_default_image ?? 'imagen-no-disponible.jpg';
    $defaultImagePath = $defaultImage === 'imagen-no-disponible.jpg'
        ? asset('logo/imagen-no-disponible.jpg')
        : asset('storage/defaults/' . $defaultImage);
    $itemsBasePath = asset('storage/uploads/items');
    $googleMapsApiKey = app(Modules\Ecommerce\Http\Controllers\EcommerceController::class)->getGoogleMaps();
    $globalDiscountTypeId = $global_discount_type_id ?? null;
@endphp
<h2 class="my-4 mt-4" style="font-weight: 900;">Finalizar compra</h2>
<div class="row" id="app">
    <div class="col-md-8 mb-3">
        <div class="card card-cart">
            <button type="button" class="btn btn-link btn-block text-left p-0" data-toggle="collapse" data-target="#cartCollapse" aria-expanded="true" style="text-decoration: none; display: block;">
                <div class="card-header d-flex align-items-center bg-white border-bottom-0 card-cart-header" style="cursor: pointer;">
                    <span class="icon-card cart">
                        <svg clip-rule="evenodd"
                            fill-rule="evenodd"
                            height="24"
                            stroke-linejoin="round"
                            stroke-miterlimit="2"
                            viewBox="0 0 512 512" width="24" xmlns="http://www.w3.org/2000/svg" id="fi_4893746">
                            <path d="m211.892 383.468c24.344 0 44.108 19.764 44.108 44.108s-19.764 44.108-44.108 44.108-44.108-19.764-44.108-44.108 19.764-44.108 44.108-44.108zm176.22 0c24.344 0 44.108 19.764 44.108 44.108s-19.764 44.108-44.108 44.108-44.108-19.764-44.108-44.108 19.764-44.108 44.108-44.108zm-288.464-273.226s63.534 222.705 63.534 222.705c6.591 23.103 27.703 39.034 51.727 39.034h157.478c33.502 0 61.98-24.47 67.023-57.59 4.821-31.664 11.838-77.75 17.065-112.081 2.869-18.84-2.626-37.994-15.046-52.449-12.42-14.454-30.529-22.769-49.586-22.769h-235.394l-8.72-30.567c-7.633-26.757-32.085-45.209-59.91-45.209-23.033 0-51.825 0-51.825 0-13.798 0-25 11.202-25 25s11.202 25 25 25h51.825c5.494 0 10.321 3.643 11.829 8.926zm71.066 66.85h221.129c4.482 0 8.741 1.956 11.663 5.355 2.921 3.4 4.213 7.905 3.539 12.337 0 0-17.066 112.081-17.066 112.081-1.323 8.693-8.798 15.116-17.592 15.116h-157.478c-1.693 0-3.181-1.122-3.645-2.751 0 0-40.55-142.138-40.55-142.138z"/>
                        </svg>
                    </span>
                    <span class="ml-2 font-weight-bold title-card">Tu carrito</span>
                    <span class="head-summary">
                        <template v-if="records.length > 0">
                            <b>@{{ records.length }} @{{ records.length === 1 ? 'producto' : 'productos' }}</b>
                            <span class="head-summary-sep">·</span>
                            <span class="head-summary-amt">S/ @{{ summary.total }}</span>
                        </template>
                        <span v-else class="head-summary-warn">Carrito vacío</span>
                    </span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="collapse-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2b2b2b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M6 9l6 6l6 -6" /></svg>
                </div>
            </button>

            <div id="cartCollapse" class="collapse show">
                <div class="card-cart-body">
                    <div v-if="records.length > 0">
                        <div v-for="(row, index) in records" class="items-cart" :key="row.id">
                            <div class="thumb">
                                <figure class="product-image-container m-0">
                                    <a href="#" class="product-image">
                                        <img class="image-product w-100" :src="(row.image && row.image !== 'imagen-no-disponible.jpg') ? '{{ $itemsBasePath }}' + '/' + row.image : '{{ $defaultImagePath }}'" :alt="row.description || 'Producto sin imagen'">
                                    </a>
                                </figure>
                            </div>

                            <div class="info">
                                <h5 class="product-title m-0">
                                    <a href="#">@{{ row.description }}</a>
                                </h5>
                                <span class="price text-muted">
                                    @{{ row.currency_type_symbol }} @{{ row.sale_unit_price }}
                                </span>
                            </div>

                            <div class="input-group input-group-sm modern-quantity-container w-auto">
                                <div class="input-group-prepend">
                                    <button class="btn btn-outline-secondary btn-input-group" type="button" @click.stop.prevent="decrementQuantity(row)">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l14 0" /></svg>
                                    </button>
                                </div>
                                <input class="input-quantity form-control text-center" :data-product="row.id" type="number" v-model.number="row.cantidad">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary btn-input-group" type="button" @click.stop.prevent="incrementQuantity(row)">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
                                    </button>
                                </div>
                            </div>

                            <strong class="total">@{{ row.currency_type_symbol }} @{{ (row.sale_unit_price * row.cantidad).toFixed(2) }}</strong>

                            <button type="button" @click="deleteItem(row.id, index)" class="btn btn-sm btn-link text-muted px-0 delete-item-btn">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 7l16 0" /><path d="M10 11l0 6" /><path d="M14 11l0 6" /><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" /><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" /></svg>
                            </button>

                        </div>
                    </div>
                    <div v-else class="cart-empty">
                        <span class="cart-empty-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6.331 8h11.339a2 2 0 0 1 1.977 2.304l-1.255 8.152a3 3 0 0 1 -2.966 2.544h-6.852a3 3 0 0 1 -2.966 -2.544l-1.255 -8.152a2 2 0 0 1 1.977 -2.304z"/><path d="M9 11v-5a3 3 0 0 1 6 0v5"/></svg>
                        </span>
                        <p class="cart-empty-title">Tu carrito está vacío</p>
                        <p class="cart-empty-text">Agrega productos para continuar con tu compra.</p>
                        <a href="/ecommerce" class="cart-empty-btn">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                            Ver productos
                        </a>
                    </div>
                </div>

                <div class="card-footer card-cart-footer border-0">
                    <div v-if="showWhatsapp && records.length > 0" class="mb-3">
                        <button type="button" @click="clickConsultWhatsappCart" class="btn btn-whatsapp w-100">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 21l1.65 -3.8a9 9 0 1 1 3.4 2.9l-5.05 .9" /><path d="M9 10a.5 .5 0 0 0 1 0v-1a.5 .5 0 0 0 -1 0v1a5 5 0 0 0 5 5h1a.5 .5 0 0 0 0 -1h-1a.5 .5 0 0 0 0 1" /></svg>
                            Consultar por WhatsApp
                        </button>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <a href="/ecommerce" class="text-muted text-capitalize">
                                <i class="fa fa-arrow-left"></i>
                                Continuar Comprando
                            </a>
                        </div>
                        <div class="col-6 text-right" v-if="records.length > 0">
                            <a href="#" @click="clearShoppingCart" class="text-danger text-capitalize">Limpiar Carrito</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-cart" v-if="records.length > 0">
            <button type="button" class="btn btn-link btn-block text-left p-0" data-toggle="collapse" data-target="#deliveryCollapse" aria-expanded="true" style="text-decoration: none; display: block;">
                <div class="card-header d-flex align-items-center bg-white border-bottom-0 card-cart-header" style="cursor: pointer;">
                    <span class="icon-card">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="24"
                            height="24"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="#2b2b2b"
                            stroke-width="1.75"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            >
                            <path d="M7 17m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                            <path d="M17 17m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                            <path d="M5 17h-2v-4m-1 -8h11v12m-4 0h6m4 0h2v-6h-8m0 -5h5l3 5" />
                            <path d="M3 9l4 0" />
                        </svg>
                    </span>
                    <span class="ml-2 font-weight-bold title-card">Datos de envio</span>
                    <span class="head-summary">
                        <template v-if="isPickupMode">
                            <b v-if="selectedPickupBranch">@{{ selectedPickupBranch.name }}</b>
                            <span v-else class="head-summary-warn">Elige sucursal</span>
                        </template>
                        <template v-else-if="form_contact.address || form_contact.telephone">
                            <span class="head-summary-addr" v-if="form_contact.address">@{{ form_contact.address }}</span>
                            <span class="head-summary-sep" v-if="form_contact.address && form_contact.telephone">·</span>
                            <b v-if="form_contact.telephone">@{{ form_contact.telephone }}</b>
                        </template>
                        <span v-else class="head-summary-warn">Falta completar</span>
                    </span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="collapse-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2b2b2b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M6 9l6 6l6 -6" /></svg>
                </div>
            </button>
            <div id="deliveryCollapse" class="collapse show">
                <div class="card-body card-body-h-auto card-cart-body ship-body">

                    {{-- Switch: Recojo en tienda (solo si está habilitado en configuración) --}}
                    <div class="pickup-switch" v-if="enableStorePickup">
                        {{-- <label class="pickup-switch-label" @click="togglePickupMode">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9 -7 9 7v11a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                            Recojo en tienda
                        </label>
                        <button type="button" class="switch" :class="{ 'switch--on': isPickupMode }" @click="togglePickupMode" :aria-pressed="isPickupMode ? 'true' : 'false'" aria-label="Activar recojo en tienda">
                            <span class="switch-knob"></span>
                        </button> --}}
                        <label
                            class="option-card send-mode"
                            :class="{ 'option-card--active': !isPickupMode }"
                        >
                            <span class="icon-delivery">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentcolor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"></path> <path d="M17 17m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"></path> <path d="M5 17h-2v-4m-1 -8h11v12m-4 0h6m4 0h2v-6h-8m0 -5h5l3 5"></path> <path d="M3 9l4 0"></path></svg>
                            </span>
                            <span class="option-card-body">
                                <strong>Envío a domicilio</strong>
                                <span class="option-card-sub">Llega a tu dirección</span>
                            </span>
                            <input
                                type="radio"
                                name="delivery_mode"
                                style="margin-left: auto"
                                :checked="!isPickupMode"
                                @change="setPickupMode(false)"
                            >
                        </label>
                        <label
                            class="option-card send-mode"
                            :class="{ 'option-card--active': isPickupMode }"
                        >
                            <span class="icon-delivery">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l1-5h16l1 5"></path><path d="M4 9v11a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V9"></path><path d="M3 9h18"></path><path d="M9 21v-6h6v6"></path></svg>
                            </span>
                            <span class="option-card-body">
                                <strong>Recojo en tienda</strong>
                                <span class="option-card-sub">Gratis · tú lo retiras</span>
                            </span>
                            <input
                                type="radio"
                                name="delivery_mode"
                                style="margin-left: auto"
                                :checked="isPickupMode"
                                @change="setPickupMode(true)"
                            >
                        </label>
                    </div>

                    <div class="ship-grid">
                        {{-- Columna izquierda: dirección de entrega o sucursal de recojo --}}
                        <div class="ship-col">
                            {{-- Modo recojo en tienda: radio buttons de sucursales --}}
                            <template v-if="isPickupMode">
                                <span class="field-label">Selecciona una sucursal</span>
                                <div v-if="pickupBranches.length === 0" class="ship-alert ship-alert--info">
                                    No hay sucursales de recojo configuradas.
                                </div>
                                <div v-else class="option-list">
                                    <label
                                        v-for="branch in pickupBranches"
                                        :key="branch.id"
                                        class="option-card"
                                        :class="{ 'option-card--active': selectedPickupBranch && selectedPickupBranch.id === branch.id }"
                                    >
                                        <input
                                            type="radio"
                                            :value="branch.id"
                                            :checked="selectedPickupBranch && selectedPickupBranch.id === branch.id"
                                            @change="selectPickupBranch(branch)"
                                        >
                                        <span class="option-card-body">
                                            <strong>@{{ branch.name }}</strong>
                                            <span class="option-card-sub" v-if="branch.address">@{{ branch.address }}</span>
                                        </span>
                                    </label>
                                </div>
                            </template>

                            {{-- Modo delivery normal --}}
                            <template v-else>
                                <span class="field-label">Dirección de entrega</span>
                                <button v-if="!form_contact.address" type="button" class="addr-btn" @click="openAddressModal">
                                    <span class="plus">+</span>
                                    Agregar dirección
                                </button>
                                <div v-else class="addr-card">
                                    <span class="addr-card-icon">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7 -9 13 -9 13s-9 -6 -9 -13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                    </span>
                                    <div class="addr-card-info">
                                        <strong>@{{ form_contact.address }}</strong>
                                        <span class="addr-card-sub">@{{ ubigeoLabel }}</span>
                                    </div>
                                    <button type="button" class="addr-change" @click="openAddressModal">Cambiar</button>
                                </div>

                                {{-- Mensaje sin cobertura de delivery --}}
                                <div v-if="deliveryMessage != ''" class="ship-alert ship-alert--warn" role="alert">
                                    <strong>&#9888; Sin cobertura:</strong> @{{ deliveryMessage }}
                                </div>

                                {{-- Opciones de envío: mostrar cuando hay múltiples zonas disponibles --}}
                                <div v-if="availableDeliveryZones.length > 1" class="option-list mt-2">
                                    <span class="field-label">Opciones de envío</span>
                                    <label
                                        v-for="zone in availableDeliveryZones"
                                        :key="zone.id"
                                        class="option-card option-card--row"
                                        :class="{ 'option-card--active': deliveryZone && deliveryZone.id === zone.id }"
                                    >
                                        <span class="option-card-left">
                                            <input
                                                type="radio"
                                                :value="zone.id"
                                                :checked="deliveryZone && deliveryZone.id === zone.id"
                                                @change="selectDeliveryZone(zone)"
                                            >
                                            <span>@{{ zone.name }}</span>
                                        </span>
                                        <strong class="option-card-price">S/ @{{ parseFloat(zone.price).toFixed(2) }}</strong>
                                    </label>
                                </div>

                                {{-- Una sola zona disponible: mostrar informativo --}}
                                <div v-else-if="availableDeliveryZones.length === 1 && deliveryZone" class="ship-note ship-note--ok mt-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5 -5"/></svg>
                                    Envío disponible: <strong>@{{ deliveryZone.name }}</strong> &mdash; S/ @{{ parseFloat(deliveryZone.price).toFixed(2) }}
                                </div>
                            </template>
                        </div>

                        {{-- Columna derecha: teléfono de contacto --}}
                        <div class="ship-col">
                            <span class="field-label">Teléfono de contacto</span>
                            <input
                                type="tel"
                                v-model="form_contact.telephone"
                                class="input"
                                placeholder="Ej: 987 654 321"
                                maxlength="15"
                                inputmode="numeric"
                                required
                            >
                            <p class="hint">Te escribiremos por aquí para coordinar la entrega.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Formulario de datos de invitado (fase 1) --}}
        <div
            class="card card-cart guest-contact-card"
            v-if="records.length > 0 && showGuestForm && !isLoggedIn"
            id="guestContactCollapse"
        >
            <button type="button" class="btn btn-link btn-block text-left p-0" data-toggle="collapse" data-target="#guestContactBody" aria-expanded="true" style="text-decoration: none; display: block;">
                <div class="card-header d-flex align-items-center bg-white border-bottom-0 card-cart-header" style="cursor: pointer;">
                    <span class="icon-card">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#2b2b2b" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" />
                            <path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" />
                        </svg>
                    </span>
                    <span class="ml-2 font-weight-bold title-card">Datos de contacto (invitado)</span>
                    <span class="head-summary">
                        <b v-if="guest_form.email">@{{ guest_form.email }}</b>
                        <span v-else class="head-summary-warn">Completa tus datos</span>
                    </span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="collapse-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2b2b2b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M6 9l6 6l6 -6" /></svg>
                </div>
            </button>
            <div id="guestContactBody" class="collapse show">
                <div class="card-body card-body-h-auto card-cart-body">
                    <p class="hint mb-3">Estos datos se usarán solo para esta compra. No se creará una cuenta ni se solicitará contraseña.</p>
                    <div class="guest-form-grid">
                        <div class="field-full">
                            <label class="field-label" for="guest_email">Correo electrónico *</label>
                            <input
                                id="guest_email"
                                type="email"
                                class="input"
                                v-model.trim="guest_form.email"
                                placeholder="tu@correo.com"
                                autocomplete="email"
                                required
                            >
                        </div>
                        <div>
                            <label class="field-label" for="guest_phone">Teléfono *</label>
                            <input
                                id="guest_phone"
                                type="tel"
                                class="input"
                                v-model.trim="guest_form.telephone"
                                placeholder="Ej: 987 654 321"
                                maxlength="15"
                                inputmode="numeric"
                                required
                            >
                        </div>
                        <div>
                            <label class="field-label" for="guest_doc_type">Tipo de documento *</label>
                            <select
                                id="guest_doc_type"
                                class="input"
                                v-model="guest_form.identity_document_type_id"
                            >
                                <option value="1">DNI</option>
                                <option value="6">RUC</option>
                                <option value="0">Sin documento</option>
                            </select>
                        </div>
                        <div>
                            <label class="field-label" for="guest_doc_number">Número de documento *</label>
                            <input
                                id="guest_doc_number"
                                type="text"
                                class="input"
                                v-model.trim="guest_form.number"
                                :maxlength="guest_form.identity_document_type_id === '6' ? 11 : (guest_form.identity_document_type_id === '1' ? 8 : 15)"
                                inputmode="numeric"
                                required
                            >
                        </div>
                        <div class="field-full">
                            <label class="field-label" for="guest_name">Nombres / Razón social *</label>
                            <input
                                id="guest_name"
                                type="text"
                                class="input"
                                v-model.trim="guest_form.name"
                                placeholder="Nombre completo o razón social"
                                required
                            >
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if($enable_electronic_documents)
            {{-- Modo documentos electrónicos: solo lectura, tipo inferido del número del usuario --}}
            <div class="card card-cart">
                <button type="button" class="btn btn-link btn-block text-left p-0" data-toggle="collapse" data-target="#documentyCollapse" aria-expanded="true" style="text-decoration: none; display: block;">
                    <div class="card-header d-flex align-items-center bg-white border-bottom-0 card-cart-header" style="cursor: pointer;">
                        <span class="icon-card">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#2b2b2b" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 3v4a1 1 0 0 0 1 1h4" />
                                <path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" />
                                <path d="M9 17h6" />
                                <path d="M9 13h6" />
                            </svg>
                        </span>
                        <span class="ml-2 font-weight-bold title-card">Datos del comprobante</span>
                        <span class="head-summary">
                            <b v-if="invoiceTypeLabel">@{{ invoiceTypeLabel }}</b>
                            <template v-if="user && user.number">
                                <span class="head-summary-sep" v-if="invoiceTypeLabel">·</span>
                                <span>@{{ user.number }}</span>
                            </template>
                        </span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="collapse-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2b2b2b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M6 9l6 6l6 -6" /></svg>
                    </div>
                </button>
                <div id="documentyCollapse" class="collapse show">
                    <div class="card-body card-body-h-auto card-cart-body">
                        <ul class="doc-list">
                            <li><span>Cliente</span> <strong>@{{ user.name }}</strong></li>
                            <li><span>Documento</span> <strong>@{{ user.number }}</strong></li>
                            <li><span>Tipo de doc.</span> <strong>@{{ invoiceTypeLabel }}</strong></li>
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        <div class="card card-cart" v-if="records.length > 0">
            <button type="button" class="btn btn-link btn-block text-left p-0" data-toggle="collapse" data-target="#paymentCollapse" aria-expanded="true" style="text-decoration: none; display: block;">
                <div class="card-header d-flex align-items-center bg-white border-bottom-0 card-cart-header" style="cursor: pointer;">
                    <span class="icon-card">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="24"
                            height="24"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="#2b2b2d"
                            stroke-width="1.75"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            >
                            <path d="M3 5m0 3a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v8a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3z" />
                            <path d="M3 10l18 0" />
                            <path d="M7 15l.01 0" />
                            <path d="M11 15l2 0" />
                        </svg>
                    </span>
                    <span class="ml-2 font-weight-bold title-card">Método de pago</span>
                    <span class="head-summary">
                        <template v-if="!isLoggedIn">
                            <b v-if="isGuestFormReady && selectedPaymentMethod === 'culqi'">@{{ titleCulqi }}</b>
                            <b v-else-if="isGuestFormReady && selectedPaymentMethod === 'cash'">@{{ cashPaymentTitle }}</b>
                            <b v-else-if="isGuestFormReady && selectedPaymentMethod === 'yape'">Yape</b>
                            <b v-else-if="isGuestFormReady && selectedPaymentMethod === 'transfer'">Transferencia</b>
                            <b v-else-if="isGuestFormReady && selectedPaymentMethod === 'paypal'">PayPal</b>
                            <b v-else-if="isGuestFormReady && selectedPaymentMethod === 'mp'">@{{ titleMp }}</b>
                            <b v-else-if="isGuestFormReady && selectedPaymentMethod === 'izipay'">@{{ titleIzipay }}</b>
                            <span v-else-if="showGuestForm" class="head-summary-warn">Completa envío y contacto</span>
                            <span v-else class="head-summary-warn">Identifícate para pagar</span>
                        </template>
                        <template v-else>
                            <b v-if="selectedPaymentMethod === 'culqi'">@{{ titleCulqi }}</b>
                            <b v-else-if="selectedPaymentMethod === 'cash'">@{{ cashPaymentTitle }}</b>
                            <b v-else-if="selectedPaymentMethod === 'yape'">Yape</b>
                            <b v-else-if="selectedPaymentMethod === 'transfer'">Transferencia</b>
                            <b v-else-if="selectedPaymentMethod === 'paypal'">PayPal</b>
                            <b v-else-if="selectedPaymentMethod === 'mp'">@{{ titleMp }}</b>
                            <b v-else-if="selectedPaymentMethod === 'izipay'">@{{ titleIzipay }}</b>
                            <span v-else class="head-summary-warn">Elige un método</span>
                        </template>
                    </span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="collapse-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2b2b2b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M6 9l6 6l6 -6" /></svg>
                </div>
            </button>
            <div id="paymentCollapse" class="collapse show">
                <div class="card-body card-body-h-auto card-cart-body">
                    <div class="login-note" v-if="!isLoggedIn && !showGuestForm">
                        <span class="login-note-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                        </span>
                        <p>Puedes <button type="button" class="btn btn-link p-0 align-baseline" @click="handleCheckoutClick">comprar como invitado</button> o <button type="button" class="btn btn-link p-0 align-baseline" @click="openLoginRegisterModal">iniciar sesión</button> para guardar tus datos.</p>
                    </div>
                    <div class="login-note" v-else-if="!isLoggedIn && showGuestForm && !isGuestCheckoutComplete">
                        <span class="login-note-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0"/></svg>
                        </span>
                        <p>Completa tus datos de contacto y la información de envío para habilitar los métodos de pago.</p>
                    </div>
                    <div class="pay-methods" v-if="isLoggedIn || isGuestCheckoutComplete" role="radiogroup">
                        
                        <label v-if="enableCulqi" class="pay-method" :class="{ 'pay-method--active': selectedPaymentMethod === 'culqi' }">
                            <input type="radio" v-model="selectedPaymentMethod" value="culqi" autocomplete="off">
                            <span class="pay-method-ic">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                            </span>
                            <span class="pay-method-label">@{{ titleCulqi }}</span>
                        </label>
                        <div v-if="selectedPaymentMethod === 'culqi'" class="pay-method-panel">
                            <p v-if="descriptionCulqi">@{{ descriptionCulqi }}</p>
                            <button
                                type="button"
                                class="pay-btn pay-method-action"
                                :disabled="processingPayment || !acceptedTerms"
                                @click="runPayment('culqi')"
                            >
                                Pagar con @{{ titleCulqi }}
                            </button>
                        </div>

                        <label v-if="enableIzipay" class="pay-method" :class="{ 'pay-method--active': selectedPaymentMethod === 'izipay' }">
                            <input type="radio" v-model="selectedPaymentMethod" value="izipay" autocomplete="off">
                            <span class="pay-method-ic">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10V8a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v2M4 14v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
                            </span>
                            <span class="pay-method-label">@{{ titleIzipay }}</span>
                        </label>
                        <div v-if="selectedPaymentMethod === 'izipay'" class="pay-method-panel">
                            <p v-if="descriptionIzipay">@{{ descriptionIzipay }}</p>
                            <button
                                type="button"
                                class="pay-btn pay-method-action"
                                :disabled="processingPayment || !acceptedTerms"
                                @click="runPayment('izipay')"
                            >
                                Pagar con @{{ titleIzipay }}
                            </button>
                        </div>

                        <label v-if="enableMp" class="pay-method" :class="{ 'pay-method--active': selectedPaymentMethod === 'mp' }">
                            <input type="radio" v-model="selectedPaymentMethod" value="mp" autocomplete="off">
                            <span class="pay-method-ic">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                            </span>
                            <span class="pay-method-label">@{{ titleMp }}</span>
                        </label>
                        <div v-if="selectedPaymentMethod === 'mp'" class="pay-method-panel">
                            <p v-if="descriptionMp">@{{ descriptionMp }}</p>
                            <button
                                type="button"
                                class="pay-btn pay-method-action"
                                :disabled="processingPayment || !acceptedTerms"
                                @click="runPayment('mp')"
                            >
                                Pagar con @{{ titleMp }}
                            </button>
                        </div>

                        <label v-if="enableCash && (!cashPaymentPickupOnly || isPickupMode)" class="pay-method" :class="{ 'pay-method--active': selectedPaymentMethod === 'cash' }">
                            <input type="radio" v-model="selectedPaymentMethod" value="cash" autocomplete="off">
                            <span class="pay-method-ic">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/></svg>
                            </span>
                            <span class="pay-method-label">@{{ cashPaymentTitle }}</span>
                        </label>
                        
                        <div v-if="selectedPaymentMethod === 'cash'" class="pay-method-panel">
                            <p v-if="cashPaymentDescription">@{{ cashPaymentDescription }}</p>
                            <button
                                type="button"
                                class="pay-btn pay-method-action"
                                :disabled="processingPayment || !acceptedTerms"
                                @click="runPayment('cash')"
                            >
                                Confirmar pedido — @{{ cashPaymentTitle }}
                            </button>
                        </div>
                        <label v-if="enableYape" class="pay-method" :class="{ 'pay-method--active': selectedPaymentMethod === 'yape' }">
                            <input type="radio" v-model="selectedPaymentMethod" value="yape" autocomplete="off">
                            <span class="pay-method-ic">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="2" width="12" height="20" rx="2"/><line x1="11" y1="18" x2="13" y2="18"/></svg>
                            </span>
                            <span class="pay-method-label">Pagar con Yape</span>
                        </label>
                        
                        <div v-if="selectedPaymentMethod === 'yape'" class="pay-method-panel">
                            <p>Escanea el código QR desde tu app de Yape.</p>
                            @if(!empty($payment_configuration->image_url_yape))
                            <div style="text-align: center; margin: 15px 0;">
                                <img src="{{ $payment_configuration->image_url_yape }}" alt="QR Yape" style="max-width: 150px; border-radius: 8px; border: 1px solid #eee;">
                            </div>
                            @endif
                            <div style="font-size: 14px; text-align: center; margin-bottom: 10px;">
                                <strong>Titular:</strong> {{ $payment_configuration->name_yape ?? 'No registrado' }}<br>
                                <strong>Teléfono:</strong> <span>{{ $payment_configuration->telephone_yape ?? 'No registrado' }}</span>
                                <button type="button" @click.prevent="copyToClipboard('{{ $payment_configuration->telephone_yape ?? '' }}')" class="btn btn-sm btn-outline-secondary" style="padding: 2px 8px; font-size: 12px; margin-left: 5px;">
                                    Copiar
                                </button>
                            </div>
                            <button
                                type="button"
                                class="pay-btn pay-method-action"
                                :disabled="processingPayment || !acceptedTerms"
                                @click="runPayment('yape')"
                            >
                                Confirmar pedido con Yape
                            </button>
                        </div>

                        <label v-if="enableTransfer" class="pay-method" :class="{ 'pay-method--active': selectedPaymentMethod === 'transfer' }">
                            <input type="radio" v-model="selectedPaymentMethod" value="transfer" autocomplete="off">
                            <span class="pay-method-ic">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V10l7 -5l7 5v11"/><path d="M9 21v-6h6v6"/></svg>
                            </span>
                            <span class="pay-method-label">Transferencia bancaria</span>
                        </label>
                        
                        <div v-if="selectedPaymentMethod === 'transfer'" class="pay-method-panel">
                            <p>Realiza el depósito en alguna de nuestras cuentas bancarias y envíanos el voucher por WhatsApp.</p>
                            @if(isset($bank_accounts) && count($bank_accounts) > 0)
                                <ul style="list-style: none; padding-left: 0; font-size: 13px; margin: 10px 0 0;">
                                @foreach($bank_accounts as $account)
                                    <li style="margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px solid #eee;">
                                        <strong>Banco:</strong> {{ $account->bank->description }} ({{ $account->currency_type->symbol }})<br>
                                        <strong>Cuenta:</strong> {{ $account->number }}<br>
                                        @if($account->cci)
                                        <strong>CCI:</strong> {{ $account->cci }}
                                        @endif
                                    </li>
                                @endforeach
                                </ul>
                            @else
                                <p style="font-size: 13px; font-weight: bold; color: #d9534f; margin-top: 10px;">No hay cuentas bancarias configuradas.</p>
                            @endif
                            <button
                                type="button"
                                class="pay-btn pay-method-action"
                                :disabled="processingPayment || !acceptedTerms"
                                @click="runPayment('transfer')"
                            >
                                Confirmar pedido con transferencia
                            </button>
                        </div>
                        @if($information->script_paypal)
                        <label class="pay-method" :class="{ 'pay-method--active': selectedPaymentMethod === 'paypal' }">
                            <input type="radio" v-model="selectedPaymentMethod" value="paypal" autocomplete="off">
                            <span class="pay-method-ic">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M7 11l1 -7h6a3 3 0 0 1 0 6h-4"/><path d="M5 20l1.5 -9h5a3 3 0 0 1 0 6h-4"/></svg>
                            </span>
                            <span class="pay-method-label">PayPal</span>
                        </label>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div><!-- End .col-lg-8 -->

    <div class="col-md-4">
      <div class="summary-sticky">
        <div class="cart-summary">
            <div class="sum-head"><h3>Resumen</h3></div>
            <div class="sum-body">
                <table class="table table-totals">
                    <tbody>

                        <tr v-if="summary.total_exonerated > 0">
                            <td>Op. exoneradas</td>
                            <td>S/ @{{ summary.total_exonerated }}</td>
                        </tr>
                        <tr v-if="summary.total_taxed > 0">
                            <td>Op. gravada</td>
                            <td>S/ @{{ summary.total_taxed }}</td>
                        </tr>
                        <tr v-if="summary.total_igv > 0">
                            <td>IGV (18%)</td>
                            <td>S/ @{{ summary.total_igv }}</td>
                        </tr>
                        <tr v-if="appliedCoupon && appliedCoupon.code">
                            <td>
                                Cupón <span class="badge badge-dark">@{{ appliedCoupon.code }}</span>
                                <button class="coupon-remove" @click="removeCoupon">Eliminar</button>
                            </td>
                            <td>
                                &minus; S/ @{{ appliedCoupon.discount }}
                            </td>
                        </tr>
                        <tr v-if="deliveryZone && parseFloat(deliveryZone.price) > 0">
                            <td>Envío <small class="text-muted">(@{{ deliveryZone.name }})</small></td>
                            <td>S/ @{{ summary.delivery }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>Total</td>
                            <td>S/ @{{summary.total}}</td>
                        </tr>
                    </tfoot>
                </table>

                <!-- Coupon input and applied coupon display -->
                <div class="coupon-block">
                    <div class="coupon">
                        <input v-model="couponField" type="text" class="input" placeholder="Código de cupón">
                        <button class="coupon-btn" @click="applyCoupon" :disabled="couponLoading">Aplicar</button>
                    </div>
                    <small class="coupon-msg text-danger" v-if="couponMessage">@{{ couponMessage }}</small>
                </div>
                <label class="terms" :class="{ 'terms--checked': acceptedTerms }" id="termsLabel">
                  <input type="checkbox" id="termsCheck" v-model="acceptedTerms">
                  <span class="box"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg></span>
                  <span class="terms-txt">He leído y acepto los <a href="#" data-modal-open="termsModal" @click.prevent>Términos y Condiciones</a>.</span>
                </label>
                <div class="checkout-methods">
                    <p v-if="!isLoggedIn && showGuestForm && isGuestCheckoutComplete" class="checkout-hint">
                        Elige un método de pago y confirma desde el botón correspondiente.
                    </p>
                    <button
                        v-if="!isLoggedIn && !(showGuestForm && isGuestCheckoutComplete)"
                        type="button"
                        class="pay-btn"
                        :class="{ disabled: !acceptedTerms || records.length === 0 }"
                        :disabled="!acceptedTerms || records.length === 0"
                        @click="handleCheckoutClick"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                        @{{ showGuestForm ? 'Completar datos' : 'Comprar como invitado' }}
                    </button>
                    <button
                        v-else-if="isLoggedIn && selectedPaymentMethod !== 'paypal'"
                        class="pay-btn"
                        :class="{ disabled: !acceptedTerms }"
                        :disabled="!selectedPaymentMethod || !acceptedTerms"
                        @click="executePayment"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                        Pagar
                    </button>
                </div><!-- End .checkout-methods -->

                <div class="trust">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    Pago 100% seguro y protegido
                </div>
                <div class="cards-row px-5">
                    <img src="{{ asset('porto-ecommerce/assets/images/payments-bordered.svg') }}" alt="payment methods" class="footer-payments">
                </div>
            </div>
            <div class="secure-foot">Transacción cifrada · IGV incluido según ley peruana</div>
        </div><!-- End .cart-summary -->
      </div><!-- End .summary-sticky -->
    </div><!-- End .col-lg-4 -->

    <!-- Modal M-01: Elección de identidad (Guest Checkout) -->
    <div class="modal fade guest-modal" id="guestIdentityModal" tabindex="-1" role="dialog" aria-labelledby="guestIdentityModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="guestIdentityModalLabel">¿Cómo deseas continuar?</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar" @click="closeGuestIdentityModal">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="guest-modal__intro mb-0">
                        Puedes completar tu compra sin crear una cuenta o acceder con tu usuario registrado.
                    </p>
                    <div class="guest-modal__actions mt-4">
                        <button type="button" class="pay-btn" @click="chooseGuestCheckout">
                            Comprar como invitado
                        </button>
                        <button type="button" class="pay-btn pay-btn--ghost" @click="openLoginRegisterModal">
                            Iniciar sesión / Registrarse
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-link text-muted" data-dismiss="modal" @click="closeGuestIdentityModal">Cancelar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal M-02: Advertencia de compra invitada -->
    <div class="modal fade guest-modal" id="guestWarningModal" tabindex="-1" role="dialog" aria-labelledby="guestWarningModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="guestWarningModalLabel">Compra como invitado</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar" @click="closeGuestWarningModal">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">
                        Si continúas sin iniciar sesión no podrás seguir tu pedido en línea ni ver el historial de compras desde tu cuenta.
                    </p>
                    <ul class="guest-modal__warning-list">
                        <li>No se creará una cuenta ni se solicitará contraseña.</li>
                        <li>El seguimiento se realizará por correo electrónico.</li>
                        <li>Tus datos se usarán únicamente para procesar esta compra.</li>
                    </ul>
                    <label class="guest-modal__check">
                        <input type="checkbox" v-model="guestWarningChecked">
                        <span>Entiendo y deseo continuar como invitado</span>
                    </label>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <button type="button" class="btn btn-link text-muted px-0" @click="backToGuestIdentityModal">Volver</button>
                    <button
                        type="button"
                        class="pay-btn"
                        :disabled="!guestWarningChecked"
                        :class="{ disabled: !guestWarningChecked }"
                        @click="confirmGuestCheckout"
                    >
                        Continuar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Dirección -->
    <div class="modal fade" id="addressModal" tabindex="-1" role="dialog" aria-labelledby="addressModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header h-auto">
                    <h3 class="modal-title">Confirmar dirección</h3>
                    <button type="button" class="close" @click="closeAddressModal()" aria-label="Close">
                        <span aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-x"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M18 6l-12 12" /><path d="M6 6l12 12" /></svg>
                        </span>
                    </button>
                </div>

                <div class="modal-body p-2">
                    @if(!empty($googleMapsApiKey))
                        <div id="map"></div>
                    @endif

                    <div class="p-3">
                        @if(empty($googleMapsApiKey))
                            <div class="form-row mb-2">
                                <div class="col-12 col-md-4 mb-2 mb-md-0">
                                    <label class="field-label" for="department">Departamento</label>
                                    <select v-model="selectedDepartment" @change="updateProvinces" name="department" id="department" class="input">
                                        <option value="">Seleccione departamento</option>
                                        <option v-for="department in departments" :key="department.value" :value="department.value">
                                            @{{ department.label }}
                                        </option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-4 mb-2 mb-md-0">
                                    <label class="field-label" for="province">Provincia</label>
                                    <select v-model="selectedProvince" @change="updateDistricts" name="province" id="province" class="input">
                                        <option value="">Seleccione provincia</option>
                                        <option v-for="province in provinces" :key="province.value" :value="province.value">
                                            @{{ province.label }}
                                        </option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="field-label" for="district">Distrito</label>
                                    <select v-model="selectedDistrict" @change="checkDeliveryZone" name="district" id="district" class="input">
                                        <option value="">Seleccione distrito</option>
                                        <option v-for="district in districts" :key="district.value" :value="district.value">
                                            @{{ district.label }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                        @endif

                        <div class="mb-2">
                            <label class="field-label">Dirección</label>
                            <div class="position-relative">
                                <input
                                    v-model="addressModal.address"
                                    @input="onAddressInputChange"
                                    @keydown.down.prevent="moveSuggestion(1)"
                                    @keydown.up.prevent="moveSuggestion(-1)"
                                    @keydown.enter.prevent="selectHighlighted"
                                    @keydown.esc="clearSuggestions"
                                    id="addressAutocompleteInput"
                                    type="text"
                                    class="input"
                                    placeholder="Ingrese su dirección completa"
                                    autocomplete="off">

                                <ul v-if="addressSuggestions.length > 0" class="addr-suggestions">
                                    <li v-for="(suggestion, i) in addressSuggestions"
                                        :key="i"
                                        @mousedown.prevent="selectSuggestionFromList(suggestion)"
                                        class="addr-suggestion"
                                        :class="{ 'addr-suggestion--active': highlightedIndex === i }">
                                        <span class="font-weight-bold">@{{ suggestion.mainText }}</span>
                                        <span class="d-block text-muted small">@{{ suggestion.secondaryText }}</span>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div class="mb-0">
                            <label class="field-label">Referencias</label>
                            <textarea v-model="addressModal.reference" class="input" rows="2" placeholder="Ej: Al costado del parque, frente a la iglesia"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Alerta sin cobertura dentro del modal de dirección -->
                <div v-if="deliveryMessage" class="ship-alert ship-alert--warn mx-3 mb-3">
                    <strong>&#9888; Sin cobertura:</strong> @{{ deliveryMessage }}
                </div>

                <div class="p-3 border-top px-4">
                    <button type="button" class="pay-btn" @click="confirmAddress()">Continuar</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== Modal reutilizable: Términos y Condiciones ===== --}}
    <div class="app-modal" id="termsModal" role="dialog" aria-modal="true" aria-labelledby="termsModalTitle" aria-hidden="true">
        <div class="app-modal__dialog">
            <div class="app-modal__header">
                <span class="app-modal__icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3v4a1 1 0 0 0 1 1h4"/><path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z"/><path d="M9 9h1"/><path d="M9 13h6"/><path d="M9 17h6"/></svg>
                </span>
                <h3 class="app-modal__title" id="termsModalTitle">Términos y Condiciones</h3>
                <button type="button" class="app-modal__close" data-modal-close aria-label="Cerrar">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6l-12 12"/><path d="M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="app-modal__body">
                @if(!empty($configuration->terms_conditions))
                    <div class="app-modal__prose">{!! $configuration->terms_conditions !!}</div>
                @else
                    <p class="app-modal__prose text-muted">No se han definido términos y condiciones.</p>
                @endif
            </div>
            <div class="app-modal__footer">
                <button type="button" class="pay-btn second-btn" data-modal-close>Cerrar</button>
                <button type="button" class="pay-btn" data-modal-accept="termsCheck" data-modal-close>Acepto los términos</button>
            </div>
        </div>
    </div>

    <!-- ===== Overlay de carga mientras se procesa el pago ===== -->
    <div class="purchase-overlay purchase-overlay--show" v-if="processingPayment">
        <div class="purchase-loading" role="status" aria-live="polite">
            <span class="purchase-spinner" aria-hidden="true"></span>
            <h3>Estamos generando tu pedido</h3>
            <p>Por favor no cierres esta ventana hasta que el proceso termine.</p>
        </div>
    </div>

    <!-- ===== Confirmación de compra (post-pago) ===== -->
    <div class="purchase-overlay" :class="{ 'purchase-overlay--show': showConfirmModal }" v-if="successOrder">
        <div class="purchase-confirm" role="dialog" aria-modal="true" aria-label="Detalle de tu compra">
            <div class="purchase-confirm-head">
                <span class="ic">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1 -2 2H5a2 2 0 0 1 -2 -2V5a2 2 0 0 1 2 -2h11"/></svg>
                </span>
                <div>
                    <h3>¡Pago realizado!</h3>
                    <div class="ordn">Pedido @{{ successOrder.number }}</div>
                </div>
            </div>
            <div class="purchase-confirm-body">
                <div class="o-item" v-for="(it, i) in successOrder.items" :key="i">
                    <div><span class="o-q">@{{ it.cantidad }}×</span>@{{ it.description }}</div>
                    <span class="o-amt">@{{ it.symbol }} @{{ it.total }}</span>
                </div>
                <div class="o-sep"></div>
                <div class="o-row" v-if="parseFloat(successOrder.total_exonerated) > 0">Op. exoneradas <span class="v">S/ @{{ successOrder.total_exonerated }}</span></div>
                <div class="o-row" v-if="parseFloat(successOrder.total_taxed) > 0">Op. gravada <span class="v">S/ @{{ successOrder.total_taxed }}</span></div>
                <div class="o-row" v-if="parseFloat(successOrder.total_igv) > 0">IGV (18%) <span class="v">S/ @{{ successOrder.total_igv }}</span></div>
                <div class="o-row" v-if="parseFloat(successOrder.delivery) > 0">Envío <span class="v">S/ @{{ successOrder.delivery }}</span></div>
                <div class="o-total"><span class="l">Total pagado</span><span class="a">S/ @{{ successOrder.total }}</span></div>
                <div class="o-pay">
                    <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                    Pago: @{{ successOrder.paymentLabel }} · @{{ successOrder.deliveryLabel }}
                </div>
            </div>
            <div class="purchase-confirm-foot">
                <button type="button" class="pay-btn" @click="goToThankYou">
                    Continuar
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </button>
            </div>
        </div>
    </div>

</div><!-- End .row -->

@if(auth('ecommerce')->check() && $information->script_paypal)
<div id="paypal-widget-container" style="display:none;">
    {!!html_entity_decode($information->script_paypal)!!}
</div>
@endif

<!-- DOM Containers for MP and Izipay (fuera de #app para evitar conflicto con Vue) -->
<div id="mp-brick-container" style="display:none"></div>
<div id="izipay-payment-host" class="kr-izipay-container-inner" style="display:none"></div>

<input type="hidden" id="total_amount" data-total="0.0">

@endsection

@push('scripts')
<!-- Configuration globals para cart app -->
<script>
    window.__ecommerce_config = {
        phone_whatsapp: {!! json_encode($phoneWhatsapp ?? '') !!},
        enable_whatsapp: {!! json_encode($showWhatsapp ?? false) !!},
        global_discount_type: {!! json_encode($global_discount_type ?? []) !!},
        user: {!! json_encode(optional(Auth::guard("ecommerce")->user())->makeHidden(['password', 'remember_token'])) !!},
        userAddress: {!! json_encode($userAddress ?? null) !!},
        enable_electronic_documents: {!! json_encode($enable_electronic_documents ?? false) !!},
        enable_store_pickup: {!! json_encode($enable_store_pickup ?? false) !!},
        pickup_branches: {!! json_encode($pickup_branches ?? []) !!},
        enable_yape: {!! json_encode($enable_yape ?? false) !!},
        enable_transfer: {!! json_encode($enable_transfer ?? false) !!},
        enable_cash: {!! json_encode(isset($configuration->preferences['enable_cash']) && $configuration->preferences['enable_cash'] == 1) !!},
        cash_payment_title: {!! json_encode($configuration->preferences['cash_title'] ?? 'Pago contra entrega') !!},
        cash_payment_description: {!! json_encode($configuration->preferences['cash_description'] ?? '') !!},
        cash_payment_pickup_only: {!! json_encode(isset($configuration->preferences['cash_pickup_only']) && $configuration->preferences['cash_pickup_only'] == 1) !!},
        enable_izipay: {!! json_encode($payment_configuration->enabled_izipay ?? false) !!},
        public_key_izipay: {!! json_encode($payment_configuration->publickey_izipay ?? '') !!},
        title_izipay: {!! json_encode($preferences['title_izipay'] ?? 'Pago con Izipay') !!},
        description_izipay: {!! json_encode($preferences['description_izipay'] ?? '') !!},
        enable_mp: {!! json_encode($payment_configuration->enabled_mp ?? false) !!},
        public_key_mp: {!! json_encode($payment_configuration->public_key_mp ?? '') !!},
        title_mp: {!! json_encode($preferences['title_mp'] ?? 'Mercado Pago') !!},
        description_mp: {!! json_encode($preferences['description_mp'] ?? '') !!},
        enable_culqi: {!! json_encode($payment_configuration->enabled_culqi ?? false) !!},
        title_culqi: {!! json_encode($preferences['title_culqi'] ?? 'Pago con Tarjeta (Culqi)') !!},
        description_culqi: {!! json_encode($preferences['description_culqi'] ?? '') !!},
    };

    window.__routes = {
        payment_cash: '{{ route("tenant_ecommerce_payment_cash") }}',
        user_data: '{{ route("tenant_ecommerce_user_data") }}',
        locations: '{{ route("get_location_cascade") }}',
        home: '{{ route("tenant.ecommerce.index") }}',
        culqi: '{{ route("tenant_ecommerce_culqui") }}',
        izipay_payment: '{{ route("tenant_ecommerce_izipay") }}',
        izipay_transaction: '{{ route("tenant_ecommerce_izipay_transaction") }}',
        mercadopago_payment: '{{ route("tenant_ecommerce_mp") }}',
        thank_you: '{{ route("tenant_ecommerce_thank_you", ["external_id" => "EXTERNAL_ID"]) }}',
    };
</script>

<script>
    (function () {
        function openModal(modal) {
            if (!modal) return;
            modal.classList.add('app-modal--open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(modal) {
            if (!modal) return;
            modal.classList.remove('app-modal--open');
            modal.setAttribute('aria-hidden', 'true');
            if (!document.querySelector('.app-modal--open')) {
                document.body.style.overflow = '';
            }
        }

        document.addEventListener('click', function (e) {
            // Abrir
            var opener = e.target.closest('[data-modal-open]');
            if (opener) {
                e.preventDefault();
                openModal(document.getElementById(opener.getAttribute('data-modal-open')));
                return;
            }

            // Aceptar: marca un checkbox antes de cerrar (dispara change para Vue/listeners)
            var accepter = e.target.closest('[data-modal-accept]');
            if (accepter) {
                var check = document.getElementById(accepter.getAttribute('data-modal-accept'));
                if (check && !check.checked) {
                    check.checked = true;
                    check.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }

            // Cerrar: botón con [data-modal-close] o clic en el overlay
            if (e.target.closest('[data-modal-close]')) {
                closeModal(e.target.closest('.app-modal'));
                return;
            }
            if (e.target.classList.contains('app-modal')) {
                closeModal(e.target);
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeModal(document.querySelector('.app-modal--open'));
            }
        });
    })();
</script>

@vite('modules/Ecommerce/Resources/assets/js/frontend/cart-app.js')

<script>
    Culqi.publicKey = {!! json_encode($payment_configuration->publickey_culqi ?? '') !!};
    if(!Culqi.publicKey)
    {
      $('.culqi').hide()
/*
        swal({
            title: "Culqi configuración",
            text: "El pago con visa aun no esta disponible. Intente con efectivo.",
            type: "error",
            position: 'top-end',
            icon: 'warning',
        })
*/
    }
    Culqi.options({
        installments: true
    });

    async function askedDocument(order) {
        app_cart.order_generated = order
        $('#modal_ask_document').modal('show')
    }

    async function execCulqi() {

       console.log( 'errores', app_cart.errors)

       //app_cart.errors = 'demo'

    //   console.log( 'errores22', app_cart.errors)


        let precio = Math.round((Number($("#total_amount").data('total')) * 100).toFixed(2));
        if (precio > 0) {
            Culqi.settings({
                title: "Productos Ecommerce",
                currency: 'PEN',
                description: 'Compras Ecommerce Facturador Pro',
                amount: precio
            });
            Culqi.open();
        }
    }


    async function culqi() {
        if (Culqi.token) {

            swal({
                title: "Estamos hablando con su banco",
                text: `Por favor no cierre esta ventana hasta que el proceso termine.`,
                focusConfirm: false,
                onOpen: () => {
                    Swal.showLoading()
                }
            });

            let precio = Math.round((Number($("#total_amount").data('total')).toFixed(2) * 100));
            let precio_culqi = Number($("#total_amount").data('total')).toFixed(2);

            var url = "/culqi";
            var token = Culqi.token.id;
            var email = Culqi.token.email;
            var installments = Culqi.token.metadata.installments;

            const formpayment = await app_cart.getFormPaymentCash()

            var data = {
                producto: 'Compras Ecommerce Facturador Pro',
                precio: precio,
                precio_culqi: precio_culqi,
                token: token,
                email: email,
                installments: installments,
                customer: JSON.stringify(formpayment.customer),
                items: JSON.stringify(getItems()),
                purchase: JSON.stringify(formpayment.purchase),
                // Coupon fields
                discount_coupon_code: formpayment.discount_coupon_code,
                discount_coupon_id: formpayment.discount_coupon_id,
                total_discount: formpayment.total_discount,
                shipping_address: formpayment.shipping_address || '',
            }

            $.ajax({
              url: "{{route('tenant_ecommerce_culqui')}}",
              method: 'post',
              headers: {
                  'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
              },
              data: data,
              dataType: 'JSON',
              success: function (data) {
                if (data.success == true) {
                  app_cart.saveContactDataUser();
                  app_cart.clearShoppingCart();
                  swal({
                    title: "Gracias por su pago!",
                    text: "En breve le enviaremos un correo electronico con los detalles de su compra.",
                    type: "success"
                  }).then((x) => {
                    askedDocument(data.order);
                    //window.location = "{{ route('tenant.ecommerce.index') }}";
                  })
                } else {
                  const message = data.message
                  swal("Pago No realizado", message, "error");
                }
              },
              error: function (error_data) {
                console.log(error_data)
                if (error_data.status === 422) {
                    app_cart.errors = JSON.parse( error_data.responseText);
                }
                swal("Pago No realizado", 'Faltan completar campos', "error");
              }
            });

        } else {
            console.log(Culqi.error);
            swal("Pago No realizado", Culqi.error.user_message, "error");
        }
    };

    function getCustomer() {
        let user = JSON.parse('{!! json_encode( Auth::guard("ecommerce")->user() ) !!}')
        return {
            "codigo_tipo_documento_identidad": "0",
            "numero_documento": "0",
            "apellidos_y_nombres_o_razon_social": user.name,
            "codigo_pais": "PE",
            "ubigeo": "150101",
            "direccion": app_cart.user.address,
            "correo_electronico": user.email,
            "telefono": app_cart.user.telephone
        }
    }

    function getItems() {
        return app_cart.records
    }

    function isNumberKey(evt) {
        var charCode = (evt.which) ? evt.which : evt.keyCode;
        if (charCode != 46 && charCode > 31 &&
            (charCode < 48 || charCode > 57))
            return false;
        return true;
    }

</script>

<script src="{{ route('google_maps_script') }}"></script>

@endpush
