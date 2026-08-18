<?php

use App\Models\Tenant\Configuration;
use Modules\Inventory\Models\InventoryConfiguration;

$configuration = Configuration::first();
$firstLevel = $path[0] ?? null;
$secondLevel = $path[1] ?? null;
$thridLevel = $path[2] ?? null;

$inventory_configuration = InventoryConfiguration::getSidebarPermissions();

// Obtener configuración visual para selector de establecimiento
$visual = $configuration->visual ?? null;
$showInSidebar = null; // will be resolved after multi-user calculation

$establishments = App\Models\Tenant\Establishment::select('id', 'description')->get();

$multiUserCount = 0;
if(config('configuration.multi_user_enabled')) {
    try {
        $website = app(\Hyn\Tenancy\Environment::class)->tenant();
        $currentClient = \App\Models\System\Client::currentClientByWebsite($website)->first();
        if($currentClient && auth()->check()) {
            $currentUser = auth()->user();
            if(!empty($currentUser->is_multi_user) && $currentUser->is_multi_user) {
                $originMulti = \Modules\MultiUser\Models\System\MultiUser::find($currentUser->multi_user_id);
                if($originMulti) {
                    $multiUserCount = \Modules\MultiUser\Models\System\MultiUser::where('origin_client_id', $originMulti->origin_client_id)
                        ->where('origin_user_id', $originMulti->origin_user_id)
                        ->count();
                    $multiUserCount = $multiUserCount + 1;
                } else {
                    $multiUserCount = 0;
                }
            } else {
                $multiUserCount = \Modules\MultiUser\Models\System\MultiUser::where('origin_client_id', $currentClient->id)
                    ->where('origin_user_id', $currentUser->id)
                    ->count();
                $multiUserCount = $multiUserCount + 1;
            }
        }
    } catch (\Exception $e) {
        $multiUserCount = 0;
    }
}
$showMultiUser = $multiUserCount > 1 && config('configuration.multi_user_enabled');

// Si hay múltiples sucursales o multiempresa disponible,
// el selector debe permanecer visible en sidebar para evitar perder acceso.
$defaultSidebarVisibility = (count($establishments) > 1) || $showMultiUser;

if (is_null($showInSidebar)) {
    if (is_object($visual) && property_exists($visual, 'branch_selector_in_sidebar')) {
        $showInSidebar = (bool)$visual->branch_selector_in_sidebar;
    } elseif (is_array($visual) && array_key_exists('branch_selector_in_sidebar', $visual)) {
        $showInSidebar = (bool)$visual['branch_selector_in_sidebar'];
    } else {
        $showInSidebar = $defaultSidebarVisibility;
    }
}

$current = auth()->user()->establishment_id;
$canShowBranchSelector = auth()->user()->type == 'admin' && count($establishments) > 0;

try {
    $website = app(\Hyn\Tenancy\Environment::class)->tenant();
    $currentClient = \App\Models\System\Client::currentClientByWebsite($website)->first();
    $current_client_fqdn = $currentClient->hostname->fqdn ?? '';
} catch (\Exception $e) {
    $current_client_fqdn = '';
}

$showTransfer = collect($vc_module_levels)->intersect(['inventory', 'inventory_devolutions', 'inventory_report_kardex', 'inventory_report', 'inventory_report_valued_kardex'])->isEmpty() && in_array('inventory_transfers', $vc_module_levels);
?>
<aside id="sidebar-left" class="sidebar-left {{ ($showInSidebar && $canShowBranchSelector && $showMultiUser) ? 'show-both-selectors' : (($showInSidebar && ($canShowBranchSelector || $showMultiUser)) ? 'show-branch-selector' : 'no-branch-selector') }}">
    <div class="sidebar-header sidebar-header-desktop">
        <div class="logo-container-sidebar pe-2">
            <a href="{{ route('tenant.dashboard.index') }}" class="logo pt-2 pt-md-0">
                @if($vc_company->logo)
                    <img src="{{ asset('storage/uploads/logos/' . $vc_company->logo) }}" alt="Logo" class="logo-light"
                        style="{{ $vc_company->logo_dark ? '' : '--show-light-logo: block;' }}" />
                @else
                    <img src="{{ asset('logo/tulogo.png') }}" alt="Logo" />
                @endif

                @if($vc_company->logo_dark)
                    <img src="{{ asset('storage/uploads/logos/' . $vc_company->logo_dark) }}" alt="Logo" class="logo-dark" />
                @endif
            </a>
        </div>
        <div class="sidebar-toggle-container d-none d-md-flex">
            <div class="sidebar-toggle position-relative ms-0 p-0" data-toggle-class="sidebar-left-collapsed" data-target="html"
                data-fire-event="sidebar-left-toggle" title="Colapsar menú lateral">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-layout-sidebar-left-collapse fa-angle-left"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 4m0 2a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2z" /><path d="M9 4v16" /> <path class="path-left" d="M15 10l-2 2l2 2" /> </svg>
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-layout-sidebar-right-collapse fa-angle-right"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 4m0 2a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2z" /><path d="M9 4v16" /><path class="path-right" d="M14 10l2 2l-2 2" /></svg>
            </div>
        </div>
        <div class="d-md-none toggle-sidebar-left" data-toggle-class="sidebar-left-opened" data-target="html"
            data-fire-event="sidebar-left-opened">
            <i class="fas fa-times"></i>
        </div>
    </div>

    @if ($canShowBranchSelector || $showMultiUser)
        <div class="establishment-selector-container mb-2" id="sidebar-selectors-container" style="display: {{ $showInSidebar ? 'block' : 'none' }};">
            @if ($canShowBranchSelector)
                <div class="form-group mb-1 form-establishment" id="sidebar-establishment-selector-container" style="display: {{ $showInSidebar ? 'block' : 'none' }};">
                    <label class="control-label mt-1">Cambiar sucursal</label>
                    <select
                        class="el-input__inner input-select-establishment"
                        name="establishment_selector"
                        id="sidebar-establishment-selector"
                        onchange="changeSidebarEstablishment(this.value)"
                    >
                        @foreach($establishments as $establishment)
                            <option
                                value="{{ $establishment->id }}"
                                {{ $establishment->id == $current ? 'selected' : '' }}
                            >
                                {{ $establishment->description }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if($showMultiUser)
                <div class="sidebar-multi-user-selector-container" id="sidebar-multi-user-selector-container" style="display: {{ $showInSidebar ? 'block' : 'none' }};">
                    <div class="sidebar-multi-user-selector-wrapper">
                        <div class="sidebar-multi-user-placeholder" aria-hidden="true">
                            <?xml version="1.0" encoding="utf-8"?><svg version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 2400 2400" xml:space="preserve"><g stroke-width="200" stroke-linecap="round" stroke="currentColor" fill="none" id="spinner"><line x1="1200" y1="600" x2="1200" y2="100"/><line opacity="0.5" x1="1200" y1="2300" x2="1200" y2="1800"/><line opacity="0.917" x1="900" y1="680.4" x2="650" y2="247.4"/><line opacity="0.417" x1="1750" y1="2152.6" x2="1500" y2="1719.6"/><line opacity="0.833" x1="680.4" y1="900" x2="247.4" y2="650"/><line opacity="0.333" x1="2152.6" y1="1750" x2="1719.6" y2="1500"/><line opacity="0.75" x1="600" y1="1200" x2="100" y2="1200"/><line opacity="0.25" x1="2300" y1="1200" x2="1800" y2="1200"/><line opacity="0.667" x1="680.4" y1="1500" x2="247.4" y2="1750"/><line opacity="0.167" x1="2152.6" y1="650" x2="1719.6" y2="900"/><line opacity="0.583" x1="900" y1="1719.6" x2="650" y2="2152.6"/><line opacity="0.083" x1="1750" y1="247.4" x2="1500" y2="680.4"/><animateTransform attributeName="transform" attributeType="XML" type="rotate" keyTimes="0;0.08333;0.16667;0.25;0.33333;0.41667;0.5;0.58333;0.66667;0.75;0.83333;0.91667" values="0 1199 1199;30 1199 1199;60 1199 1199;90 1199 1199;120 1199 1199;150 1199 1199;180 1199 1199;210 1199 1199;240 1199 1199;270 1199 1199;300 1199 1199;330 1199 1199" dur="0.83333s" begin="0s" repeatCount="indefinite" calcMode="discrete"/></g></svg>
                            Cargando...
                        </div>
                        <tenant-multi-users-change-client class="sidebar-multi-user-selector"></tenant-multi-users-change-client>
                    </div>
                </div>
            @endif
        </div>
    @endif
    @if ($canShowBranchSelector || $showMultiUser)
        <div class="contain-icon-establishment-wrapper" id="sidebar-establishment-icon-wrapper" style="display: {{ $showInSidebar ? 'block' : 'none' }};">
            <div class="contain-icon-establishment" id="establishment-icon-trigger" role="button" tabindex="0" aria-label="Cambiar sucursal">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M3 21l18 0"></path><path d="M4 21l0 -10"></path><path d="M20 21l0 -10"></path><path d="M5 11l14 0"></path><path d="M5 11l1 -6h12l1 6"></path><path d="M9 21l0 -8l6 0l0 8"></path></svg>
            </div>
            <div class="tooltip-right" role="tooltip">Cambiar @if ($canShowBranchSelector) sucursal @endif @if ($canShowBranchSelector && $showMultiUser) o @endif @if ($showMultiUser) empresa @endif</div>
            <div class="establishment-dropdown" id="establishment-dropdown">
                <div class="establishment-dropdown-header">
                    <span>Cambiar @if ($canShowBranchSelector) Sucursal @endif @if ($canShowBranchSelector && $showMultiUser) / @endif @if ($showMultiUser) Empresa @endif</span>
                </div>
                <div class="establishment-dropdown-content">
                    @if ($canShowBranchSelector)
                        <div id="branch-selector-dropdown" style="display: {{ $showInSidebar ? 'block' : 'none' }};">
                            <label class="control-label mt-0">Cambiar sucursal:</label>
                            <select
                                class="el-input__inner input-select-establishment"
                                name="establishment_selector_dropdown"
                                id="dropdown-establishment-selector"
                                onchange="changeSidebarEstablishment(this.value)"
                            >
                                @foreach($establishments as $establishment)
                                    <option
                                        value="{{ $establishment->id }}"
                                        {{ $establishment->id == $current ? 'selected' : '' }}
                                    >
                                        {{ $establishment->description }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    @if($showMultiUser)
                        <div class="sidebar-multi-user-selector-container" id="multi-user-selector-dropdown" style="display: {{ $showInSidebar ? 'block' : 'none' }};">
                            <div class="sidebar-multi-user-selector-wrapper">
                                <div class="sidebar-multi-user-placeholder" aria-hidden="true"></div>
                                <tenant-multi-users-change-client class="sidebar-multi-user-selector"></tenant-multi-users-change-client>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
    <div class="nano">
        <div class="sidebar-header sidebar-header-mobile">
            <a href="{{route('tenant.dashboard.index')}}" class="logo pt-2 pt-md-0 logo-container-sidebar">
                @if($vc_company->logo)
                    <img src="{{ asset('storage/uploads/logos/' . $vc_company->logo) }}" alt="Logo" class="logo-light"
                        style="{{ $vc_company->logo_dark ? '' : '--show-light-logo: block;' }}" />
                @else
                    <img src="{{ asset('logo/tulogo.png') }}" alt="Logo" />
                @endif

                @if($vc_company->logo_dark)
                    <img src="{{ asset('storage/uploads/logos/' . $vc_company->logo_dark) }}" alt="Logo"
                        class="logo-dark" />
                @endif
            </a>
            <div class="d-md-none toggle-sidebar-left" data-toggle-class="sidebar-left-opened" data-target="html"
                data-fire-event="sidebar-left-opened">
                <i class="fas fa-times"></i>
            </div>
        </div>
        <div class="nano-content nano-content-mobile pt-0">
            <nav id="menu" class="nav-main" role="navigation">
                <section id="sidebar-favorites" class="sidebar-favorites" aria-label="Accesos favoritos">
                    <div class="sidebar-favorites-header">
                        <span class="sidebar-favorites-title">
                            <i class="fas fa-thumbtack" aria-hidden="true"></i>
                            <span>Favoritos</span>
                            <button id="sidebar-search-trigger" type="button" class="sidebar-search-trigger" title="Buscar opciones (Ctrl + K)" aria-label="Abrir buscador de opciones">
                                <i class="fas fa-search" aria-hidden="true"></i>
                            </button>
                        </span>
                        <label class="sidebar-compact-toggle" title="Ocultar el menú principal y mostrar solo favoritos">
                            <input id="sidebar-show-only-active" type="checkbox">
                            <span class="sidebar-switch" aria-hidden="true"></span>
                            <span>Mostrar solo activos</span>
                        </label>
                    </div>
                    <ul id="sidebar-pinned-items" class="nav nav-main sidebar-pinned-items"></ul>
                    <p id="sidebar-pinned-empty" class="sidebar-pinned-empty mb-0">
                        Pasa el cursor por una opción y pulsa el pin para agregarla.
                    </p>
                    <small id="sidebar-preferences-status" class="sidebar-preferences-status" role="status" aria-live="polite"></small>
                    <button id="sidebar-preferences-retry" type="button" class="sidebar-preferences-retry">
                        <i class="fas fa-sync-alt" aria-hidden="true"></i>
                        Reintentar sincronización
                    </button>
                </section>
                <div id="sidebar-advanced-search" class="sidebar-search-overlay" aria-hidden="true">
                    <div class="sidebar-search-dialog" role="dialog" aria-modal="true" aria-labelledby="sidebar-search-title">
                        <div class="sidebar-search-heading">
                            <div>
                                <strong id="sidebar-search-title">Buscar en el sistema</strong>
                                <small>Encuentra módulos, opciones y acciones por nombre o sinónimos.</small>
                            </div>
                            <button id="sidebar-search-close" type="button" aria-label="Cerrar buscador">
                                <i class="fas fa-times" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div class="sidebar-search-input-wrap">
                            <i class="fas fa-search" aria-hidden="true"></i>
                            <input id="sidebar-search-input" type="search" autocomplete="off" placeholder="Ej. sucursal, almacén, ventas..." aria-controls="sidebar-search-results">
                            <kbd>Esc</kbd>
                        </div>
                        <div id="sidebar-search-summary" class="sidebar-search-summary">Escribe para buscar entre las opciones disponibles.</div>
                        <div id="sidebar-search-results" class="sidebar-search-results" role="listbox"></div>
                    </div>
                </div>
                <ul class="nav nav-main nav-main-mobile">
                    @if(in_array('dashboard', $vc_modules))
                        <li class="{{ ($firstLevel === 'dashboard') ? 'nav-active' : '' }}">
                            <a class="nav-link dashboard-link" href="{{ route('tenant.dashboard.index') }}">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                    stroke-linejoin="round"
                                    class="icon icon-tabler icons-tabler-outline icon-tabler-dashboard">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M12 13m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                    <path d="M13.45 11.55l2.05 -2.05" />
                                    <path d="M6.4 20a9 9 0 1 1 11.2 0z" />
                                </svg>
                                <span>Dashboard</span>
                            </a>
                        </li>
                    @endif

                    {{-- Preventas --}}
                    @if(in_array('preventa', $vc_modules))
                        <li
                            class="
                                                                                                                                            nav-parent
                                                                                                                                            {{ ($firstLevel === 'quotations') ? 'nav-active nav-expanded' : '' }}
                                                                                                                                            {{ ($firstLevel === 'order-notes') ? 'nav-active nav-expanded' : '' }}
                                                                                                                                            {{ ($firstLevel === 'sale-opportunities') ? 'nav-active nav-expanded' : '' }}
                                                                                                                                            {{ ($firstLevel === 'contracts') ? 'nav-active nav-expanded' : '' }}
                                                                                                                                            {{ ($firstLevel === 'production-orders') ? 'nav-active nav-expanded' : '' }}
                                                                                                                                            {{ ($firstLevel === 'technical-services') ? 'nav-active nav-expanded' : '' }}
                                                                                                                                                ">
                            <a class="nav-link" href="#">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                    stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-edit">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1" />
                                    <path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z" />
                                    <path d="M16 5l3 3" />
                                </svg>
                                <span>Preventa</span>
                            </a>
                            <ul class="nav nav-children" style="">
                                @if(in_array('sale-opportunity', $vc_module_levels))
                                    <li class="{{ ($firstLevel === 'sale-opportunities') ? 'nav-active' : '' }}">
                                        <a class="nav-link" href="{{route('tenant.sale_opportunities.index')}}">
                                            Oportunidad de venta
                                        </a>
                                    </li>
                                @endif

                                @if(in_array('quotations', $vc_module_levels))
                                    <li class="{{ ($firstLevel === 'quotations') ? 'nav-active' : '' }} nav-item-with-action">
                                        <a class="nav-link pe-5" href="{{ route('tenant.quotations.index') }}">
                                            Cotizaciones
                                        </a>
                                        <button
                                            type="button"
                                            class="{{ ($firstLevel === 'quotations') ? 'second-buton' : 'btn-primary' }} btn btn-xs nav-action m-0 py-0"
                                            title="Crear cotización"
                                            onclick="event.preventDefault(); event.stopPropagation(); window.location.href='{{ route('tenant.quotations.create') }}';"
                                        >
                                            Crear
                                        </button>
                                    </li>
                                @endif

                                @if(in_array('contracts', $vc_module_levels))
                                    <li class="{{ ($firstLevel === 'contracts') ? 'nav-active' : '' }}">
                                        <a class="nav-link" href="{{ route('tenant.contracts.index') }}">
                                            Contratos
                                        </a>
                                    </li>
                                @endif

                                @if(in_array('order-note', $vc_module_levels))
                                    <li class="{{ ($firstLevel === 'order-notes') ? 'nav-active' : '' }}">
                                        <a class="nav-link" href="{{route('tenant.order_notes.index')}}">
                                            Pedidos
                                        </a>
                                    </li>
                                @endif

                                @if(in_array('technical-service', $vc_module_levels))
                                    <li class="{{ ($firstLevel === 'technical-services') ? 'nav-active' : '' }}">
                                        <a class="nav-link" href="{{route('tenant.technical_services.index')}}">
                                            Servicio de soporte técnico
                                        </a>
                                    </li>
                                @endif
                            </ul>
                        </li>
                    @endif

                    {{-- Ventas --}}
                    @if(in_array('documents', $vc_modules))
                        <li
                            class="
                                                                                                                                            nav-parent
                                                                                                                                            {{ ($firstLevel === 'documents' && $secondLevel !== 'create' && $secondLevel !== 'not-sent' && $secondLevel !== 'regularize-shipping') ? 'nav-active nav-expanded' : '' }}
                                                                                                                                            {{ ($firstLevel === 'documents' && $secondLevel === 'create') ? 'nav-active nav-expanded' : '' }}
                                                                                                                                            {{ ($firstLevel === 'sale-notes') ? 'nav-active nav-expanded' : '' }}
                                                                                                                                            {{ ($firstLevel === 'regularize-shipping') ? 'nav-active nav-expanded' : '' }}
                                                                                                                                            {{ ($firstLevel === 'pos') ? 'nav-active nav-expanded' : '' }}
                                                                                                                                                ">
                            <a class="nav-link" href="#">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-receipt-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 21v-16a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v16l-3 -2l-2 2l-2 -2l-2 2l-2 -2l-3 2" /><path d="M14 8h-2.5a1.5 1.5 0 0 0 0 3h1a1.5 1.5 0 0 1 0 3h-2.5m2 0v1.5m0 -9v1.5" /></svg>
                                <span>Ventas</span>
                            </a>
                            <ul class="nav nav-children" style="">
                                {{-- @if(auth()->user()->type != 'integrator' && $vc_company->soap_type_id != '03')
                                    @if(in_array('documents', $vc_modules))
                                        @if(in_array('new_document', $vc_module_levels))
                                            <li
                                                class="{{ ($firstLevel === 'documents' && $secondLevel === 'create') ? 'nav-active' : '' }}">
                                                <a class="nav-link" href="{{route('tenant.documents.create')}}">Nuevo Comprobante</a>
                                            </li>
                                        @endif
                                    @endif
                                @endif --}}

                                @if(in_array('documents', $vc_modules) && $vc_company->soap_type_id != '03')
                                    @if(in_array('list_document', $vc_module_levels))
                                        <li
                                            class="{{ ($firstLevel === 'documents' && $secondLevel != 'create' && $secondLevel != 'not-sent' && $secondLevel != 'regularize-shipping') ? 'nav-active' : '' }} {{ ($firstLevel === 'documents' && $secondLevel === 'create') ? 'nav-active' : '' }} nav-item-with-action">
                                            <a class="nav-link pe-5" href="{{route('tenant.documents.index')}}">Boleta/factura</a>
                                            <button
                                                type="button"
                                                class="{{ ($firstLevel === 'documents') ? 'second-buton' : 'btn-primary' }} btn btn-xs nav-action m-0 py-0"
                                                title="Crear comprobante"
                                                onclick="event.preventDefault(); event.stopPropagation(); window.location.href='{{ route('tenant.documents.create') }}';"
                                            >
                                                Crear
                                            </button>
                                        </li>
                                    @endif
                                @endif

                                @if(in_array('sale_notes', $vc_module_levels))
                                    <li class="{{ ($firstLevel === 'sale-notes') ? 'nav-active' : '' }} nav-item-with-action">
                                        <a class="nav-link pe-5" href="{{route('tenant.sale_notes.index')}}">Notas de Venta</a>
                                        <button
                                            type="button"
                                            class="{{ ($firstLevel === 'sale-notes') ? 'second-buton' : 'btn-primary' }} btn btn-xs nav-action m-0 py-0"
                                            title="Crear nota de venta"
                                            onclick="event.preventDefault(); event.stopPropagation(); window.location.href='{{ route('tenant.sale_notes.create') }}';"
                                        >
                                            Crear
                                        </button>
                                    </li>
                                @endif

                                @if(in_array('pos', $vc_module_levels))
                                    <li class="{{ ($firstLevel === 'pos' && !$secondLevel) ? 'nav-active' : '' }}">
                                        <a class="nav-link" href="{{ route('tenant.pos.index') }}">Punto de venta</a>
                                    </li>
                                @endif

                                {{-- Venta Rápida --}}
                                @if(in_array('pos_garage', $vc_module_levels))
                                @php
                                    $business_turns_active = \Modules\BusinessTurn\Models\BusinessTurn::where('id', 4)->value('active');
                                @endphp
                                    <li class="{{ ($firstLevel === 'pos' && $secondLevel === 'garage') ? 'nav-active' : '' }} nav-item-with-action">
                                        <a class="nav-link" href="{{ route('tenant.pos.garage') }}">Venta rápida
                                            @if($business_turns_active)
                                                <span style="font-size:.65rem;">(Grifos y Markets)</span>
                                            @endif
                                        </a>
                                        <button
                                            type="button"
                                            class="{{ ($firstLevel === 'quotations') ? 'second-buton' : 'btn-primary' }} btn btn-xs nav-action m-0 py-0 ms-1"
                                            title="Vendeya"
                                            onclick="openVendeyaApp('{{ auth()->user()->api_token ?? '' }}')">Vendeya
                                        </button>
                                    </li>
                                @endif

                            </ul>
                        </li>
                    @endif

                    @if(auth()->user()->type != 'integrator')
                                    @if(in_array('purchases', $vc_modules))
                                                    <li
                                                        class="
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            nav-parent
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            {{ (
                                            $firstLevel === 'purchases' ||
                                            ($firstLevel === 'persons' && $secondLevel === 'suppliers') ||
                                            $firstLevel === 'expenses' ||
                                            $firstLevel === 'purchase-quotations' ||
                                            $firstLevel === 'purchase-orders' ||
                                            $firstLevel === 'fixed-asset'
                                        ) ? 'nav-active nav-expanded' : '' }}
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                ">
                                                        <a class="nav-link" href="#">
                                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                                                fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                                                stroke-linejoin="round"
                                                                class="icon icon-tabler icons-tabler-outline icon-tabler-shopping-bag">
                                                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                                <path
                                                                    d="M6.331 8h11.339a2 2 0 0 1 1.977 2.304l-1.255 8.152a3 3 0 0 1 -2.966 2.544h-6.852a3 3 0 0 1 -2.965 -2.544l-1.255 -8.152a2 2 0 0 1 1.977 -2.304z" />
                                                                <path d="M9 11v-5a3 3 0 0 1 6 0v5" />
                                                            </svg>
                                                            <span>Compras</span>
                                                        </a>
                                                        <ul class="nav nav-children">
                                                            {{-- @if(in_array('purchases_create', $vc_module_levels))
                                                                <li
                                                                    class="{{ ($firstLevel === 'purchases' && $secondLevel === 'create') ? 'nav-active' : '' }}">
                                                                    <a class="nav-link" href="{{route('tenant.purchases.create')}}">Nuevo</a>
                                                                </li>
                                                            @endif --}}
                                                            @if(in_array('purchases_list', $vc_module_levels))
                                                                <li
                                                                    class="{{ ($firstLevel === 'purchases' && $secondLevel != 'create') ? 'nav-active' : '' }} {{ ($firstLevel === 'purchases' && $secondLevel === 'create') ? 'nav-active' : '' }} nav-item-with-action">
                                                                    <a class="nav-link pe-5" href="{{route('tenant.purchases.index')}}">Listado</a>
                                                                    <button
                                                                        type="button"
                                                                        class="{{ ($firstLevel === 'purchases') ? 'second-buton' : 'btn-primary' }} btn btn-xs nav-action m-0 py-0"
                                                                        title="Nuevo compra"
                                                                        onclick="event.preventDefault(); event.stopPropagation(); window.location.href='{{ route('tenant.purchases.create') }}';"
                                                                    >
                                                                        Crear
                                                                    </button>
                                                                </li>
                                                            @endif
                                                            @if(in_array('purchases_orders', $vc_module_levels))
                                                                <li class="{{ ($firstLevel === 'purchase-orders') ? 'nav-active' : '' }} nav-item-with-action">
                                                                    <a class="nav-link pe-5" href="{{route('tenant.purchase-orders.index')}}">Ord. de compra</a>
                                                                    <button
                                                                        type="button"
                                                                        class="{{ ($firstLevel === 'purchase-orders') ? 'second-buton' : 'btn-primary' }} btn btn-xs nav-action m-0 py-0"
                                                                        title="Nueva orden de compra"
                                                                        onclick="event.preventDefault(); event.stopPropagation(); window.location.href='{{ route('tenant.purchase-orders.create') }}';"
                                                                    >
                                                                        Crear
                                                                    </button>
                                                                </li>
                                                            @endif

                                                            @if(in_array('purchases_expenses', $vc_module_levels))
                                                                <li class="{{ ($firstLevel === 'expenses') ? 'nav-active' : '' }} nav-item-with-action">
                                                                    <a class="nav-link pe-5" href="{{route('tenant.expenses.index')}}">Gastos diversos</a>
                                                                    <button
                                                                        type="button"
                                                                        class="{{ ($firstLevel === 'expenses') ? 'second-buton' : 'btn-primary' }} btn btn-xs nav-action m-0 py-0"
                                                                        title="Nuevo gasto diverso"
                                                                        onclick="event.preventDefault(); event.stopPropagation(); window.location.href='{{ route('tenant.expenses.create') }}';"
                                                                    >
                                                                        Crear
                                                                    </button>
                                                                </li>
                                                            @endif
                                                            @if(in_array('purchases_suppliers', $vc_module_levels))
                                                                <li class="{{ ($firstLevel === 'persons') ? 'nav-active' : '' }}">
                                                                    <a class="nav-link" href="{{route('tenant.persons.index', ['type' => 'suppliers'])}}">
                                                                        Proveedores
                                                                    </a>
                                                                </li>
                                                            @endif
                                                            @if(in_array('purchases_quotations', $vc_module_levels))
                                                                <li class="{{ ($firstLevel === 'purchase-quotations') ? 'nav-active' : '' }} nav-item-with-action">
                                                                    <a class="nav-link pe-5" href="{{route('tenant.purchase-quotations.index')}}">
                                                                        Solicitar cotización
                                                                    </a>
                                                                    <button
                                                                        type="button"
                                                                        class="{{ ($firstLevel === 'purchase-quotations') ? 'second-buton' : 'btn-primary' }} btn btn-xs nav-action m-0 py-0"
                                                                        title="Nueva solicitud de cotización"
                                                                        onclick="event.preventDefault(); event.stopPropagation(); window.location.href='{{ route('tenant.purchase-quotations.create') }}';"
                                                                    >
                                                                        Crear
                                                                    </button>
                                                                </li>
                                                            @endif
                                                            @if(in_array('purchases_fixed_assets_items', $vc_module_levels))
                                                                <li
                                                                    class="{{ ($firstLevel === 'fixed-asset' && $secondLevel === 'items') ? 'nav-active' : '' }}">
                                                                    <a class="nav-link" href="{{ route('tenant.fixed_asset_items.index') }}">Activos
                                                                        fijos</a>
                                                                </li>
                                                            @endif
                                                            @if(in_array('purchases_fixed_assets_purchases', $vc_module_levels))
                                                                <li
                                                                    class="{{ ($firstLevel === 'fixed-asset' && $secondLevel === 'purchases') ? 'nav-active' : '' }} nav-item-with-action">
                                                                    <a class="nav-link pe-5" href="{{ route('tenant.fixed_asset_purchases.index') }}">Comprar activo fijo</a>
                                                                    <button
                                                                        type="button"
                                                                        class="{{ ($firstLevel === 'fixed-asset' && $secondLevel === 'purchases') ? 'second-buton' : 'btn-primary' }} btn btn-xs nav-action m-0 py-0"
                                                                        title="Nueva compra de activo fijo"
                                                                        onclick="event.preventDefault(); event.stopPropagation(); window.location.href='{{ route('tenant.fixed_asset_purchases.create') }}';"
                                                                    >
                                                                        Crear
                                                                    </button>
                                                                </li>
                                                            @endif
                                                        </ul>
                                                    </li>
                                    @endif

                                    {{-- Clientes --}}
                                    @if(in_array('persons', $vc_modules))
                                        <li
                                            class="nav-parent
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            {{ ($firstLevel === 'persons' && $secondLevel === 'customers') ? 'nav-active nav-expanded' : '' }}
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            {{ $firstLevel === 'person-types' ? 'nav-active nav-expanded' : '' }}
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            {{ $firstLevel === 'agents' ? 'nav-active nav-expanded' : '' }}
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                ">
                                            <a class="nav-link" href="#">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                                    fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    class="icon icon-tabler icons-tabler-outline icon-tabler-users-group">
                                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                    <path d="M10 13a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" />
                                                    <path d="M8 21v-1a2 2 0 0 1 2 -2h4a2 2 0 0 1 2 2v1" />
                                                    <path d="M15 5a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" />
                                                    <path d="M17 10h2a2 2 0 0 1 2 2v1" />
                                                    <path d="M5 5a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" />
                                                    <path d="M3 13v-1a2 2 0 0 1 2 -2h2" />
                                                </svg>
                                                <span>Clientes</span>
                                            </a>
                                            <ul class="nav nav-children">
                                                @if(in_array('clients', $vc_module_levels))
                                                    <li
                                                        class="{{ ($firstLevel === 'persons' && $secondLevel === 'customers') ? 'nav-active' : '' }} nav-item-with-action">
                                                        <a class="nav-link pe-5"
                                                            href="{{route('tenant.persons.index', ['type' => 'customers'])}}">Clientes</a>
                                                        <button
                                                            type="button"
                                                            class="{{ ($firstLevel === 'persons' && $secondLevel === 'customers') ? 'second-buton' : 'btn-primary' }} btn btn-xs nav-action m-0 py-0"
                                                            title="Crear cliente"
                                                            onclick="event.preventDefault(); event.stopPropagation(); window.location.href='{{ route('tenant.persons.index', ['type' => 'customers', 'create' => 1]) }}';"
                                                        >
                                                            Crear
                                                        </button>
                                                    </li>
                                                @endif
                                                @if(in_array('clients_types', $vc_module_levels))
                                                    <li class="{{ ($firstLevel === 'person-types') ? 'nav-active' : '' }}">
                                                        <a class="nav-link" href="{{route('tenant.person_types.index')}}">Tipos de clientes</a>
                                                    </li>
                                                @endif

                                                @if($configuration->enabled_sales_agents)
                                                    <li class="{{ ($firstLevel === 'agents') ? 'nav-active' : '' }}">
                                                        <a class="nav-link" href="{{route('tenant.agents.index')}}">Agentes</a>
                                                    </li>
                                                @endif

                                            </ul>
                                        </li>
                                    @endif



                                    {{-- Productos --}}
                                    @if(in_array('items', $vc_modules))
                                        <li
                                            class="nav-parent
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            {{ ($firstLevel === 'items') ? 'nav-active nav-expanded' : '' }}
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            {{ ($firstLevel === 'services') ? 'nav-active nav-expanded' : '' }}
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            {{ ($firstLevel === 'categories') ? 'nav-active nav-expanded' : '' }}
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            {{ ($firstLevel === 'brands') ? 'nav-active nav-expanded' : '' }}
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            {{ ($firstLevel === 'item-lots') ? 'nav-active nav-expanded' : '' }}
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            {{ ($firstLevel === 'item-sets') ? 'nav-active nav-expanded' : '' }}
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                ">
                                            <a class="nav-link" href="#">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                                    fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    class="icon icon-tabler icons-tabler-outline icon-tabler-category-2">
                                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                    <path d="M14 4h6v6h-6z" />
                                                    <path d="M4 14h6v6h-6z" />
                                                    <path d="M17 17m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" />
                                                    <path d="M7 7m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" />
                                                </svg>
                                                <span>Productos/Servicios</span>
                                            </a>
                                            <ul class="nav nav-children">
                                                @if(in_array('items', $vc_module_levels))
                                                    <li class="{{ ($firstLevel === 'items') ? 'nav-active' : '' }}">
                                                        <a class="nav-link" href="{{route('tenant.items.index')}}">Productos</a>
                                                    </li>
                                                @endif
                                                @if(in_array('items_packs', $vc_module_levels))
                                                    <li class="{{ ($firstLevel === 'item-sets') ? 'nav-active' : '' }}">
                                                        <a class="nav-link"
                                                            href="{{route('tenant.item_sets.index')}}">Conjuntos y Packs</a>
                                                    </li>
                                                @endif
                                                @if(in_array('items_services', $vc_module_levels))
                                                    <li class="{{ ($firstLevel === 'services') ? 'nav-active' : '' }}">
                                                        <a class="nav-link" href="{{route('tenant.services')}}">Servicios</a>
                                                    </li>
                                                @endif
                                                @if(in_array('items_categories', $vc_module_levels))
                                                    <li class="{{ ($firstLevel === 'categories') ? 'nav-active' : '' }}">
                                                        <a class="nav-link" href="{{route('tenant.categories.index')}}">Categorías</a>
                                                    </li>
                                                @endif
                                                @if(in_array('items_brands', $vc_module_levels))
                                                    <li class="{{ ($firstLevel === 'brands') ? 'nav-active' : '' }}">
                                                        <a class="nav-link" href="{{route('tenant.brands.index')}}">Marcas</a>
                                                    </li>
                                                @endif
                                                @if(in_array('items_lots', $vc_module_levels))
                                                    <li class="{{ ($firstLevel === 'item-lots') ? 'nav-active' : '' }}">
                                                        <a class="nav-link" href="{{route('tenant.item-lots.index')}}">Series</a>
                                                    </li>
                                                @endif

                                                <!-- <li class="{{ ($firstLevel === 'zones')?'nav-active':'' }}">
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    <a class="nav-link"
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        href="{{route('tenant.zone.index')}}">Zonas</a>
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                </li> -->

                                            </ul>
                                        </li>
                                    @endif


                                    {{-- Inventario --}}
                                    @if(in_array('inventory', $vc_modules))
                                        <li
                                            class="nav-parent
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                {{ (in_array($firstLevel, ['inventory', 'moves', 'transfers', 'devolutions', 'extra_info_items', 'inventory-review']) | ($firstLevel === 'reports' && in_array($secondLevel, ['kardex', 'inventory', 'valued-kardex']))) ? 'nav-active nav-expanded' : '' }}
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    ">
                                            <a class="nav-link" href="#">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                                    fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    class="icon icon-tabler icons-tabler-outline icon-tabler-building-warehouse">
                                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                    <path d="M3 21v-13l9 -4l9 4v13" />
                                                    <path d="M13 13h4v8h-10v-6h6" />
                                                    <path d="M13 21v-9a1 1 0 0 0 -1 -1h-2a1 1 0 0 0 -1 1v3" />
                                                </svg>
                                                <span>Inventario</span>
                                            </a>
                                            <ul class="nav nav-children">
                                                @if(in_array('inventory', $vc_module_levels))
                                                    <li class="{{ ($firstLevel === 'inventory') ? 'nav-active' : '' }}">
                                                        <a class="nav-link" href="{{route('inventory.index')}}">Movimientos</a>
                                                    </li>
                                                @endif
                                                @if($showTransfer)
                                                    <li class="{{ ($firstLevel === 'transfers') ? 'nav-active' : '' }}">
                                                        <a class="nav-link" href="{{route('transfers.index')}}">Traslados</a>
                                                    </li>
                                                @endif
                                                @if(in_array('inventory_devolutions', $vc_module_levels))
                                                    <li class="{{ ($firstLevel === 'devolutions') ? 'nav-active' : '' }}">
                                                        <a class="nav-link" href="{{route('devolutions.index')}}">Devolucion a proveedor</a>
                                                    </li>
                                                @endif
                                                @if(in_array('inventory_report_kardex', $vc_module_levels))
                                                    <li
                                                        class="{{(($firstLevel === 'reports') && ($secondLevel === 'kardex')) ? 'nav-active' : ''}}">
                                                        <a class="nav-link" href="{{route('reports.kardex.index')}}">Reporte Kardex</a>
                                                    </li>
                                                @endif
                                                @if(in_array('inventory_report', $vc_module_levels))
                                                    <li
                                                        class="{{(($firstLevel === 'reports') && ($secondLevel == 'inventory')) ? 'nav-active' : ''}}">
                                                        <a class="nav-link" href="{{route('reports.inventory.index')}}">Reporte Inventario</a>
                                                    </li>
                                                @endif
                                                @if(in_array('inventory_report_valued_kardex', $vc_module_levels))
                                                    {{-- <li class="{{ ($firstLevel === 'warehouses')?'nav-active':'' }}">
                                                        <a class="nav-link" href="{{route('warehouses.index')}}">Almacenes</a>
                                                    </li> --}}
                                                    <li
                                                        class="{{(($firstLevel === 'reports') && ($secondLevel === 'valued-kardex')) ? 'nav-active' : ''}}">
                                                        <a class="nav-link" href="{{route('reports.valued_kardex.index')}}">Kardex
                                                            valorizado</a>
                                                    </li>
                                                @endif
                                                <!-- @if(in_array('production_app', $vc_modules) && $configuration->isShowExtraInfoToItem())
                                                    <li class="{{($firstLevel === 'extra_info_items') ? 'nav-active' : ''}}">
                                                        <a class="nav-link" href="{{route('extra_info_items.index')}}">Datos extra de items</a>
                                                    </li>
                                                @endif -->
                                                @if($inventory_configuration->inventory_review)
                                                    <li class="{{ ($firstLevel === 'inventory-review') ? 'nav-active' : '' }}">
                                                        <a class="nav-link" href="{{route('tenant.inventory-review.index')}}">Revisión de
                                                            inventario</a>
                                                    </li>
                                                @endif
                                            </ul>
                                        </li>
                                    @endif

                    @endif

                    @if(in_array('finance', $vc_modules))

                                        <li
                                            class="nav-parent {{ $firstLevel === 'finances' && in_array($secondLevel, [
                            'global-payments',
                            'balance',
                            'payment-method-types',
                            'unpaid',
                            'to-pay',
                            'income',
                            'transactions',
                            'movements'
                        ]) ? 'nav-active nav-expanded' : ''}}
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    {{ ($firstLevel === 'cash') ? 'nav-active nav-expanded' : '' }}
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    {{ ($firstLevel === 'bank_loan') ? 'nav-active nav-expanded' : '' }}">
                                            <a class="nav-link" href="#">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                                    fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    class="icon icon-tabler icons-tabler-outline icon-tabler-calculator">
                                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                    <path
                                                        d="M4 3m0 2a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v14a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2z" />
                                                    <path
                                                        d="M8 7m0 1a1 1 0 0 1 1 -1h6a1 1 0 0 1 1 1v1a1 1 0 0 1 -1 1h-6a1 1 0 0 1 -1 -1z" />
                                                    <path d="M8 14l0 .01" />
                                                    <path d="M12 14l0 .01" />
                                                    <path d="M16 14l0 .01" />
                                                    <path d="M8 17l0 .01" />
                                                    <path d="M12 17l0 .01" />
                                                    <path d="M16 17l0 .01" />
                                                </svg>
                                                <span>Finanzas</span>
                                            </a>
                                            <ul class="nav nav-children">
                                                @if(in_array('cash', $vc_module_levels))
                                                    <li class="{{ ($firstLevel === 'cash') ? 'nav-active' : '' }}">
                                                        <a class="nav-link" href="{{route('tenant.cash.index')}}">Caja general</a>
                                                    </li>
                                                @endif
                                                @if(in_array('finances_movements', $vc_module_levels))
                                                    <li
                                                        class="{{(($firstLevel === 'finances') && ($secondLevel == 'movements')) ? 'nav-active' : ''}}">
                                                        <a class="nav-link" href="{{route('tenant.finances.movements.index')}}">Movimientos</a>
                                                    </li>
                                                @endif
                                                @if(in_array('finances_movements', $vc_module_levels))
                                                    <li
                                                        class="{{(($firstLevel === 'finances') && ($secondLevel == 'transactions')) ? 'nav-active' : ''}}">
                                                        <a class="nav-link"
                                                            href="{{route('tenant.finances.transactions.index')}}">Transacciones</a>
                                                    </li>
                                                @endif
                                                @if(in_array('finances_incomes', $vc_module_levels))
                                                    <li
                                                        class="{{(($firstLevel === 'finances') && ($secondLevel == 'income')) ? 'nav-active' : ''}}">
                                                        <a class="nav-link" href="{{route('tenant.finances.income.index')}}">Ingresos</a>
                                                    </li>
                                                @endif
                                                @if(in_array('finances_unpaid', $vc_module_levels))
                                                    <li
                                                        class="{{(($firstLevel === 'finances') && ($secondLevel == 'unpaid')) ? 'nav-active' : ''}}">
                                                        <a class="nav-link" href="{{route('tenant.finances.unpaid.index')}}">Cuentas por
                                                            cobrar</a>
                                                    </li>
                                                @endif
                                                @if(in_array('finances_to_pay', $vc_module_levels))
                                                    <li
                                                        class="{{(($firstLevel === 'finances') && ($secondLevel == 'to-pay')) ? 'nav-active' : ''}}">
                                                        <a class="nav-link" href="{{route('tenant.finances.to_pay.index')}}">Cuentas por
                                                            pagar</a>
                                                    </li>
                                                @endif
                                                @if(in_array('finances_payments', $vc_module_levels))
                                                    <li
                                                        class="{{(($firstLevel === 'finances') && ($secondLevel == 'global-payments')) ? 'nav-active' : ''}}">
                                                        <a class="nav-link" href="{{route('tenant.finances.global_payments.index')}}">Pagos</a>
                                                    </li>
                                                @endif
                                                {{-- @if(in_array('finances_balance', $vc_module_levels))
                                                    <li
                                                        class="{{(($firstLevel === 'finances') && ($secondLevel == 'balance')) ? 'nav-active' : ''}}">
                                                        <a class="nav-link" href="{{route('tenant.finances.balance.index')}}">Balance</a>
                                                    </li>
                                                @endif --}}
                                                @if(in_array('finances_payment_method_types', $vc_module_levels))
                                                    <li
                                                        class="{{(($firstLevel === 'finances') && ($secondLevel == 'payment-method-types')) ? 'nav-active' : ''}}">
                                                        <a class="nav-link"
                                                            href="{{route('tenant.finances.payment_method_types.index')}}">Ingresos y
                                                            Egresos - M. Pago</a>
                                                    </li>
                                                @endif
                                                @if(in_array('bank_loan', $vc_module_levels))
                                                    <li class="{{ ($firstLevel === 'bank_loan') ? 'nav-active' : '' }}">
                                                        <a class="nav-link" href="{{route('tenant.bank_loan.index')}}">Credito Bancario</a>
                                                    </li>
                                                @endif
                                            </ul>
                                        </li>
                    @endif

                    @if(in_array('guia', $vc_modules) && $vc_company->soap_type_id != '03')
                        <li
                            class="nav-parent
                                                                                                                                                    {{ ($firstLevel === 'dispatches') ? 'nav-active nav-expanded' : '' }}
                                                                                                                                                    {{ ($firstLevel === 'drivers') ? 'nav-active nav-expanded' : '' }}
                                                                                                                                                    {{ ($firstLevel === 'dispatchers') ? 'nav-active nav-expanded' : '' }}
                                                                                                                                                    {{ ($firstLevel === 'transports') ? 'nav-active nav-expanded' : '' }}
                                                                                                                                                    {{ ($firstLevel === 'dispatch_carrier') ? 'nav-active nav-expanded' : '' }}
                                                                                                                                                    {{ ($firstLevel === 'dispatch_addresses') ? 'nav-active nav-expanded' : '' }}">
                            <a class="nav-link" href="#">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                    stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-truck">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M7 17m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                    <path d="M17 17m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                    <path d="M5 17h-2v-11a1 1 0 0 1 1 -1h9v12m-4 0h6m4 0h2v-6h-8m0 -5h5l3 5" />
                                </svg>
                                <span>Guías de remisión</span>
                            </a>
                            <ul class="nav nav-children" style="">
                                @if(in_array('dispatches', $vc_module_levels))
                                    <li class="{{ ($firstLevel === 'dispatches') ? 'nav-active' : '' }} nav-item-with-action">
                                        <a class="nav-link pe-5" href="{{route('tenant.dispatches.index')}}">G.R. Remitente</a>
                                        <button
                                            type="button"
                                            class="{{ ($firstLevel === 'dispatches') ? 'second-buton' : 'btn-primary' }} btn btn-xs nav-action m-0 py-0"
                                            title="Nueva guía de remisión"
                                            onclick="event.preventDefault(); event.stopPropagation(); window.location.href='{{ url('dispatches/create') }}';"
                                        >
                                            Crear
                                        </button>
                                    </li>
                                @endif
                                @if(in_array('dispatch_carrier', $vc_module_levels))
                                    <li class="{{ ($firstLevel === 'dispatch_carrier') ? 'nav-active' : '' }} nav-item-with-action">
                                        <a class="nav-link pe-5" href="{{route('tenant.dispatch_carrier.index')}}">G.R. Transportista</a>
                                        <button
                                            type="button"
                                            class="{{ ($firstLevel === 'dispatch_carrier') ? 'second-buton' : 'btn-primary' }} btn btn-xs nav-action m-0 py-0"
                                            title="Nueva guía de remisión"
                                            onclick="event.preventDefault(); event.stopPropagation(); window.location.href='{{ url('dispatch_carrier/create') }}';"
                                        >
                                            Crear
                                        </button>
                                    </li>
                                @endif
                                @if(in_array('dispatchers', $vc_module_levels))
                                    <li class="{{ ($firstLevel === 'dispatchers') ? 'nav-active' : '' }}">
                                        <a class="nav-link" href="{{route('tenant.dispatchers.index')}}">Transportistas</a>
                                    </li>
                                @endif
                                @if(in_array('drivers', $vc_module_levels))
                                    <li class="{{ ($firstLevel === 'drivers') ? 'nav-active' : '' }}">
                                        <a class="nav-link" href="{{route('tenant.drivers.index')}}">Conductores</a>
                                    </li>
                                @endif
                                @if(in_array('transports', $vc_module_levels))
                                    <li class="{{ ($firstLevel === 'transports') ? 'nav-active' : '' }}">
                                        <a class="nav-link" href="{{route('tenant.transports.index')}}">Vehículos</a>
                                    </li>
                                @endif
                                {{--
                                @if(in_array('origin_addresses', $vc_module_levels))
                                <li class="{{ ($firstLevel === 'origin_addresses')?'nav-active':'' }}">
                                    <a class="nav-link" href="{{route('tenant.origin_addresses.index')}}">Direcciones de
                                        partida</a>
                                </li>
                                @endif
                                @if(in_array('dispatch_addresses', $vc_module_levels))
                                <li class="{{ ($firstLevel === 'dispatch_addresses')?'nav-active':'' }}">
                                    <a class="nav-link" href="{{route('tenant.dispatch-addresses.index')}}">Direcciones de
                                        llegada</a>
                                </li>
                                @endif--}}
                            </ul>
                        </li>
                    @endif

                    @if(in_array('comprobante', $vc_modules))
                        <li
                            class="nav-parent
                                                                                                                                            {{ ($secondLevel === 'not-sent') ? 'nav-active nav-expanded' : '' }}
                                                                                                                                            {{ ($secondLevel === 'regularize-shipping') ? 'nav-active nav-expanded' : '' }}
                                                                                                                                            {{ ($firstLevel === 'summaries') ? 'nav-active nav-expanded' : '' }}
                                                                                                                                            {{ ($firstLevel === 'voided') ? 'nav-active nav-expanded' : '' }}">
                            <a class="nav-link" href="#">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                    stroke-linejoin="round"
                                    class="icon icon-tabler icons-tabler-outline icon-tabler-file-unknown">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M14 3v4a1 1 0 0 0 1 1h4" />
                                    <path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" />
                                    <path d="M12 17v.01" />
                                    <path d="M12 14a1.5 1.5 0 1 0 -1.14 -2.474" />
                                </svg>
                                <span>Comprobantes pendientes</span>
                            </a>
                            <ul class="nav nav-children">
                                @if(in_array('comprobante', $vc_modules) && $vc_company->soap_type_id != '03')
                                    @if(in_array('document_not_sent', $vc_module_levels))
                                        <li
                                            class="{{ ($firstLevel === 'documents' && $secondLevel === 'not-sent') ? 'nav-active' : '' }}">
                                            <a class="nav-link" href="{{route('tenant.documents.not_sent')}}">
                                                Comprobantes no enviados
                                            </a>
                                        </li>
                                    @endif
                                    @if(in_array('regularize_shipping', $vc_module_levels))
                                        <li
                                            class="{{ ($firstLevel === 'documents' && $secondLevel === 'regularize-shipping') ? 'nav-active' : '' }}">
                                            <a class="nav-link" href="{{route('tenant.documents.regularize_shipping')}}">
                                                CPE pendientes de rectificación
                                            </a>
                                        </li>
                                    @endif
                                @endif
                                @if(in_array('summary_voided', $vc_module_levels) && $vc_company->soap_type_id != '03')

                                    <li class="{{ ($firstLevel === 'summaries') ? 'nav-active' : '' }}">
                                        <a class="nav-link text-danger" href="{{route('tenant.summaries.index')}}">
                                            Resúmenes
                                        </a>
                                    </li>
                                    <li class="{{ ($firstLevel === 'voided') ? 'nav-active' : '' }}">
                                        <a class="nav-link text-danger" href="{{route('tenant.voided.index')}}">
                                            Anulaciones
                                        </a>
                                    </li>

                                @endif
                            </ul>
                        </li>
                    @endif


                    @if(in_array('advanced', $vc_modules) && $vc_company->soap_type_id != '03')
                        <li
                            class="nav-parent
                                                                                                                                            {{ ($firstLevel === 'retentions') ? 'nav-active nav-expanded' : '' }}
                                                                                                                                            {{ ($firstLevel === 'perceptions') ? 'nav-active nav-expanded' : '' }}
                                                                                                                                            {{ ($firstLevel === 'order-forms') ? 'nav-active nav-expanded' : '' }}
                                                                                                                                            {{ ($firstLevel === 'contingencies') ? 'nav-active nav-expanded' : '' }}
                                                                                                                                            {{ ($firstLevel === 'purchase-settlements') ? 'nav-active nav-expanded' : '' }}">
                            <a class="nav-link" href="#">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                    stroke-linejoin="round"
                                    class="icon icon-tabler icons-tabler-outline icon-tabler-clipboard-text">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path
                                        d="M9 5h-2a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-12a2 2 0 0 0 -2 -2h-2" />
                                    <path
                                        d="M9 3m0 2a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v0a2 2 0 0 1 -2 2h-2a2 2 0 0 1 -2 -2z" />
                                    <path d="M9 12h6" />
                                    <path d="M9 16h6" />
                                </svg>
                                <span>Comprobantes avanzados</span>
                            </a>
                            <ul class="nav nav-children" style="">
                                @if(in_array('advanced_retentions', $vc_module_levels))
                                    <li class="{{ ($firstLevel === 'retentions') ? 'nav-active' : '' }}">
                                        <a class="nav-link" href="{{route('tenant.retentions.index')}}">Retenciones</a>
                                    </li>
                                @endif
                                @if(in_array('advanced_perceptions', $vc_module_levels))
                                    <li class="{{ ($firstLevel === 'perceptions') ? 'nav-active' : '' }}">
                                        <a class="nav-link" href="{{route('tenant.perceptions.index')}}">Percepciones</a>
                                    </li>
                                @endif
                                @if(in_array('advanced_purchase_settlements', $vc_module_levels))
                                    <li class="{{ ($firstLevel === 'purchase-settlements') ? 'nav-active' : '' }}">
                                        <a class="nav-link" href="{{route('tenant.purchase-settlements.index')}}">Liquidaciones
                                            de
                                            compra</a>
                                    </li>
                                @endif
                                @if(in_array('advanced_order_forms', $vc_module_levels))
                                    <li class="{{ ($firstLevel === 'order-forms') ? 'nav-active' : '' }}">
                                        <a class="nav-link" href="{{route('tenant.order_forms.index')}}">Ordenes de pedido</a>
                                    </li>
                                @endif
                                @if(auth()->user()->type != 'integrator' && in_array('documents', $vc_modules))
                                    @if(auth()->user()->type != 'integrator' && in_array('document_contingengy', $vc_module_levels) && $vc_company->soap_type_id != '03')
                                        <li class="{{ ($firstLevel === 'contingencies') ? 'nav-active' : '' }}">
                                            <a class="nav-link" href="{{route('tenant.contingencies.index')}}">
                                                Documentos de contingencia
                                            </a>
                                        </li>
                                    @endif
                                @endif
                            </ul>
                        </li>
                    @endif

                    @if(in_array('accounting', $vc_modules))
                        <li
                            class="nav-parent {{ ($firstLevel === 'account' || $firstLevel === 'accounting_ledger' || $firstLevel === 'sire') ? 'nav-active nav-expanded' : '' }}">
                            <a class="nav-link" href="#">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                    stroke-linejoin="round"
                                    class="icon icon-tabler icons-tabler-outline icon-tabler-chart-histogram">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M3 3v18h18" />
                                    <path d="M20 18v3" />
                                    <path d="M16 16v5" />
                                    <path d="M12 13v8" />
                                    <path d="M8 16v5" />
                                    <path d="M3 11c6 0 5 -5 9 -5s3 5 9 5" />
                                </svg>
                                <span>Contabilidad</span>
                            </a>
                            <ul class="nav nav-children" style="">
                                @if(in_array('account_report', $vc_module_levels))
                                    <li
                                        class="{{(($firstLevel === 'account') && ($secondLevel === 'format')) ? 'nav-active' : ''}}">
                                        <a class="nav-link" href="{{ route('tenant.account_format.index') }}">Exportar
                                            reporte</a>
                                    </li>
                                @endif
                                @if(in_array('account_formats', $vc_module_levels))
                                    <li class="{{(($firstLevel === 'account') && ($secondLevel == '')) ? 'nav-active' : ''}}">
                                        <a class="nav-link" href="{{ route('tenant.account.index') }}">Exportar formatos - Sis.
                                            Contable</a>
                                    </li>
                                @endif
                                @if(in_array('account_summary', $vc_module_levels))
                                    <li
                                        class="{{(($firstLevel === 'account') && ($secondLevel == 'summary-report')) ? 'nav-active' : ''}}">
                                        <a class="nav-link" href="{{ route('tenant.account_summary_report.index') }}">Reporte
                                            resumido -
                                            Ventas</a>
                                    </li>
                                @endif
                                <li class="{{(($firstLevel === 'accounting_ledger')) ? 'nav-active' : ''}}">
                                    <a class="nav-link" href="{{ route('tenant.accounting_ledger.create') }}">
                                        Libro Mayor
                                    </a>
                                </li>
                                <li class="nav-parent {{ ($firstLevel === 'sire') ? 'nav-active nav-expanded' : '' }}">
                                    <a class="nav-link" href="#">
                                        <span>SIRE</span>
                                    </a>
                                    <ul class="nav nav-children" style="">
                                        <li class="{{ ($secondLevel === 'sale') ? 'nav-active' : '' }}">
                                            <a class="nav-link" href="{{route('tenant.sire.sale')}}">Ventas</a>
                                        </li>
                                        <li class="{{ ($secondLevel === 'purchase') ? 'nav-active' : '' }}">
                                            <a class="nav-link" href="{{route('tenant.sire.purchase')}}">Compras</a>
                                        </li>
                                    </ul>
                                </li>
                            </ul>
                        </li>
                    @endif

                    @if(in_array('reports', $vc_modules))
                        <li
                            class="{{  ($firstLevel === 'reports' && in_array($secondLevel, ['purchases', 'search', 'sales', 'customers', 'items', 'general-items', 'consistency-documents', 'quotations', 'sale-notes', 'cash', 'commissions', 'document-hotels', 'validate-documents', 'document-detractions', 'commercial-analysis', 'order-notes-consolidated', 'order-notes-general', 'sales-consolidated', 'user-commissions', 'fixed-asset-purchases', 'massive-downloads', 'tips'])) ? 'nav-active' : ''}} {{ in_array($firstLevel, ['list-reports', 'system-activity-logs']) ? 'nav-active' : '' }}">
                            {{--
                        <li
                            class="{{  ($firstLevel === 'reports' && in_array($secondLevel, ['purchases', 'search','sales','customers','items', 'general-items','consistency-documents', 'quotations', 'sale-notes','cash','commissions','document-hotels', 'validate-documents', 'document-detractions','commercial-analysis', 'order-notes-consolidated', 'order-notes-general', 'sales-consolidated', 'user-commissions', 'fixed-asset-purchases', 'massive-downloads', 'tips'])) ? 'nav-active' : ''}} {{ $firstLevel === 'list-reports' ? 'nav-active' : '' }}">
                            --}}
                            <a class="nav-link dashboard-link" href="{{ url('list-reports') }}">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                    stroke-linejoin="round"
                                    class="icon icon-tabler icons-tabler-outline icon-tabler-file-analytics">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M14 3v4a1 1 0 0 0 1 1h4" />
                                    <path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" />
                                    <path d="M9 17l0 -5" />
                                    <path d="M12 17l0 -1" />
                                    <path d="M15 17l0 -3" />
                                </svg>
                                <span>Reportes</span>
                            </a>
                        </li>
                    @endif

                    {{-- Tienda virtual --}}
                    @if(in_array('ecommerce', $vc_modules))
                        <li
                            class="nav-parent
                                                                                                                                            {{ in_array($firstLevel, ['ecommerce', 'items_ecommerce', 'tags', 'promotions', 'orders', 'configuration']) ? 'nav-active nav-expanded' : '' }}">
                            <a class="nav-link" href="#">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                    stroke-linejoin="round"
                                    class="icon icon-tabler icons-tabler-outline icon-tabler-shopping-cart">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M6 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                    <path d="M17 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                    <path d="M17 17h-11v-14h-2" />
                                    <path d="M6 5l14 1l-1 7h-13" />
                                </svg>
                                <span>Tienda Virtual</span>
                            </a>
                            <ul class="nav nav-children">
                                @if(in_array('ecommerce_orders', $vc_module_levels))
                                    <li class="{{ ($firstLevel === 'orders') ? 'nav-active' : '' }}">
                                        <a class="nav-link" href="{{route('tenant_orders_index')}}">Pedidos</a>
                                    </li>
                                @endif
                                @if(in_array('ecommerce_items', $vc_module_levels))
                                    <li class="{{ ($firstLevel === 'items_ecommerce') ? 'nav-active' : '' }}">
                                        <a class="nav-link" href="{{route('tenant.items_ecommerce.index')}}">Productos visibles</a>
                                    </li>
                                @endif

                                <li class="{{ ($secondLevel === 'item-sets') ? 'nav-active' : '' }}">
                                    <a class="nav-link"
                                        href="{{route('tenant.ecommerce.item_sets.index')}}">Conjuntos y Packs</a>
                                </li>

                                @if(in_array('ecommerce_tags', $vc_module_levels))
                                    <li class="{{ ($firstLevel === 'tags') ? 'nav-active' : '' }}">
                                        <a class="nav-link" href="{{route('tenant.tags.index')}}">Etiquetas</a>
                                    </li>
                                @endif

                                @if(in_array('ecommerce_settings', $vc_module_levels))
                                <li class="nav-item-with-action {{ ($secondLevel === 'configuration')?'nav-active':'' }}">
                                    <a class="nav-link" href="{{route('tenant_ecommerce_configuration')}}">Configuración</a>
                                    <button
                                        type="button"
                                        class="{{ ($firstLevel === 'quotations') ? 'second-buton' : 'btn-primary' }} btn btn-xs nav-action m-0 py-0"
                                        title="Ver tienda"
                                        onclick="window.open( '{{ route("tenant.ecommerce.index") }} ')">Ver Tienda
                                    </button>
                                </li>
                                @endif
                            </ul>
                        </li>
                    @endif

                    {{-- Restaurante --}}
                    @if(in_array('restaurant_app', $vc_modules))
                        <li class=" nav-parent {{ ($firstLevel === 'restaurant') ? 'nav-active nav-expanded' : '' }}">
                            <a class="nav-link" href="#">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                    stroke-linejoin="round"
                                    class="icon icon-tabler icons-tabler-outline icon-tabler-tools-kitchen-2">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path
                                        d="M19 3v12h-5c-.023 -3.681 .184 -7.406 5 -12zm0 12v6h-1v-3m-10 -14v17m-3 -17v3a3 3 0 1 0 6 0v-3" />
                                </svg>
                                <span>Restaurante</span>
                            </a>
                            <ul class="nav nav-children">
                                {{-- <li
                                    class="nav-parent
                                                                                                                                                    {{ ($secondLevel != null && $secondLevel == 'cash' && $thridLevel == 'pos')?'nav-active nav-expanded':'' }}">
                                    <a class="nav-link" href="#">
                                        POS
                                    </a>
                                    <ul class="nav nav-children">
                                        <li
                                            class="{{ ($secondLevel != null && $secondLevel == 'cash' && $thridLevel == 'pos')?'nav-active':'' }}">
                                            <a class="nav-link" href="{{route('tenant.restaurant.cash.filter-pos')}}">
                                                Caja Chica
                                            </a>
                                        </li>
                                    </ul>
                                </li> --}}
                                {{-- <li
                                    class="nav-parent {{ ($secondLevel != null && $secondLevel == 'cash' && $thridLevel == '')?'nav-active nav-expanded':'' }}">
                                    <a class="nav-link" href="#">
                                        Mesas
                                    </a>
                                    <ul class="nav nav-children">
                                        <li
                                            class="{{ ($secondLevel != null && $secondLevel == 'cash' && $thridLevel == '')?'nav-active':'' }}">
                                            <a class="nav-link" href="{{route('tenant.restaurant.cash.index')}}">
                                                Caja Chica
                                            </a>
                                        </li>
                                    </ul>
                                </li> --}}

                                <li
                                    class="{{ ($secondLevel != null && $secondLevel == 'list' && $firstLevel === 'restaurant') ? 'nav-active' : '' }}">
                                    <a class="nav-link" href="{{ route('tenant.restaurant.list_items') }}">
                                        Productos
                                    </a>
                                </li>

                                <li
                                    class="{{ ($secondLevel != null && $secondLevel == 'supplies' && $firstLevel === 'restaurant') ? 'nav-active' : '' }}">
                                    <a class="nav-link" href="{{ route('tenant.restaurant.supplies.index') }}">
                                        Insumos
                                    </a>
                                </li>

                                <li class="{{ ($secondLevel != null && $secondLevel == 'modifier-groups' && $firstLevel === 'restaurant') ? 'nav-active' : '' }}">
                                    <a class="nav-link" href="{{ url('restaurant/modifier-groups') }}">
                                        Modificadores
                                    </a>
                                </li>

                                <li
                                    class="nav-parent
                                                                                                                                                    {{ ($secondLevel != null && $secondLevel == 'promotions') || ($secondLevel != null && $secondLevel == 'orders') ? 'nav-active nav-expanded' : '' }}">
                                    <a class="nav-link" href="#">
                                        Pedidos Delivery
                                    </a>
                                    <ul class="nav nav-children">
                                        <li class="">
                                            <a class="nav-link" href="{{ route('tenant.restaurant.menu') }}" target="blank">
                                                Ver pedidos en linea
                                            </a>
                                        </li>
                                        <li
                                            class="{{ ($secondLevel != null && $secondLevel == 'orders') ? 'nav-active' : '' }}">
                                            <a class="nav-link" href="{{route('tenant.restaurant.order.index')}}">
                                                Listado de pedidos
                                            </a>
                                        </li>
                                        <li
                                            class="{{ ($secondLevel != null && $secondLevel == 'promotions') ? 'nav-active' : '' }}">
                                            <a class="nav-link" href="{{route('tenant.restaurant.promotion.index')}}">
                                                Promociones(Banners)
                                            </a>
                                        </li>
                                    </ul>
                                </li>
                                <li
                                    class="nav-item-with-action {{ ($secondLevel != null && $secondLevel == 'configuration' && $firstLevel === 'restaurant') ? 'nav-active' : '' }}">
                                    <a class="nav-link" href="{{ route('tenant.restaurant.configuration') }}">
                                        Configuración
                                    </a>
                                    <button
                                        type="button"
                                        class="{{ ($firstLevel === 'quotations') ? 'second-buton' : 'btn-primary' }} btn btn-xs nav-action m-0 py-0"
                                        title="Mozo"
                                        onclick="openMozoApp()">Ver Mozo
                                    </button>
                                </li>
                                <li class="nav-mozo-access" data-nav="mozo-access">
                                    <a class="nav-link" href="#" onclick="openMozoAccessModal(); return false;">
                                        Acceso Mozo
                                    </a>
                                </li>
                            </ul>
                        </li>
                    @endif

                    {{-- DIGEMID --}}
                    @if(in_array('digemid', $vc_modules) && $configuration->isPharmacy())
                        <li class=" nav-parent {{ ($firstLevel === 'digemid') ? 'nav-active nav-expanded' : '' }}">
                            <a class="nav-link" href="#">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                    stroke-linejoin="round"
                                    class="icon icon-tabler icons-tabler-outline icon-tabler-medicine-syrup">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path
                                        d="M8 21h8a1 1 0 0 0 1 -1v-10a3 3 0 0 0 -3 -3h-4a3 3 0 0 0 -3 3v10a1 1 0 0 0 1 1z" />
                                    <path d="M10 14h4" />
                                    <path d="M12 12v4" />
                                    <path d="M10 7v-3a1 1 0 0 1 1 -1h2a1 1 0 0 1 1 1v3" />
                                </svg>
                                <span>Farmacia</span>
                            </a>
                            <ul class="nav nav-children">
                                @if(in_array('digemid', $vc_module_levels))
                                    {{-- <li
                                        class="{{ (($firstLevel === 'documentary-procedure') && ($secondLevel === 'offices')) ? 'nav-active' : '' }}">
                                        <a class="nav-link" href="{{ route('documentary.offices') }}">Oficinas</a>
                                    </li> --}}
                                    <li
                                        class="{{ (($firstLevel === 'digemid') && ($secondLevel === 'digemid')) ? 'nav-active' : '' }}">
                                        <a class="nav-link" href="{{ route('tenant.digemid.index') }}">Productos</a>
                                    </li>
                                @endif
                            </ul>
                        </li>
                    @endif

                    {{-- @if(in_array('cuenta', $vc_modules))
                    <li class=" nav-parent
                        {{ ($firstLevel === 'cuenta')?'nav-active nav-expanded':'' }}">
                        <a class="nav-link" href="#">
                            <i class="fas fa-dollar-sign" aria-hidden="true"></i>
                            <span>Mis Pagos</span>
                        </a>
                        <ul class="nav nav-children">
                            @if(in_array('account_users_settings', $vc_module_levels))
                            <li
                                class="{{ (($firstLevel === 'cuenta') && ($secondLevel === 'configuration')) ?'nav-active':'' }}">
                                <a class="nav-link" href="{{route('tenant.configuration.index')}}">Configuracion</a>
                            </li>
                            @endif
                            @if(in_array('account_users_list', $vc_module_levels))
                            <li
                                class="{{ (($firstLevel === 'cuenta') && ($secondLevel === 'payment_index')) ?'nav-active':'' }}">
                                <a class="nav-link" href="{{route('tenant.payment.index')}}">Lista de Pagos</a>
                            </li>
                            @endif
                        </ul>
                    </li>
                    @endif --}}
                    {{-- @if(in_array('hotels', $vc_modules) || in_array('documentary-procedure', $vc_modules))
                    <li class="nav-description">Módulos extras</li>
                    @endif --}}
                    @if(in_array('hotels', $vc_modules))
                        <li class=" nav-parent {{ ($firstLevel === 'hotels') ? 'nav-active nav-expanded' : '' }}">
                            <a class="nav-link" href="#">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                    stroke-linejoin="round"
                                    class="icon icon-tabler icons-tabler-outline icon-tabler-building-skyscraper">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M3 21l18 0" />
                                    <path d="M5 21v-14l8 -4v18" />
                                    <path d="M19 21v-10l-6 -4" />
                                    <path d="M9 9l0 .01" />
                                    <path d="M9 12l0 .01" />
                                    <path d="M9 15l0 .01" />
                                    <path d="M9 18l0 .01" />
                                </svg>
                                <span>Hoteles</span>
                            </a>
                            <ul class="nav nav-children">
                                @if(in_array('hotels_reception', $vc_module_levels))
                                    <li
                                        class="{{ (($firstLevel === 'hotels') && ($secondLevel === 'reception')) ? 'nav-active' : '' }}">
                                        <a class="nav-link" href="{{ url('hotels/reception') }}">Recepción</a>
                                    </li>
                                @endif
                                @if(in_array('hotels_rates', $vc_module_levels))
                                    <li
                                        class="{{ (($firstLevel === 'hotels') && ($secondLevel === 'rates')) ? 'nav-active' : '' }}">
                                        <a class="nav-link" href="{{ url('hotels/rates') }}">Tarifas</a>
                                    </li>
                                @endif
                                @if(in_array('hotels_floors', $vc_module_levels))
                                    <li
                                        class="{{ (($firstLevel === 'hotels') && ($secondLevel === 'floors')) ? 'nav-active' : '' }}">
                                        <a class="nav-link" href="{{ url('hotels/floors') }}">Ubicaciones</a>
                                    </li>
                                @endif
                                @if(in_array('hotels_cats', $vc_module_levels))
                                    <li
                                        class="{{ (($firstLevel === 'hotels') && ($secondLevel === 'categories')) ? 'nav-active' : '' }}">
                                        <a class="nav-link" href="{{ url('hotels/categories') }}">Categorías</a>
                                    </li>
                                @endif
                                @if(in_array('hotels_rooms', $vc_module_levels))
                                    <li
                                        class="{{ (($firstLevel === 'hotels') && ($secondLevel === 'rooms')) ? 'nav-active' : '' }}">
                                        <a class="nav-link" href="{{ url('hotels/rooms') }}">Habitaciones</a>
                                    </li>
                                @endif
                            </ul>
                        </li>
                    @endif
                    {{-- Suscription --}}
                    @if(in_array('suscription_app', $vc_modules))
                        <li class=" nav-parent {{ ($firstLevel === 'full_suscription') ? 'nav-active nav-expanded' : '' }}">
                            <a class="nav-link" href="#">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                    stroke-linejoin="round"
                                    class="icon icon-tabler icons-tabler-outline icon-tabler-calendar-month">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path
                                        d="M4 7a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-12z" />
                                    <path d="M16 3v4" />
                                    <path d="M8 3v4" />
                                    <path d="M4 11h16" />
                                    <path d="M7 14h.013" />
                                    <path d="M10.01 14h.005" />
                                    <path d="M13.01 14h.005" />
                                    <path d="M16.015 14h.005" />
                                    <path d="M13.015 17h.005" />
                                    <path d="M7.01 17h.005" />
                                    <path d="M10.01 17h.005" />
                                </svg>
                                <span>
                                    Suscripción
                                </span>
                            </a>
                            <ul class="nav nav-children">
                                <li
                                    class="{{ (($firstLevel === 'full_suscription') && ($secondLevel === 'payments')) ? 'nav-active' : '' }}">
                                    <a class="nav-link" href="{{ route('tenant.fullsuscription.payments.index') }}">
                                        Suscripciones
                                    </a>
                                </li>
                                <li
                                    class="{{ (($firstLevel === 'full_suscription') && ($secondLevel === 'payment_receipt')) ? 'nav-active' : '' }}">
                                    <a class="nav-link" href="{{ route('tenant.fullsuscription.payment_receipt.index') }}">
                                        Recibos de pago
                                    </a>
                                </li>
                                <li
                                    class="{{ (($firstLevel === 'full_suscription') && ($secondLevel === 'plans')) ? 'nav-active' : '' }}">
                                    <a class="nav-link" href="{{ route('tenant.fullsuscription.plans.index') }}">
                                        Planes
                                    </a>
                                </li>
                                @if(in_array('suscription_app_pending_payments', $vc_module_levels))
                                <li
                                    class="{{ (($firstLevel === 'full-suscription') && ($secondLevel === 'pending-payments')) ? 'nav-active' : '' }}">
                                    <a class="nav-link" href="{{ route('tenant.full_suscription.pending-payments.index') }}">
                                        Pagos pendientes
                                    </a>
                                </li>
                                @endif

                                @if(in_array('suscription_app_payment_reminders', $vc_module_levels))
                                <li
                                    class="{{ (($firstLevel === 'full-suscription') && ($secondLevel === 'payment-reminders')) ? 'nav-active' : '' }}">
                                    <a class="nav-link" href="{{ route('tenant.full_suscription.payment-reminders.index') }}">
                                        Recordatorios de pago
                                    </a>
                                </li>
                                @endif
                            </ul>
                        </li>
                    @endif
                    {{-- Suscription Escolar--}}
                    @if(in_array('full_suscription_app', $vc_modules))
                        <li class=" nav-parent {{ ($firstLevel === 'suscription') ? 'nav-active nav-expanded' : '' }}">
                            <a class="nav-link" href="#">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                    stroke-linejoin="round"
                                    class="icon icon-tabler icons-tabler-outline icon-tabler-calendar-user">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M12 21h-6a2 2 0 0 1 -2 -2v-12a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v4.5" />
                                    <path d="M16 3v4" />
                                    <path d="M8 3v4" />
                                    <path d="M4 11h16" />
                                    <path d="M19 17m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                    <path d="M22 22a2 2 0 0 0 -2 -2h-2a2 2 0 0 0 -2 2" />
                                </svg>
                                <span>Suscripción Escolar <sup
                                        style="background: #ffc300;padding: 0px 3px;border-radius: 4px;">Beta</sup></span>
                            </a>
                            <ul class="nav nav-children">
                                {{-- @if(in_array('suscription_app_client', $vc_module_levels))--}}
                                <li
                                    class="nav-parent {{ (($firstLevel === 'suscription') && ($secondLevel === 'client')) ? ' nav-active nav-expanded ' : '' }}
                                                                                                                                                        ">

                                    <a class="nav-link" href="#">
                                        Clientes
                                    </a>
                                    <ul class="nav nav-children">
                                        <li
                                            class="{{ (($firstLevel === 'suscription') && ($secondLevel === 'client') && ($thridLevel !== 'childrens')) ? 'nav-active' : '' }}">
                                            <a class="nav-link" href="{{ route('tenant.suscription.client.index') }}">
                                                Padres
                                            </a>
                                        </li>
                                        <li
                                            class="{{ (($firstLevel === 'suscription') && ($secondLevel === 'client') && ($thridLevel === 'childrens')) ? 'nav-active' : '' }}">
                                            <a class="nav-link"
                                                href="{{ route('tenant.suscription.client_children.index') }}">
                                                Hijos
                                            </a>
                                        </li>
                                    </ul>
                                </li>
                                {{-- @endif--}}
                                {{--
                                @todo suscription_app_service borrar de modulo de permisos admin y cliente

                                @if(in_array('suscription_app_service', $vc_module_levels))
                                <li
                                    class="{{ (($firstLevel === 'suscription') && ($secondLevel === 'service')) ? 'nav-active' : '' }}">
                                    <a class="nav-link" href="{{ route('tenant.suscription.service.index') }}">
                                        Servicio
                                    </a>
                                </li>
                                @endif
                                --}}
                                {{-- @if(in_array('suscription_app_plans', $vc_module_levels))--}}
                                <li
                                    class="{{ (($firstLevel === 'suscription') && ($secondLevel === 'plans')) ? 'nav-active' : '' }}">
                                    <a class="nav-link" href="{{ route('tenant.suscription.plans.index') }}">
                                        Planes
                                    </a>
                                </li>
                                {{-- @endif--}}

                                {{-- @if(in_array('suscription_app_payments', $vc_module_levels))--}}
                                <li
                                    class="{{ (($firstLevel === 'suscription') && ($secondLevel === 'payments')) ? 'nav-active' : '' }}">
                                    <a class="nav-link" href="{{ route('tenant.suscription.payments.index') }}">
                                        Matrículas
                                    </a>
                                </li>
                                {{-- @endif--}}
                                {{-- @if(in_array('suscription_app_payments', $vc_module_levels))--}}
                                <li
                                    class="{{ (($firstLevel === 'suscription') && ($secondLevel === 'payment_receipt')) ? 'nav-active' : '' }}">
                                    <a class="nav-link" href="{{ route('tenant.suscription.payment_receipt.index') }}">
                                        Recibos de pago
                                    </a>
                                </li>
                                {{-- @endif--}}

                                <li
                                    class="{{ (($firstLevel === 'suscription') && ($secondLevel === 'grade_section')) ? 'nav-active' : '' }}">
                                    <a class="nav-link" href="{{ route('tenant.suscription.grade_section.index') }}">
                                        Grados y Secciones
                                    </a>
                                </li>

                            </ul>
                        </li>
                    @endif

                    @if(in_array('documentary-procedure', $vc_modules))
                        <li
                            class=" nav-parent {{ ($firstLevel === 'documentary-procedure') ? 'nav-active nav-expanded' : '' }}">
                            <a class="nav-link" href="#">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-folder-open"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 19l2.757 -7.351a1 1 0 0 1 .936 -.649h12.307a1 1 0 0 1 .986 1.164l-.996 5.211a2 2 0 0 1 -1.964 1.625h-14.026a2 2 0 0 1 -2 -2v-11a2 2 0 0 1 2 -2h4l3 3h7a2 2 0 0 1 2 2v2" /></svg>
                                <span>Trámite documentario</span>
                            </a>
                            <ul class="nav nav-children">
                                @if(in_array('documentary_offices', $vc_module_levels))
                                    <li
                                        class="{{ (($firstLevel === 'documentary-procedure') && ($secondLevel === 'offices')) ? 'nav-active' : '' }}">
                                        <a class="nav-link" href="{{ route('documentary.offices') }}">Listado de Etapas</a>
                                    </li>
                                    <li
                                        class="{{ (($firstLevel === 'documentary-procedure') && ($secondLevel === 'status')) ? 'nav-active' : '' }}">
                                        <a class="nav-link" href="{{ route('documentary.status') }}">Listado de Estados</a>
                                    </li>
                                @endif
                                @if(in_array('documentary_process', $vc_module_levels))


                                    <li
                                        class="{{ (($firstLevel === 'documentary-procedure') && ($secondLevel === 'requirements')) ? 'nav-active' : '' }}">
                                        <a class="nav-link" href="{{ route('documentary.requirements') }}">Listado de
                                            requisitos</a>
                                    </li>

                                    <li
                                        class="{{ (($firstLevel === 'documentary-procedure') && ($secondLevel === 'processes')) ? 'nav-active' : '' }}">
                                        <a class="nav-link" href="{{ route('documentary.processes') }}">Tipos de Trámites</a>
                                    </li>
                                @endif
                                {{--
                                @if(in_array('documentary_documents', $vc_module_levels))
                                <li
                                    class="{{ (($firstLevel === 'documentary-procedure') && ($secondLevel === 'documents')) ? 'nav-active' : '' }}">
                                    <a class="nav-link" href="{{ route('documentary.documents') }}">Tipos de Documento</a>
                                </li>
                                @endif
                                @if(in_array('documentary_actions', $vc_module_levels))
                                <li
                                    class="{{ (($firstLevel === 'documentary-procedure') && ($secondLevel === 'actions')) ? 'nav-active' : '' }}">
                                    <a class="nav-link" href="{{ route('documentary.actions') }}">Acciones</a>
                                </li>
                                @endif
                                --}}
                                @if(in_array('documentary_files', $vc_module_levels))
                                    {{--
                                    <li
                                        class="{{ (($firstLevel === 'documentary-procedure') && ($secondLevel === 'files')) ? 'nav-active' : '' }}">
                                        <a class="nav-link" href="{{ route('documentary.files') }}">Listado de Trámites</a>
                                    </li>
                                    --}}
                                    <li
                                        class="{{ (($firstLevel === 'documentary-procedure') && (($secondLevel === 'files_simplify') || ($secondLevel === 'files'))) ? 'nav-active' : '' }}">
                                        <a class="nav-link" href="{{ route('documentary.files_simplify') }}">Listado de
                                            Trámites</a>
                                    </li>
                                    <li
                                        class="{{ (($firstLevel === 'documentary-procedure') && (($secondLevel === 'stadistic'))) ? 'nav-active' : '' }}">
                                        <a class="nav-link" href="{{ route('documentary.stadistic') }}">Estadisticas de
                                            Trámites</a>
                                    </li>
                                @endif
                            </ul>
                        </li>
                    @endif

                    @if(in_array('claims_book', $vc_modules))
                        <li
                            class="{{  ($firstLevel === 'claims' ) ? 'nav-active' : ''}}">
                            <a class="nav-link dashboard-link" href="{{ url('claims') }}">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-book-2"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M19 4v16h-12a2 2 0 0 1 -2 -2v-12a2 2 0 0 1 2 -2h12" /><path d="M19 16h-12a2 2 0 0 0 -2 2" /><path d="M9 8h6" /></svg>
                                <span>Libro de Reclamaciones</span>
                            </a>
                        </li>
                    @endif

                    {{-- Produccion
                    @if(in_array('production_app', $vc_modules))

                                        <li class=" nav-parent {{ (
                            ($firstLevel === 'production') ||
                            ($firstLevel === 'machine-production') ||
                            ($firstLevel === 'packaging') ||
                            ($firstLevel === 'machine-type-production') ||
                            ($firstLevel === 'workers') ||
                            ($firstLevel === 'mill-production')
                        ) ? 'nav-active nav-expanded' : '' }}">
                                            <a class="nav-link" href="#">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                                    fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    class="icon icon-tabler icons-tabler-outline icon-tabler-building-factory-2">
                                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                    <path d="M3 21h18" />
                                                    <path d="M5 21v-12l5 4v-4l5 4h4" />
                                                    <path
                                                        d="M19 21v-8l-1.436 -9.574a.5 .5 0 0 0 -.495 -.426h-1.145a.5 .5 0 0 0 -.494 .418l-1.43 8.582" />
                                                    <path d="M9 17h1" />
                                                    <path d="M14 17h1" />
                                                </svg>
                                                <span>Producción</span>
                                            </a>
                                            <ul class="nav nav-children">
                                                <li class="{{ (($firstLevel === 'production')) ? 'nav-active' : '' }}">
                                                    <a class="nav-link" href="{{ route('tenant.production.index') }}">
                                                        Productos Fabricados
                                                    </a>
                                                </li>
                                                <li class="{{ (($firstLevel === 'mill-production')) ? 'nav-active' : '' }}">
                                                    <a class="nav-link" href="{{ route('tenant.mill_production.index') }}">
                                                        Ingreso de Insumos
                                                    </a>
                                                </li>

                                                <li class="{{ (($firstLevel === 'machine-type-production')) ? 'nav-active' : '' }}">
                                                    <a class="nav-link" href="{{ route('tenant.machine_type_production.index') }}">
                                                        Tipos de maquinaria
                                                    </a>
                                                </li>


                                                <li class="{{ (($firstLevel === 'machine-production')) ? 'nav-active' : '' }}">
                                                    <a class="nav-link" href="{{ route('tenant.machine_production.index') }}">
                                                        Maquinaria
                                                    </a>
                                                </li>
                                                <li class="{{ (($firstLevel === 'packaging')) ? 'nav-active' : '' }}">
                                                    <a class="nav-link" href="{{ route('tenant.packaging.index') }}">
                                                        Zona de embalaje
                                                    </a>
                                                </li>

                                                <li class="{{ (($firstLevel === 'workers')) ? 'nav-active' : '' }}">
                                                    <a class="nav-link" href="{{ route('tenant.workers.index') }}">
                                                        Empleados
                                                    </a>
                                                </li>
                                            </ul>
                                        </li>
                    @endif
                    --}}

                    <!-- @if(in_array('generate_link_app', $vc_modules))
                <li class="{{ ($firstLevel === 'payment-links')?'nav-active':'' }}">
                    <a class="nav-link"
                        href="{{ route('tenant.payment.generate.index') }}">
                        <svg xmlns="http://www.w3.org/2000/svg"
                            width="24"
                            height="24"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            class="feather feather-share-2">
                            <circle cx="18"
                                cy="5"
                                r="3"></circle>
                            <circle cx="6"
                                cy="12"
                                r="3"></circle>
                            <circle cx="18"
                                cy="19"
                                r="3"></circle>
                            <line x1="8.59"
                                y1="13.51"
                                x2="15.42"
                                y2="17.49"></line>
                            <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                        </svg>
                        <span>Generador de link de pago</span>
                    </a>
                </li>
                @endif -->
                </ul>
            </nav>
        </div>
        <script>
            // Maintain Scroll Position
            if (typeof localStorage !== 'undefined') {
                if (localStorage.getItem('sidebar-left-position') !== null) {
                    var initialPosition = localStorage.getItem('sidebar-left-position'),
                        sidebarLeft = document.querySelector('#sidebar-left .nano-content');
                    sidebarLeft.scrollTop = initialPosition;
                }
            }
        </script>
    </div>

    @if(auth()->check() || in_array('users_establishments', $vc_module_levels) || in_array('users', $vc_module_levels) || in_array('configuration', $vc_modules) || in_array('app_2_generator', $vc_modules) || in_array('apps', $vc_modules))
        <div class="more-config more-config-mobile">
            <div class="nano-content nano-content-config pt-0">
                <ul class="nav nav-main">
                    <li class="mb-0">
                        <a class="nav-link">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
                                class="feather feather-settings">
                                <circle cx="12" cy="12" r="3"></circle>
                                <path
                                    d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z">
                                </path>
                            </svg>
                            <span>Configuración y más</span>
                        </a>
                    </li>
                    <span class="w-100 text-center text-muted">{{ $vc_version ?? 'Pro 9' }}</span>
                </ul>
            </div>

            <ul class="nav list-config">

                <li>
                    <a id="sidebar-menu-config-trigger" class="nav-link" href="#">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icon-tabler-adjustments-horizontal">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M14 6l7 0" />
                            <path d="M3 6l2 0" />
                            <path d="M9 6l1 0" />
                            <path d="M5 3l0 6" />
                            <path d="M10 3l0 6" />
                            <path d="M3 12l6 0" />
                            <path d="M13 12l8 0" />
                            <path d="M9 9l0 6" />
                            <path d="M13 9l0 6" />
                            <path d="M14 18l7 0" />
                            <path d="M3 18l2 0" />
                            <path d="M9 18l1 0" />
                            <path d="M5 15l0 6" />
                            <path d="M10 15l0 6" />
                        </svg>
                        Configurar menú
                    </a>
                </li>

                @if(in_array('users', $vc_module_levels))
                    <li>
                        <a class="nav-link" href="{{route('tenant.users.index')}}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
                                class="icon icon-tabler icons-tabler-outline icon-tabler-users">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M9 7m-4 0a4 4 0 1 0 8 0a4 4 0 1 0 -8 0" />
                                <path d="M3 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" />
                                <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                                <path d="M21 21v-2a4 4 0 0 0 -3 -3.85" />
                            </svg>
                            Usuarios</a>
                    </li>
                @endif
                @if(in_array('users_establishments', $vc_module_levels))
                    <li>
                        <a class="nav-link" href="{{route('tenant.establishments.index')}}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
                                class="icon icon-tabler icons-tabler-outline icon-tabler-list-numbers">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M11 6h9" />
                                <path d="M11 12h9" />
                                <path d="M12 18h8" />
                                <path d="M4 16a2 2 0 1 1 4 0c0 .591 -.5 1 -1 1.5l-3 2.5h4" />
                                <path d="M6 10v-6l-2 2" />
                            </svg>
                            Sucursales & Series</a>
                    </li>
                @endif
                @if(in_array('app_2_generator', $vc_modules))
                    <li>
                        <a class="nav-link" href="{{ route('tenant.liveapp.configuration') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
                                class="icon icon-tabler icons-tabler-outline icon-tabler-device-mobile">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M6 5a2 2 0 0 1 2 -2h8a2 2 0 0 1 2 2v14a2 2 0 0 1 -2 2h-8a2 2 0 0 1 -2 -2v-14z" />
                                <path d="M11 4h2" />
                                <path d="M12 17v.01" />
                            </svg>
                            APP 4.1
                        </a>
                    </li>
                @endif
                @if(in_array('apps', $vc_modules))
                    <li>
                        <a class="nav-link" href="{{url('list-extras')}}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
                                class="icon icon-tabler icons-tabler-outline icon-tabler-packages">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M7 16.5l-5 -3l5 -3l5 3v5.5l-5 3z" />
                                <path d="M2 13.5v5.5l5 3" />
                                <path d="M7 16.545l5 -3.03" />
                                <path d="M17 16.5l-5 -3l5 -3l5 3v5.5l-5 3z" />
                                <path d="M12 19l5 3" />
                                <path d="M17 16.5l5 -3" />
                                <path d="M12 13.5v-5.5l-5 -3l5 -3l5 3v5.5" />
                                <path d="M7 5.03v5.455" />
                                <path d="M12 8l5 -3" />
                            </svg>
                            Apps
                        </a>
                    </li>
                @endif
                @if(in_array('configuration', $vc_modules))
                    <li
                        class="{{in_array($firstLevel, ['list-platforms', 'list-cards', 'list-currencies', 'list-bank-accounts', 'list-banks', 'list-attributes', 'list-detractions', 'list-units', 'list-payment-methods', 'list-incomes', 'list-payments', 'company_accounts', 'list-vouchers-type', 'companies', 'advanced', 'tasks', 'inventories', 'bussiness_turns', 'offline-configurations', 'series-configurations', 'configurations', 'login-page', 'list-settings']) ? 'nav-active' : ''}}">
                        <a class="nav-link" href="{{ url('list-settings') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
                                class="icon icon-tabler icons-tabler-outline icon-tabler-briefcase">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M3 7m0 2a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v9a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2z" />
                                <path d="M8 7v-2a2 2 0 0 1 2 -2h4a2 2 0 0 1 2 2v2" />
                                <path d="M12 12l0 .01" />
                                <path d="M3 13a20 20 0 0 0 18 0" />
                            </svg>
                            Configuraciones Globales</a>
                    </li>
                @endif

            </ul>
        </div>
    @endif
</aside>

<div id="sidebar-menu-config-modal" class="sidebar-menu-config-overlay" aria-hidden="true">
    <div class="sidebar-menu-config-dialog" role="dialog" aria-modal="true" aria-labelledby="sidebar-menu-config-title">
        <header class="sidebar-menu-config-header">
            <div>
                <h4 id="sidebar-menu-config-title" class="mb-1">Configurar menú</h4>
                <p class="mb-0">Organiza tus favoritos y elige qué accesos mantener a la vista.</p>
            </div>
            <button id="sidebar-menu-config-close" type="button" aria-label="Cerrar configuración del menú">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </header>
        <div class="sidebar-menu-config-toolbar">
            <label class="sidebar-menu-config-compact">
                <input id="sidebar-menu-config-filter-favorites" type="checkbox">
                <span class="sidebar-switch" aria-hidden="true"></span>
                <span>Mostrar únicamente los favoritos</span>
            </label>
            <div class="sidebar-menu-config-search">
                <i class="fas fa-search" aria-hidden="true"></i>
                <input id="sidebar-menu-config-search" type="search" autocomplete="off" placeholder="Buscar una opción del menú...">
            </div>
        </div>
        <div id="sidebar-menu-config-content" class="sidebar-menu-config-content">
            <section class="sidebar-menu-config-section">
                <div class="sidebar-menu-config-section-title">
                    <strong>Favoritos fijados</strong>
                    <small>Usa las flechas para cambiar la prioridad.</small>
                </div>
                <div id="sidebar-menu-config-pinned" class="sidebar-menu-config-list"></div>
            </section>
            <section class="sidebar-menu-config-section">
                <div class="sidebar-menu-config-section-title">
                    <strong>Opciones disponibles</strong>
                    <small>Solo aparecen los módulos permitidos para tu usuario.</small>
                </div>
                <div id="sidebar-menu-config-available" class="sidebar-menu-config-list sidebar-menu-config-available"></div>
            </section>
        </div>
        <footer class="sidebar-menu-config-footer">
            <small>Los cambios se guardan automáticamente.</small>
            <button id="sidebar-menu-config-done" type="button" class="btn btn-primary">Listo</button>
        </footer>
    </div>
</div>

<style>
    .sidebar-favorites {
        margin: 0 10px 8px;
        padding: 8px;
        border: 1px solid rgba(127, 127, 127, .2);
        border-radius: 10px;
        background: rgba(127, 127, 127, .06);
    }
    .sidebar-favorites-header,
    .sidebar-favorites-title,
    .sidebar-compact-toggle {
        display: flex;
        align-items: center;
    }
    .sidebar-favorites-header {
        justify-content: space-between;
        gap: 8px;
        margin-bottom: 5px;
    }
    .sidebar-favorites-title {
        gap: 6px;
        font-weight: 600;
        font-size: 12px;
    }
    .sidebar-search-trigger {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 24px;
        height: 24px;
        padding: 0;
        border: 0;
        border-radius: 6px;
        color: inherit;
        background: rgba(127, 127, 127, .12);
    }
    .sidebar-search-trigger:hover,
    .sidebar-search-trigger:focus {
        color: var(--primary-color, #635bff);
        background: rgba(99, 91, 255, .12);
    }
    .sidebar-compact-toggle {
        gap: 5px;
        margin: 0;
        font-size: 10px;
        line-height: 1.15;
        cursor: pointer;
        user-select: none;
    }
    .sidebar-compact-toggle input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }
    .sidebar-switch {
        position: relative;
        flex: 0 0 28px;
        width: 28px;
        height: 16px;
        border-radius: 10px;
        background: #adb5bd;
        transition: background-color .2s ease;
    }
    .sidebar-switch::after {
        content: '';
        position: absolute;
        top: 2px;
        left: 2px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #fff;
        box-shadow: 0 1px 3px rgba(0, 0, 0, .25);
        transition: transform .2s ease;
    }
    .sidebar-compact-toggle input:checked + .sidebar-switch {
        background: var(--primary-color, #635bff);
    }
    .sidebar-compact-toggle input:checked + .sidebar-switch::after {
        transform: translateX(12px);
    }
    .sidebar-pinned-items > li {
        cursor: grab;
        border-radius: 7px;
        transition: transform .18s ease, background-color .18s ease, opacity .18s ease;
    }
    .sidebar-pinned-items > li.is-dragging {
        opacity: .45;
    }
    .sidebar-pinned-items > li.drag-before {
        box-shadow: inset 0 2px 0 var(--primary-color, #635bff);
    }
    .sidebar-pinned-items > li.drag-after {
        box-shadow: inset 0 -2px 0 var(--primary-color, #635bff);
    }
    .sidebar-pinned-items .nav-children {
        display: block !important;
        max-height: 0;
        overflow: hidden;
        opacity: 0;
        transition: max-height .28s ease, opacity .2s ease;
    }
    .sidebar-pinned-items .nav-parent.nav-expanded > .nav-children {
        max-height: 900px;
        opacity: 1;
    }
    .sidebar-pinned-empty,
    .sidebar-preferences-status {
        display: block;
        padding: 6px 5px;
        color: #8a8f98;
        font-size: 10px;
        line-height: 1.3;
    }
    .sidebar-preferences-status:empty {
        display: none;
    }
    .sidebar-preferences-retry {
        display: none;
        width: 100%;
        margin-top: 4px;
        padding: 5px 8px;
        border: 1px solid #dc3545;
        border-radius: 6px;
        color: #dc3545;
        background: transparent;
        font-size: 10px;
        transition: color .16s ease, background-color .16s ease;
    }
    .sidebar-preferences-retry:hover,
    .sidebar-preferences-retry:focus {
        color: #fff;
        background: #dc3545;
    }
    .sidebar-preferences-retry.is-visible {
        display: block;
    }
    .sidebar-menu-config-overlay {
        position: fixed;
        z-index: 10000;
        inset: 0;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 18px;
        background: rgba(15, 23, 42, .52);
        backdrop-filter: blur(3px);
    }
    .sidebar-menu-config-overlay.is-open {
        display: flex;
    }
    .sidebar-menu-config-dialog {
        display: flex;
        flex-direction: column;
        width: min(920px, 100%);
        max-height: 88vh;
        overflow: hidden;
        border: 1px solid rgba(127, 127, 127, .2);
        border-radius: 14px;
        color: #263238;
        background: #fff;
        box-shadow: 0 28px 80px rgba(15, 23, 42, .3);
    }
    .sidebar-menu-config-header,
    .sidebar-menu-config-toolbar,
    .sidebar-menu-config-footer,
    .sidebar-menu-config-row,
    .sidebar-menu-config-row-actions,
    .sidebar-menu-config-compact,
    .sidebar-menu-config-search {
        display: flex;
        align-items: center;
    }
    .sidebar-menu-config-header {
        justify-content: space-between;
        gap: 16px;
        padding: 18px 20px;
        border-bottom: 1px solid #e8ebf1;
    }
    .sidebar-menu-config-header p,
    .sidebar-menu-config-section-title small,
    .sidebar-menu-config-footer small,
    .sidebar-menu-config-row-path {
        color: #7b8494;
    }
    .sidebar-menu-config-header > button {
        width: 32px;
        height: 32px;
        border: 0;
        border-radius: 8px;
        background: #f0f2f6;
    }
    .sidebar-menu-config-toolbar {
        justify-content: space-between;
        gap: 14px;
        padding: 12px 20px;
        border-bottom: 1px solid #eef0f4;
        background: #fafbfc;
    }
    .sidebar-menu-config-compact {
        gap: 8px;
        margin: 0;
        cursor: pointer;
    }
    .sidebar-menu-config-compact input {
        position: absolute;
        opacity: 0;
    }
    .sidebar-menu-config-compact input:checked + .sidebar-switch {
        background: var(--primary-color, #4267ef);
    }
    .sidebar-menu-config-compact input:checked + .sidebar-switch::after {
        transform: translateX(12px);
    }
    .sidebar-menu-config-search {
        gap: 8px;
        width: min(360px, 100%);
        padding: 0 11px;
        border: 1px solid #dce1ea;
        border-radius: 9px;
        background: #fff;
    }
    .sidebar-menu-config-search:focus-within {
        border-color: var(--primary-color, #4267ef);
        box-shadow: 0 0 0 3px rgba(66, 103, 239, .1);
    }
    .sidebar-menu-config-search input {
        flex: 1;
        min-width: 0;
        height: 38px;
        border: 0;
        outline: 0;
        background: transparent;
    }
    .sidebar-menu-config-content {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        gap: 16px;
        min-height: 0;
        padding: 16px 20px;
        overflow: hidden;
    }
    .sidebar-menu-config-content.is-showing-only-pinned {
        grid-template-columns: minmax(0, 1fr);
    }
    .sidebar-menu-config-content.is-showing-only-pinned .sidebar-menu-config-section:last-child {
        display: none;
    }
    .sidebar-menu-config-section {
        display: flex;
        flex-direction: column;
        min-height: 0;
        border: 1px solid #e5e9f0;
        border-radius: 10px;
        overflow: hidden;
    }
    .sidebar-menu-config-section-title {
        padding: 11px 13px;
        border-bottom: 1px solid #edf0f4;
        background: #f8f9fb;
    }
    .sidebar-menu-config-section-title strong,
    .sidebar-menu-config-section-title small,
    .sidebar-menu-config-row-name,
    .sidebar-menu-config-row-path {
        display: block;
    }
    .sidebar-menu-config-list {
        min-height: 180px;
        overflow-y: auto;
        padding: 7px;
    }
    .sidebar-menu-config-row {
        gap: 9px;
        min-height: 48px;
        padding: 7px 8px;
        border-radius: 8px;
    }
    .sidebar-menu-config-row:hover {
        background: rgba(66, 103, 239, .07);
    }
    .sidebar-menu-config-row-copy {
        flex: 1;
        min-width: 0;
    }
    .sidebar-menu-config-row-name,
    .sidebar-menu-config-row-path {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .sidebar-menu-config-row-name {
        font-weight: 600;
        font-size: 12px;
    }
    .sidebar-menu-config-row-path {
        margin-top: 2px;
        font-size: 10px;
    }
    .sidebar-menu-config-row-actions {
        gap: 4px;
    }
    .sidebar-menu-config-row-actions button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        padding: 0;
        border: 0;
        border-radius: 6px;
        color: #657184;
        background: #edf0f5;
    }
    .sidebar-menu-config-row-actions button:hover:not(:disabled) {
        color: #fff;
        background: var(--primary-color, #4267ef);
    }
    .sidebar-menu-config-row-actions button:disabled {
        opacity: .35;
    }
    .sidebar-menu-config-empty {
        padding: 28px 12px;
        color: #8992a1;
        text-align: center;
        font-size: 12px;
    }
    .sidebar-menu-config-footer {
        justify-content: space-between;
        gap: 12px;
        padding: 12px 20px;
        border-top: 1px solid #e8ebf1;
    }
    html.dark .sidebar-menu-config-dialog,
    html.sidebarMode-dark .sidebar-menu-config-dialog {
        color: #eef2f7;
        background: #202938;
    }
    @media (max-width: 767px) {
        .sidebar-menu-config-toolbar { align-items: stretch; flex-direction: column; }
        .sidebar-menu-config-search { width: 100%; }
        .sidebar-menu-config-content { grid-template-columns: 1fr; overflow-y: auto; }
        .sidebar-menu-config-list { max-height: 260px; }
    }
    .sidebar-search-overlay {
        position: fixed;
        z-index: 9999;
        inset: 0;
        display: none;
        align-items: flex-start;
        justify-content: center;
        padding: 9vh 16px 20px;
        background: rgba(15, 23, 42, .5);
        backdrop-filter: blur(3px);
    }
    .sidebar-search-overlay.is-open {
        display: flex;
    }
    .sidebar-search-dialog {
        width: min(680px, 100%);
        max-height: 78vh;
        overflow: hidden;
        border: 1px solid rgba(127, 127, 127, .22);
        border-radius: 14px;
        color: #263238;
        background: #fff;
        box-shadow: 0 24px 70px rgba(15, 23, 42, .28);
    }
    .sidebar-search-heading {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        padding: 16px 18px 10px;
    }
    .sidebar-search-heading strong,
    .sidebar-search-heading small {
        display: block;
    }
    .sidebar-search-heading small,
    .sidebar-search-summary,
    .sidebar-search-path {
        color: #7b8494;
    }
    .sidebar-search-heading button {
        width: 30px;
        height: 30px;
        border: 0;
        border-radius: 7px;
        background: #f1f3f7;
    }
    .sidebar-search-input-wrap {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0 18px;
        padding: 0 12px;
        border: 2px solid rgba(99, 91, 255, .25);
        border-radius: 10px;
        background: #fff;
    }
    .sidebar-search-input-wrap:focus-within {
        border-color: var(--primary-color, #635bff);
        box-shadow: 0 0 0 3px rgba(99, 91, 255, .1);
    }
    .sidebar-search-input-wrap input {
        flex: 1;
        min-width: 0;
        height: 46px;
        border: 0;
        outline: 0;
        color: inherit;
        background: transparent;
        font-size: 15px;
    }
    .sidebar-search-input-wrap kbd {
        padding: 2px 6px;
        border: 1px solid #d8dde7;
        border-radius: 5px;
        color: #7b8494;
        background: #f7f8fa;
        font-size: 10px;
    }
    .sidebar-search-summary {
        padding: 10px 19px 7px;
        font-size: 11px;
    }
    .sidebar-search-results {
        max-height: calc(78vh - 150px);
        overflow-y: auto;
        padding: 0 10px 12px;
    }
    .sidebar-search-result {
        display: flex;
        align-items: center;
        gap: 12px;
        width: 100%;
        padding: 10px;
        border: 0;
        border-radius: 9px;
        color: inherit;
        text-align: left;
        background: transparent;
    }
    .sidebar-search-result:hover,
    .sidebar-search-result.is-selected {
        color: inherit;
        background: rgba(99, 91, 255, .1);
    }
    .sidebar-search-result-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 34px;
        width: 34px;
        height: 34px;
        border-radius: 8px;
        color: var(--primary-color, #635bff);
        background: rgba(99, 91, 255, .1);
    }
    .sidebar-search-result-copy {
        min-width: 0;
    }
    .sidebar-search-result-name,
    .sidebar-search-path {
        display: block;
    }
    .sidebar-search-result-name {
        font-weight: 600;
    }
    .sidebar-search-path {
        margin-top: 2px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: 11px;
    }
    .sidebar-search-empty {
        padding: 32px 16px;
        color: #7b8494;
        text-align: center;
    }
    html.dark .sidebar-search-dialog,
    html.sidebarMode-dark .sidebar-search-dialog {
        color: #edf0f5;
        background: #1f2937;
    }
    html.dark .sidebar-search-input-wrap,
    html.sidebarMode-dark .sidebar-search-input-wrap {
        background: #111827;
    }
    #menu > .nav-main-mobile li {
        position: relative;
    }
    .menu-pin-action {
        position: absolute;
        z-index: 5;
        top: 7px;
        right: 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        box-sizing: border-box;
        width: 25px;
        height: 25px;
        padding: 0;
        border: 0;
        border-radius: 6px;
        color: inherit;
        background: rgba(127, 127, 127, .14);
        opacity: 0;
        transform: translateX(4px);
        pointer-events: none;
        transition: opacity .16s ease, transform .16s ease, background-color .16s ease;
    }
    .menu-pin-action > i,
    .menu-pin-action > .fas,
    .menu-pin-action > .fa {
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
        width: 14px !important;
        height: 14px !important;
        margin: 0 !important;
        padding: 0 !important;
        font-size: 12px;
        line-height: 14px !important;
        text-align: center;
        transform: none !important;
    }
    .menu-pin-action > i::before,
    .menu-pin-action > .fas::before,
    .menu-pin-action > .fa::before {
        display: block;
        width: 100%;
        margin: 0;
        line-height: 14px;
        text-align: center;
    }
    #menu > .nav-main-mobile li.nav-item-with-action > .menu-pin-action {
        right: 66px;
    }
    #menu > .nav-main-mobile .nav-children > li:not(.nav-item-with-action) > .menu-pin-action {
        right: 12px;
    }
    #menu > .nav-main-mobile li:hover > .menu-pin-action,
    #menu > .nav-main-mobile li:focus-within > .menu-pin-action,
    .menu-pin-action.is-pinned {
        opacity: 1;
        transform: translateX(0);
        pointer-events: auto;
    }
    #menu > .nav-main-mobile li:hover > .menu-pin-action,
    #menu > .nav-main-mobile li:focus-within > .menu-pin-action {
        pointer-events: auto;
    }
    .menu-pin-action.is-pinned {
        color: var(--primary-color, #635bff);
    }
    .sidebar-only-active #menu > .nav-main-mobile {
        display: none !important;
    }
    html.sidebar-left-collapsed .sidebar-favorites-header,
    html.sidebar-left-collapsed .sidebar-pinned-empty,
    html.sidebar-left-collapsed .sidebar-preferences-status {
        display: none;
    }
    html.sidebar-left-collapsed .sidebar-preferences-retry {
        display: none;
    }
    html.sidebar-left-collapsed .sidebar-favorites {
        margin: 5px;
        padding: 3px;
        border-color: transparent;
        background: transparent;
    }
    @media (max-width: 767px) {
        .menu-pin-action { opacity: .75; transform: none; pointer-events: auto; }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const sidebar = document.getElementById('sidebar-left');
        const menu = document.getElementById('menu');
        const originalList = menu ? menu.querySelector(':scope > .nav-main-mobile') : null;
        const pinnedList = document.getElementById('sidebar-pinned-items');
        const emptyState = document.getElementById('sidebar-pinned-empty');
        const compactToggle = document.getElementById('sidebar-show-only-active');
        const status = document.getElementById('sidebar-preferences-status');
        const retryButton = document.getElementById('sidebar-preferences-retry');
        const searchTrigger = document.getElementById('sidebar-search-trigger');
        const searchOverlay = document.getElementById('sidebar-advanced-search');
        const searchClose = document.getElementById('sidebar-search-close');
        const searchInput = document.getElementById('sidebar-search-input');
        const searchResults = document.getElementById('sidebar-search-results');
        const searchSummary = document.getElementById('sidebar-search-summary');
        const menuConfigTrigger = document.getElementById('sidebar-menu-config-trigger');
        const menuConfigModal = document.getElementById('sidebar-menu-config-modal');
        const menuConfigClose = document.getElementById('sidebar-menu-config-close');
        const menuConfigDone = document.getElementById('sidebar-menu-config-done');
        const menuConfigFilterFavorites = document.getElementById('sidebar-menu-config-filter-favorites');
        const menuConfigContent = document.getElementById('sidebar-menu-config-content');
        const menuConfigSearch = document.getElementById('sidebar-menu-config-search');
        const menuConfigPinned = document.getElementById('sidebar-menu-config-pinned');
        const menuConfigAvailable = document.getElementById('sidebar-menu-config-available');
        const endpoint = @json(url('api/user/menu-preferences'));
        const storageKey = @json('menu-preferences:' . request()->getHost() . ':' . auth()->id());
        const expansionStorageKey = @json('sidebar-menu-state:' . request()->getHost() . ':' . auth()->id());

        if (!sidebar || !originalList || !pinnedList || !compactToggle) return;

        let preferences = { pinned_items: [], menu_order: [], show_only_active_menu: false };
        let saveTimer = null;
        let draggedItem = null;
        let syncInProgress = false;
        let syncQueued = false;
        let searchIndex = [];
        let selectedSearchIndex = -1;
        let menuExpansionState = {};
        let menuConfigurationEntries = [];
        let menuConfigShowOnlyPinned = false;

        const synonymGroups = [
            ['sucursal', 'sede', 'establecimiento', 'local', 'tienda', 'agencia', 'punto de venta', 'almacen'],
            ['inventario', 'stock', 'existencia', 'existencias', 'kardex', 'almacen', 'bodega', 'deposito', 'disponibilidad', 'saldo', 'ubicacion', 'lote'],
            ['producto', 'articulo', 'item', 'mercaderia', 'mercancia', 'genero', 'bien', 'catalogo'],
            ['venta', 'facturacion', 'comercializacion', 'transaccion', 'operacion', 'transferencia', 'ingreso', 'pedido'],
            ['comprobante', 'documento', 'factura', 'boleta', 'ticket', 'recibo', 'cpe', 'nota de credito', 'nota de debito'],
            ['cliente', 'comprador', 'consumidor', 'adquirente', 'contacto'],
            ['compra', 'adquisicion', 'abastecimiento', 'aprovisionamiento', 'pedido de compra'],
            ['proveedor', 'suministrador', 'abastecedor', 'acreedor'],
            ['usuario', 'cuenta', 'operador', 'perfil', 'acceso', 'credencial'],
            ['vendedor', 'asesor', 'comercial', 'ejecutivo de ventas', 'representante'],
            ['pago', 'abono', 'paga', 'cobro', 'reembolso', 'reintegro', 'amortizacion', 'cancelacion'],
            ['caja', 'efectivo', 'dinero', 'tesoreria', 'arqueo', 'apertura', 'cierre', 'turno'],
            ['reporte', 'informe', 'estadistica', 'resumen', 'consulta', 'listado', 'analisis'],
            ['cotizacion', 'presupuesto', 'proforma', 'propuesta', 'oferta', 'estimacion'],
            ['guia', 'guia de remision', 'despacho', 'envio', 'traslado', 'transporte', 'transportista', 'remitente'],
            ['devolucion', 'retorno', 'reintegro', 'nota de credito', 'anulacion', 'reversion'],
            ['serie', 'numeracion', 'correlativo', 'secuencia', 'folio'],
            ['moneda', 'divisa', 'tipo de cambio', 'cambio', 'conversion'],
            ['banco', 'entidad financiera', 'cuenta bancaria', 'banca'],
            ['credito', 'financiamiento', 'cuota', 'pago diferido', 'dias de credito', 'plazo'],
            ['trabajador', 'empleado', 'personal', 'colaborador'],
            ['configuracion', 'ajuste', 'preferencia', 'parametro', 'opcion', 'personalizacion']
        ];

        function normalizedPreferences(value) {
            const source = value && typeof value === 'object' ? value : {};
            return {
                pinned_items: Array.isArray(source.pinned_items) ? source.pinned_items.filter(function (item) { return typeof item === 'string'; }) : [],
                menu_order: Array.isArray(source.menu_order) ? source.menu_order.filter(function (item) { return typeof item === 'string'; }) : [],
                show_only_active_menu: source.show_only_active_menu === true
            };
        }

        function readLocalBackup() {
            try {
                const backup = JSON.parse(localStorage.getItem(storageKey) || 'null');
                if (!backup || !backup.preferences) return null;
                return {
                    preferences: normalizedPreferences(backup.preferences),
                    pending: backup.pending === true
                };
            } catch (error) {
                console.error('Error al leer respaldo local de preferencias:', error);
                return null;
            }
        }

        function saveLocalBackup(pending) {
            try {
                localStorage.setItem(storageKey, JSON.stringify({
                    preferences: normalizedPreferences(preferences),
                    pending: pending === true,
                    updated_at: new Date().toISOString()
                }));
            } catch (error) {
                console.error('Error al guardar respaldo local de preferencias:', error);
            }
        }

        function setRetryVisible(visible) {
            retryButton.classList.toggle('is-visible', !!visible);
        }

        function readMenuExpansionState() {
            try {
                const stored = JSON.parse(localStorage.getItem(expansionStorageKey) || '{}');
                menuExpansionState = stored && typeof stored === 'object' && !Array.isArray(stored) ? stored : {};
            } catch (error) {
                menuExpansionState = {};
                console.error('Error al leer el estado de los menús laterales:', error);
            }
        }

        function saveMenuExpansionState() {
            try {
                localStorage.setItem(expansionStorageKey, JSON.stringify(menuExpansionState));
            } catch (error) {
                console.error('Error al guardar el estado de los menús laterales:', error);
            }
        }

        function setMenuExpanded(key, expanded, persist) {
            if (!key) return;
            menuExpansionState[key] = !!expanded;
            document.querySelectorAll('[data-menu-preference-key]').forEach(function (item) {
                if (item.dataset.menuPreferenceKey === key && item.classList.contains('nav-parent')) {
                    item.classList.toggle('nav-expanded', !!expanded);
                }
            });
            if (persist !== false) saveMenuExpansionState();
        }

        function restoreMenuExpansionState(root) {
            const scope = root || originalList;
            scope.querySelectorAll('li.nav-parent[data-menu-preference-key]').forEach(function (item) {
                const key = item.dataset.menuPreferenceKey;
                if (Object.prototype.hasOwnProperty.call(menuExpansionState, key)) {
                    item.classList.toggle('nav-expanded', menuExpansionState[key] === true);
                }
            });
        }

        function normalizeLabel(value) {
            return (value || '')
                .replace(/\s+/g, ' ')
                .trim()
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/[^a-z0-9]+/g, '-');
        }

        function directAnchor(item) {
            return Array.from(item.children).find(function (child) {
                return child.matches && child.matches('a.nav-link');
            });
        }

        function closestPinnedItem(target) {
            const item = target && target.closest ? target.closest('li.sidebar-pinned-item') : null;
            return item && item.parentElement === pinnedList ? item : null;
        }

        function itemKey(item) {
            const anchor = directAnchor(item);
            if (!anchor) return null;
            const href = anchor.getAttribute('href') || '';
            if (href && href !== '#') {
                try {
                    const parsed = new URL(href, window.location.origin);
                    return 'route:' + parsed.pathname.replace(/\/$/, '') + parsed.search;
                } catch (error) {
                    return 'route:' + href;
                }
            }

            const labels = [];
            let current = item;
            while (current && current !== originalList) {
                const currentAnchor = directAnchor(current);
                if (currentAnchor) labels.unshift(normalizeLabel(currentAnchor.textContent));
                current = current.parentElement ? current.parentElement.closest('li') : null;
            }
            return 'group:' + labels.filter(Boolean).join('/');
        }

        function availableItems() {
            const items = new Map();
            originalList.querySelectorAll('li').forEach(function (item) {
                const key = itemKey(item);
                if (key && !items.has(key)) {
                    item.dataset.menuPreferenceKey = key;
                    items.set(key, item);
                }
            });
            return items;
        }

        function orderedPinnedKeys() {
            const pinned = preferences.pinned_items || [];
            const order = (preferences.menu_order || []).filter(function (key) { return pinned.includes(key); });
            pinned.forEach(function (key) {
                if (!order.includes(key)) order.push(key);
            });
            return order;
        }

        function renderPinnedItems() {
            const items = availableItems();
            const validKeys = orderedPinnedKeys().filter(function (key) { return items.has(key); });
            preferences.pinned_items = validKeys.slice();
            preferences.menu_order = validKeys.slice();
            pinnedList.innerHTML = '';

            validKeys.forEach(function (key) {
                const clone = items.get(key).cloneNode(true);
                clone.querySelectorAll('.menu-pin-action').forEach(function (button) { button.remove(); });
                clone.dataset.menuPreferenceKey = key;
                clone.classList.add('sidebar-pinned-item');
                clone.setAttribute('draggable', 'true');
                pinnedList.appendChild(clone);
            });
            restoreMenuExpansionState(pinnedList);

            emptyState.style.display = validKeys.length ? 'none' : 'block';
            originalList.querySelectorAll('.menu-pin-action').forEach(function (button) {
                const active = preferences.pinned_items.includes(button.dataset.key);
                button.classList.toggle('is-pinned', active);
                button.title = active ? 'Quitar de favoritos' : 'Fijar en favoritos';
                button.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
        }

        function applyCompactMode() {
            compactToggle.checked = !!preferences.show_only_active_menu;
            sidebar.classList.toggle('sidebar-only-active', !!preferences.show_only_active_menu);
        }

        function showStatus(message, isError) {
            status.textContent = message;
            status.style.color = isError ? '#dc3545' : '';
            window.setTimeout(function () {
                if (status.textContent === message) status.textContent = '';
            }, 2500);
        }

        function persistPreferences(immediate) {
            saveLocalBackup(true);
            setRetryVisible(false);
            window.clearTimeout(saveTimer);
            saveTimer = window.setTimeout(function () {
                if (syncInProgress) {
                    syncQueued = true;
                    return;
                }

                syncInProgress = true;
                const snapshot = normalizedPreferences(preferences);
                const serializedSnapshot = JSON.stringify(snapshot);
                const csrf = document.querySelector('meta[name="csrf-token"]');
                fetch(endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf ? csrf.getAttribute('content') : '',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: serializedSnapshot
                }).then(function (response) {
                    return response.text().then(function (body) {
                        let payload = null;
                        try { payload = body ? JSON.parse(body) : null; } catch (parseError) { payload = body; }
                        if (!response.ok) {
                            const error = new Error('No se pudieron guardar las preferencias');
                            error.response = { status: response.status, statusText: response.statusText, data: payload };
                            throw error;
                        }
                        return payload || {};
                    });
                }).then(function (payload) {
                    // Una respuesta de una petición anterior nunca debe reemplazar
                    // cambios que el usuario haya realizado mientras se guardaba.
                    if (JSON.stringify(normalizedPreferences(preferences)) === serializedSnapshot) {
                        preferences = normalizedPreferences(payload.preferences || snapshot);
                        saveLocalBackup(false);
                        setRetryVisible(false);
                        showStatus('Preferencias guardadas');
                    } else {
                        syncQueued = true;
                    }
                }).catch(function (error) {
                    console.error('Error al guardar preferencias:', error.response || error);
                    saveLocalBackup(true);
                    setRetryVisible(true);
                    showStatus('No se pudieron guardar los cambios', true);
                }).finally(function () {
                    syncInProgress = false;
                    if (syncQueued) {
                        syncQueued = false;
                        persistPreferences(true);
                    }
                });
            }, immediate ? 0 : 180);
        }

        function normalizeSearchText(value) {
            return (value || '')
                .toString()
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/[^a-z0-9]+/g, ' ')
                .replace(/\s+/g, ' ')
                .trim();
        }

        function menuItemLabel(item) {
            const anchor = directAnchor(item);
            return anchor ? anchor.textContent.replace(/\s+/g, ' ').trim() : '';
        }

        function buildSearchIndex() {
            searchIndex = [];
            availableItems().forEach(function (item, key) {
                const anchor = directAnchor(item);
                if (!anchor) return;
                const href = anchor.getAttribute('href') || '';
                if (!href || href === '#') return;

                const hierarchy = [];
                let current = item;
                while (current && current !== originalList) {
                    const label = menuItemLabel(current);
                    if (label) hierarchy.unshift(label);
                    const parentList = current.parentElement;
                    current = parentList ? parentList.closest('li') : null;
                }

                const name = hierarchy[hierarchy.length - 1] || menuItemLabel(item);
                const path = hierarchy.join(' > ');
                const icon = anchor.querySelector('svg, i');
                searchIndex.push({
                    key: key,
                    name: name,
                    path: path,
                    normalizedName: normalizeSearchText(name),
                    normalizedPath: normalizeSearchText(path),
                    href: href,
                    icon: icon ? icon.cloneNode(true) : null
                });
            });
        }

        function expandedSearchTerms(query) {
            const terms = normalizeSearchText(query).split(' ').filter(Boolean);
            const expanded = new Set(terms);
            synonymGroups.forEach(function (group) {
                const normalizedGroup = group.map(normalizeSearchText);
                const matches = terms.some(function (term) {
                    return normalizedGroup.some(function (synonym) {
                        return synonym.includes(term) || term.includes(synonym);
                    });
                });
                if (matches) normalizedGroup.forEach(function (synonym) { expanded.add(synonym); });
            });
            return Array.from(expanded);
        }

        function textSimilarity(left, right) {
            if (!left || !right) return 0;
            if (left === right) return 1;
            if (left.length < 2 || right.length < 2) return 0;
            const pairs = new Map();
            for (let index = 0; index < left.length - 1; index++) {
                const pair = left.slice(index, index + 2);
                pairs.set(pair, (pairs.get(pair) || 0) + 1);
            }
            let intersection = 0;
            for (let index = 0; index < right.length - 1; index++) {
                const pair = right.slice(index, index + 2);
                const count = pairs.get(pair) || 0;
                if (count > 0) {
                    pairs.set(pair, count - 1);
                    intersection++;
                }
            }
            return (2 * intersection) / (left.length + right.length - 2);
        }

        function searchMenu(query) {
            const normalizedQuery = normalizeSearchText(query);
            if (!normalizedQuery) return [];
            const terms = expandedSearchTerms(normalizedQuery);

            return searchIndex.map(function (entry) {
                let score = 0;
                if (entry.normalizedName === normalizedQuery) score += 140;
                else if (entry.normalizedName.startsWith(normalizedQuery)) score += 110;
                else if (entry.normalizedName.includes(normalizedQuery)) score += 90;
                if (entry.normalizedPath.includes(normalizedQuery)) score += 55;

                terms.forEach(function (term) {
                    if (entry.normalizedName.includes(term)) score += 35;
                    else if (entry.normalizedPath.includes(term)) score += 20;
                });

                const similarity = textSimilarity(normalizedQuery, entry.normalizedName);
                if (similarity >= .42) score += Math.round(similarity * 35);
                return { entry: entry, score: score };
            }).filter(function (result) {
                return result.score > 0;
            }).sort(function (left, right) {
                return right.score - left.score || left.entry.path.localeCompare(right.entry.path);
            }).slice(0, 15).map(function (result) {
                return result.entry;
            });
        }

        function selectSearchResult(index) {
            const results = Array.from(searchResults.querySelectorAll('.sidebar-search-result'));
            if (!results.length) {
                selectedSearchIndex = -1;
                return;
            }
            selectedSearchIndex = Math.max(0, Math.min(index, results.length - 1));
            results.forEach(function (result, resultIndex) {
                result.classList.toggle('is-selected', resultIndex === selectedSearchIndex);
                result.setAttribute('aria-selected', resultIndex === selectedSearchIndex ? 'true' : 'false');
            });
            results[selectedSearchIndex].scrollIntoView({ block: 'nearest' });
        }

        function navigateToSearchResult(entry) {
            closeAdvancedSearch();
            window.location.href = entry.href;
        }

        function renderSearchResults(query) {
            const matches = searchMenu(query);
            searchResults.innerHTML = '';
            selectedSearchIndex = -1;

            if (!normalizeSearchText(query)) {
                searchSummary.textContent = 'Escribe para buscar entre ' + searchIndex.length + ' opciones disponibles.';
                return;
            }
            searchSummary.textContent = matches.length
                ? matches.length + ' resultado(s) ordenados por relevancia.'
                : 'No encontramos opciones relacionadas con “' + query.trim() + '”.';

            if (!matches.length) {
                const empty = document.createElement('div');
                empty.className = 'sidebar-search-empty';
                empty.innerHTML = '<i class="fas fa-search" aria-hidden="true"></i><br>No hay coincidencias disponibles para tu usuario.';
                searchResults.appendChild(empty);
                return;
            }

            matches.forEach(function (entry, index) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'sidebar-search-result';
                button.setAttribute('role', 'option');
                button.setAttribute('aria-selected', 'false');

                const icon = document.createElement('span');
                icon.className = 'sidebar-search-result-icon';
                if (entry.icon) icon.appendChild(entry.icon.cloneNode(true));
                else icon.innerHTML = '<i class="fas fa-arrow-right" aria-hidden="true"></i>';

                const copy = document.createElement('span');
                copy.className = 'sidebar-search-result-copy';
                const name = document.createElement('span');
                name.className = 'sidebar-search-result-name';
                name.textContent = entry.name;
                const path = document.createElement('span');
                path.className = 'sidebar-search-path';
                path.textContent = entry.path;
                copy.appendChild(name);
                copy.appendChild(path);
                button.appendChild(icon);
                button.appendChild(copy);
                button.addEventListener('mouseenter', function () { selectSearchResult(index); });
                button.addEventListener('click', function () { navigateToSearchResult(entry); });
                searchResults.appendChild(button);
            });
            selectSearchResult(0);
        }

        function openAdvancedSearch() {
            buildSearchIndex();
            searchOverlay.classList.add('is-open');
            searchOverlay.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            searchInput.value = '';
            renderSearchResults('');
            window.setTimeout(function () { searchInput.focus(); }, 20);
        }

        function closeAdvancedSearch() {
            searchOverlay.classList.remove('is-open');
            searchOverlay.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            searchTrigger.focus();
        }

        function buildMenuConfigurationEntries() {
            menuConfigurationEntries = [];
            availableItems().forEach(function (item, key) {
                const hierarchy = [];
                let current = item;
                while (current && current !== originalList) {
                    const label = menuItemLabel(current);
                    if (label) hierarchy.unshift(label);
                    const parentList = current.parentElement;
                    current = parentList ? parentList.closest('li') : null;
                }
                if (!hierarchy.length) return;
                menuConfigurationEntries.push({
                    key: key,
                    name: hierarchy[hierarchy.length - 1],
                    path: hierarchy.join(' > '),
                    normalized: normalizeSearchText(hierarchy.join(' '))
                });
            });
            menuConfigurationEntries.sort(function (left, right) {
                return left.path.localeCompare(right.path);
            });
        }

        function updateMenuConfiguration() {
            renderPinnedItems();
            renderMenuConfiguration();
            saveLocalBackup(true);
            persistPreferences();
        }

        function moveConfiguredFavorite(index, direction) {
            const order = orderedPinnedKeys();
            const target = index + direction;
            if (target < 0 || target >= order.length) return;
            const moving = order[index];
            order[index] = order[target];
            order[target] = moving;
            preferences.menu_order = order.slice();
            preferences.pinned_items = order.slice();
            updateMenuConfiguration();
        }

        function toggleConfiguredFavorite(key) {
            const pinnedIndex = preferences.pinned_items.indexOf(key);
            if (pinnedIndex >= 0) {
                preferences.pinned_items.splice(pinnedIndex, 1);
                preferences.menu_order = preferences.menu_order.filter(function (value) { return value !== key; });
            } else {
                preferences.pinned_items.push(key);
                preferences.menu_order.push(key);
            }
            updateMenuConfiguration();
        }

        function createMenuConfigurationRow(entry, pinned, index, total) {
            const row = document.createElement('div');
            row.className = 'sidebar-menu-config-row';

            const copy = document.createElement('div');
            copy.className = 'sidebar-menu-config-row-copy';
            const name = document.createElement('span');
            name.className = 'sidebar-menu-config-row-name';
            name.textContent = entry.name;
            const path = document.createElement('span');
            path.className = 'sidebar-menu-config-row-path';
            path.textContent = entry.path;
            copy.appendChild(name);
            copy.appendChild(path);

            const actions = document.createElement('div');
            actions.className = 'sidebar-menu-config-row-actions';
            if (pinned) {
                const up = document.createElement('button');
                up.type = 'button';
                up.title = 'Subir prioridad';
                up.disabled = index === 0;
                up.innerHTML = '<i class="fas fa-arrow-up" aria-hidden="true"></i>';
                up.addEventListener('click', function () { moveConfiguredFavorite(index, -1); });
                const down = document.createElement('button');
                down.type = 'button';
                down.title = 'Bajar prioridad';
                down.disabled = index === total - 1;
                down.innerHTML = '<i class="fas fa-arrow-down" aria-hidden="true"></i>';
                down.addEventListener('click', function () { moveConfiguredFavorite(index, 1); });
                actions.appendChild(up);
                actions.appendChild(down);
            }

            const toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.title = pinned ? 'Quitar de favoritos' : 'Fijar en favoritos';
            toggle.innerHTML = pinned
                ? '<i class="fas fa-times" aria-hidden="true"></i>'
                : '<i class="fas fa-thumbtack" aria-hidden="true"></i>';
            toggle.addEventListener('click', function () { toggleConfiguredFavorite(entry.key); });
            actions.appendChild(toggle);
            row.appendChild(copy);
            row.appendChild(actions);
            return row;
        }

        function renderMenuConfiguration() {
            if (!menuConfigPinned || !menuConfigAvailable) return;
            const entries = new Map(menuConfigurationEntries.map(function (entry) { return [entry.key, entry]; }));
            const ordered = orderedPinnedKeys().filter(function (key) { return entries.has(key); });
            const query = normalizeSearchText(menuConfigSearch.value);
            menuConfigPinned.innerHTML = '';
            menuConfigAvailable.innerHTML = '';

            ordered.forEach(function (key, index) {
                menuConfigPinned.appendChild(createMenuConfigurationRow(entries.get(key), true, index, ordered.length));
            });
            if (!ordered.length) {
                menuConfigPinned.innerHTML = '<div class="sidebar-menu-config-empty">Todavía no fijaste ningún acceso.</div>';
            }

            const available = menuConfigurationEntries.filter(function (entry) {
                return !preferences.pinned_items.includes(entry.key) && (!query || entry.normalized.includes(query));
            });
            available.forEach(function (entry) {
                menuConfigAvailable.appendChild(createMenuConfigurationRow(entry, false, -1, available.length));
            });
            if (!available.length) {
                menuConfigAvailable.innerHTML = '<div class="sidebar-menu-config-empty">No hay opciones que coincidan con la búsqueda.</div>';
            }
            applyMenuConfigurationFilter();
        }

        function applyMenuConfigurationFilter() {
            menuConfigFilterFavorites.checked = menuConfigShowOnlyPinned;
            menuConfigContent.classList.toggle('is-showing-only-pinned', menuConfigShowOnlyPinned);
            menuConfigAvailable.closest('.sidebar-menu-config-section').setAttribute(
                'aria-hidden',
                menuConfigShowOnlyPinned ? 'true' : 'false'
            );
        }

        function openMenuConfiguration() {
            buildMenuConfigurationEntries();
            menuConfigSearch.value = '';
            menuConfigShowOnlyPinned = false;
            renderMenuConfiguration();
            menuConfigModal.classList.add('is-open');
            menuConfigModal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            window.setTimeout(function () { menuConfigSearch.focus(); }, 20);
        }

        function closeMenuConfiguration() {
            menuConfigModal.classList.remove('is-open');
            menuConfigModal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            menuConfigTrigger.focus();
        }

        function installPinButtons() {
            availableItems().forEach(function (item, key) {
                if (Array.from(item.children).some(function (child) { return child.classList && child.classList.contains('menu-pin-action'); })) return;
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'menu-pin-action';
                button.dataset.key = key;
                button.setAttribute('aria-label', 'Fijar opción en favoritos');
                button.innerHTML = '<i class="fas fa-thumbtack" aria-hidden="true"></i>';
                button.addEventListener('click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    const index = preferences.pinned_items.indexOf(key);
                    if (index >= 0) {
                        preferences.pinned_items.splice(index, 1);
                        preferences.menu_order = preferences.menu_order.filter(function (value) { return value !== key; });
                    } else {
                        preferences.pinned_items.push(key);
                        preferences.menu_order.push(key);
                    }
                    renderPinnedItems();
                    persistPreferences();
                });
                item.appendChild(button);
            });
        }

        pinnedList.addEventListener('click', function (event) {
            const anchor = event.target.closest('a.nav-link');
            const item = anchor ? anchor.parentElement : null;
            if (!item || !item.classList.contains('nav-parent') || anchor.getAttribute('href') !== '#') return;
            event.preventDefault();
            event.stopPropagation();
            const expanded = !item.classList.contains('nav-expanded');
            setMenuExpanded(item.dataset.menuPreferenceKey, expanded);
        });

        originalList.addEventListener('click', function (event) {
            const anchor = event.target.closest('a.nav-link');
            const item = anchor ? anchor.parentElement : null;
            if (!item || !item.classList.contains('nav-parent') || anchor.getAttribute('href') !== '#') return;

            // El plugin histórico del sidebar alterna la clase en su propio
            // listener. Esperamos al siguiente ciclo para persistir el estado final.
            window.setTimeout(function () {
                setMenuExpanded(
                    item.dataset.menuPreferenceKey,
                    item.classList.contains('nav-expanded')
                );
            }, 0);
        });

        pinnedList.addEventListener('dragstart', function (event) {
            draggedItem = closestPinnedItem(event.target);
            if (!draggedItem) return;
            draggedItem.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', draggedItem.dataset.menuPreferenceKey || '');
        });
        pinnedList.addEventListener('dragover', function (event) {
            const target = closestPinnedItem(event.target);
            if (!draggedItem || !target || target === draggedItem) return;
            event.preventDefault();
            pinnedList.querySelectorAll('.drag-before, .drag-after').forEach(function (item) {
                item.classList.remove('drag-before', 'drag-after');
            });
            const after = event.clientY > target.getBoundingClientRect().top + target.offsetHeight / 2;
            target.classList.add(after ? 'drag-after' : 'drag-before');
        });
        pinnedList.addEventListener('drop', function (event) {
            const target = closestPinnedItem(event.target);
            if (!draggedItem || !target || target === draggedItem) return;
            event.preventDefault();
            const after = event.clientY > target.getBoundingClientRect().top + target.offsetHeight / 2;
            pinnedList.insertBefore(draggedItem, after ? target.nextSibling : target);
            preferences.menu_order = Array.from(pinnedList.children).map(function (item) {
                return item.dataset.menuPreferenceKey;
            });
            preferences.pinned_items = preferences.menu_order.slice();
            persistPreferences();
        });
        pinnedList.addEventListener('dragend', function () {
            pinnedList.querySelectorAll('.is-dragging, .drag-before, .drag-after').forEach(function (item) {
                item.classList.remove('is-dragging', 'drag-before', 'drag-after');
            });
            draggedItem = null;
        });

        compactToggle.addEventListener('change', function () {
            preferences.show_only_active_menu = compactToggle.checked;
            applyCompactMode();
            persistPreferences();
        });

        retryButton.addEventListener('click', function () {
            showStatus('Sincronizando...');
            persistPreferences(true);
        });

        searchTrigger.addEventListener('click', openAdvancedSearch);
        searchClose.addEventListener('click', closeAdvancedSearch);
        searchOverlay.addEventListener('mousedown', function (event) {
            if (event.target === searchOverlay) closeAdvancedSearch();
        });
        searchInput.addEventListener('input', function () {
            renderSearchResults(searchInput.value);
        });
        menuConfigTrigger.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            openMenuConfiguration();
        });
        menuConfigClose.addEventListener('click', closeMenuConfiguration);
        menuConfigDone.addEventListener('click', closeMenuConfiguration);
        menuConfigModal.addEventListener('mousedown', function (event) {
            if (event.target === menuConfigModal) closeMenuConfiguration();
        });
        menuConfigSearch.addEventListener('input', renderMenuConfiguration);
        menuConfigFilterFavorites.addEventListener('change', function () {
            menuConfigShowOnlyPinned = menuConfigFilterFavorites.checked;
            applyMenuConfigurationFilter();
        });
        document.addEventListener('keydown', function (event) {
            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                if (searchOverlay.classList.contains('is-open')) closeAdvancedSearch();
                else openAdvancedSearch();
                return;
            }
            if (menuConfigModal.classList.contains('is-open') && event.key === 'Escape') {
                event.preventDefault();
                closeMenuConfiguration();
                return;
            }
            if (!searchOverlay.classList.contains('is-open')) return;
            if (event.key === 'Escape') {
                event.preventDefault();
                closeAdvancedSearch();
            } else if (event.key === 'ArrowDown') {
                event.preventDefault();
                selectSearchResult(selectedSearchIndex + 1);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                selectSearchResult(selectedSearchIndex - 1);
            } else if (event.key === 'Enter' && selectedSearchIndex >= 0) {
                event.preventDefault();
                const selected = searchResults.querySelectorAll('.sidebar-search-result')[selectedSearchIndex];
                if (selected) selected.click();
            }
        });

        readMenuExpansionState();
        installPinButtons();
        restoreMenuExpansionState(originalList);
        buildSearchIndex();
        const localBackup = readLocalBackup();
        if (localBackup) {
            preferences = localBackup.preferences;
            renderPinnedItems();
            applyCompactMode();
            setRetryVisible(localBackup.pending);
        }
        fetch(endpoint, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (response) {
            if (!response.ok) throw new Error('No se pudieron cargar las preferencias');
            return response.json();
        }).then(function (payload) {
            // Si hay cambios locales pendientes, tienen prioridad hasta que el
            // usuario pueda sincronizarlos nuevamente.
            if (!localBackup || !localBackup.pending) {
                preferences = normalizedPreferences(payload || {});
                saveLocalBackup(false);
                setRetryVisible(false);
            }
            renderPinnedItems();
            applyCompactMode();
            if (localBackup && localBackup.pending) {
                showStatus('Sincronizando cambios pendientes...');
                persistPreferences(true);
            }
        }).catch(function (error) {
            console.error('Error al cargar preferencias:', error.response || error);
            renderPinnedItems();
            applyCompactMode();
            setRetryVisible(true);
            showStatus('No se pudieron cargar las preferencias', true);
        });
    });
</script>

<script>
    // Función para cambiar establecimiento desde el sidebar
    function changeSidebarEstablishment(establishmentId) {
        const payload = {
            establishment_id: establishmentId
        };

        const selector = document.getElementById('sidebar-establishment-selector');
        if (selector) {
            selector.disabled = true;
        }

        fetch('/establishments/change-user-establishment', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify(payload)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const mainWrapper = document.getElementById('main-wrapper');
                if (mainWrapper && mainWrapper.__vue__) {
                    const vueInstance = mainWrapper.__vue__;

                    if (vueInstance.$message) {
                        vueInstance.$message({
                            type: 'success',
                            message: data.message
                        });
                    }

                    if (vueInstance.$eventHub) {
                        vueInstance.$eventHub.$emit('establishmentChanged', establishmentId);
                    }
                }

                if (selector) {
                    selector.disabled = false;
                }
            }
        })
        .catch(error => {
            console.error('Error al cambiar establecimiento:', error);

            const mainWrapper = document.getElementById('main-wrapper');
            if (mainWrapper && mainWrapper.__vue__ && mainWrapper.__vue__.$message) {
                mainWrapper.__vue__.$message({
                    type: 'error',
                    message: 'Error al cambiar establecimiento'
                });
            }

            if (selector) {
                selector.disabled = false;
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        const sidebarToggle = document.querySelector('.sidebar-toggle');

        if (sidebarToggle) {
            function updateToggleTitle() {
                const isCollapsed = document.documentElement.classList.contains('sidebar-left-collapsed');
                sidebarToggle.setAttribute('title', isCollapsed ? 'Expandir menú lateral' : 'Colapsar menú lateral');
            }

            updateToggleTitle();

            sidebarToggle.addEventListener('click', function() {
                setTimeout(updateToggleTitle, 50);
            });

            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.attributeName === 'class') {
                        updateToggleTitle();
                    }
                });
            });

            observer.observe(document.documentElement, {
                attributes: true,
                attributeFilter: ['class']
            });
        }

        // Dropdown de establecimiento
        const establishmentIcon = document.getElementById('establishment-icon-trigger');
        const establishmentDropdown = document.getElementById('establishment-dropdown');

        if (establishmentIcon && establishmentDropdown) {

            establishmentIcon.addEventListener('click', function(e) {
                e.stopPropagation();
                establishmentDropdown.classList.toggle('show');
                establishmentIcon.classList.toggle('active');

            });


            document.addEventListener('click', function(e) {
                if (!establishmentIcon.contains(e.target) && !establishmentDropdown.contains(e.target)) {
                    establishmentDropdown.classList.remove('show');
                    establishmentIcon.classList.remove('active');
                }
            });


            establishmentDropdown.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }

        function reconcileHeaderSelectorsSection() {
            var li = document.getElementById('multi-user-content-li');
            if (!li) return;
            var divider = document.getElementById('multi-user-content-divider');

            var branchInSidebar = li.getAttribute('data-branch-in-sidebar') === '1';
            var branchSelector = document.getElementById('header-establishment-selector-container');

            var multiUserHasContent = !!document.querySelector('#header-multi-user-selector-container select[name="multi_user_id"]');

            var multiUserVisible = multiUserHasContent && !branchInSidebar;
            var branchVisible = !!branchSelector && !branchInSidebar;
            var sectionVisible = multiUserVisible || branchVisible;

            li.style.display = sectionVisible ? '' : 'none';
            if (divider) {
                divider.style.display = sectionVisible ? '' : 'none';
            }
        }

        document.addEventListener('tenant-multi-users-mounted', function() {
            reconcileHeaderSelectorsSection();
        });

        document.addEventListener('DOMContentLoaded', function() {
            reconcileHeaderSelectorsSection();
            setTimeout(reconcileHeaderSelectorsSection, 800);
            setTimeout(reconcileHeaderSelectorsSection, 2500);
        });

        // Listener para cambios de visibilidad del selector de establecimiento en sidebar
        window.addEventListener('branchSelectorVisibilityChanged', function(event) {
            const selectorContainer = document.getElementById('sidebar-establishment-selector-container');
            const sidebarSelectorsContainer = document.getElementById('sidebar-selectors-container');
            const multiuserSelector = document.getElementById('sidebar-multi-user-selector-container');
            const iconWrapper = document.getElementById('sidebar-establishment-icon-wrapper');
            const branchSelectorDropdown = document.getElementById('branch-selector-dropdown');
            const multiUserSelectorDropdown = document.getElementById('multi-user-selector-dropdown');
            const sidebar = document.getElementById('sidebar-left');

            let showInSidebarEvent;
            if (event.detail) {
                if (typeof event.detail.showInSidebar !== 'undefined') {
                    showInSidebarEvent = !!event.detail.showInSidebar;
                } else if (typeof event.detail.showInHeader !== 'undefined') {
                    showInSidebarEvent = !event.detail.showInHeader;
                }
            }

            if (typeof showInSidebarEvent !== 'undefined') {

                if (selectorContainer) {
                    selectorContainer.style.display = showInSidebarEvent ? 'block' : 'none';
                }
                if (sidebarSelectorsContainer) {
                    sidebarSelectorsContainer.style.display = showInSidebarEvent ? 'block' : 'none';
                }
                if (multiuserSelector) {
                    multiuserSelector.style.display = showInSidebarEvent ? 'block' : 'none';
                }
                if (iconWrapper) {
                    iconWrapper.style.display = showInSidebarEvent ? 'block' : 'none';
                }
                if (branchSelectorDropdown) {
                    branchSelectorDropdown.style.display = showInSidebarEvent ? 'block' : 'none';
                }
                if (multiUserSelectorDropdown) {
                    multiUserSelectorDropdown.style.display = showInSidebarEvent ? 'block' : 'none';
                }

                var headerBranchSelector = document.getElementById('header-establishment-selector-container');
                if (headerBranchSelector) {
                    headerBranchSelector.style.display = showInSidebarEvent ? 'none' : 'block';
                }

                var headerMultiUserSelector = document.getElementById('header-multi-user-selector-container');
                if (headerMultiUserSelector) {
                    headerMultiUserSelector.style.display = showInSidebarEvent ? 'none' : 'block';
                }

                var headerSectionLi = document.getElementById('multi-user-content-li');
                if (headerSectionLi) {
                    headerSectionLi.setAttribute('data-branch-in-sidebar', showInSidebarEvent ? '1' : '0');
                }
                reconcileHeaderSelectorsSection();

                var shouldShowBranch = !!selectorContainer && showInSidebarEvent;
                var shouldShowMulti = !!multiuserSelector && showInSidebarEvent;

                if (sidebar) {
                    sidebar.classList.remove('show-branch-selector', 'show-both-selectors', 'no-branch-selector');
                    if (shouldShowBranch && shouldShowMulti) {
                        sidebar.classList.add('show-both-selectors');
                    } else if (shouldShowBranch || shouldShowMulti) {
                        sidebar.classList.add('show-branch-selector');
                    } else {
                        sidebar.classList.add('no-branch-selector');
                    }
                }
            }
        });

        // Función global para actualizar la visibilidad del selector desde cualquier script sin recargar
        window.updateBranchSelectorVisibility = function(showInSidebar) {
            const ev = new CustomEvent('branchSelectorVisibilityChanged', { detail: { showInSidebar: !!showInSidebar } });
            window.dispatchEvent(ev);
        };

        document.addEventListener('DOMContentLoaded', function() {
            const wrappers = document.querySelectorAll('.sidebar-multi-user-selector-wrapper');
            wrappers.forEach(function(wrap) {
                const placeholder = wrap.querySelector('.sidebar-multi-user-placeholder');
                const tenantEl = wrap.querySelector('tenant-multi-users-change-client');
                if (!tenantEl || !placeholder) return;

                const removePlaceholder = function() {
                    placeholder.style.transition = 'opacity .25s ease';
                    placeholder.style.opacity = '0';
                    setTimeout(function() { if (placeholder.parentNode) placeholder.parentNode.removeChild(placeholder); }, 300);
                };

                const checkAndRemove = function() {
                    try {
                        if (tenantEl.offsetHeight > 0) { removePlaceholder(); return true; }
                        if (tenantEl.innerHTML && tenantEl.innerHTML.trim().length > 0) { removePlaceholder(); return true; }
                    } catch (e) { }
                    return false;
                };

                if (checkAndRemove()) return;

                const mo = new MutationObserver(function() {
                    if (checkAndRemove()) mo.disconnect();
                });

                mo.observe(tenantEl, { childList: true, subtree: true, attributes: true });

                setTimeout(function() { if (document.body.contains(placeholder)) removePlaceholder(); mo.disconnect(); }, 3000);
            });
        });

        document.addEventListener('tenant-multi-users-mounted', function(e) {
            try {
                const wrapper = e.target.closest && e.target.closest('.sidebar-multi-user-selector-wrapper');
                const placeholder = wrapper ? wrapper.querySelector('.sidebar-multi-user-placeholder') : null;
                if (placeholder) {
                    placeholder.style.transition = 'opacity .18s ease';
                    placeholder.style.opacity = '0';
                    setTimeout(function() { if (placeholder.parentNode) placeholder.parentNode.removeChild(placeholder); }, 220);
                }
            } catch (err) { }
        });

        (function() {
            var sidebar = document.getElementById('sidebar-left');
            if (!sidebar) return;
            var nano = sidebar.querySelector('.nano');
            if (!nano) return;

            nano.addEventListener('mouseenter', function() {
                nano.classList.add('hovered');
            });

            sidebar.addEventListener('mouseleave', function() {
                nano.classList.remove('hovered');
            });
        })();
    });
</script>

<style>
    html.no-overflowscrolling .nano {
        height: calc(100% - 62px);
    }
    @media only screen and (min-width: 767px) {
        html.no-overflowscrolling .sidebar-left.show-branch-selector .nano,
        html.no-overflowscrolling .sidebar-left:has(.nano.hovered).show-branch-selector .nano {
            height: calc(100% - 146px);
        }
        html.no-overflowscrolling .sidebar-left.show-both-selectors .nano,
        html.no-overflowscrolling .sidebar-left:has(.nano.hovered).show-both-selectors .nano {
            height: calc(100% - 200px);
        }
    }
    .more-config {
        position: relative;
        display: inline-block;
        overflow: visible;
        width: 100%;
    }

    .list-config {
        position: absolute;
        z-index: 1;
        display: none;
        background-color: #fff;
        min-width: 230px;
        border: 1px solid #e0e6f8;
        box-shadow: 0 0 16px 0px rgb(0 36 96 / 12%);
        bottom: 116px;
        left: 15px;
        border-radius: 8px;
        padding: 15px;
    }

    .more-config .nano-content:hover~.list-config,
    .list-config:hover {
        display: block;
    }

    ul.nav.list-config i {
        width: 18px;
        text-align: center;
    }

    ul.nav.list-config li:hover {
        background: #f3f4fb;
        border-radius: 5px;
    }

    .more-config ul.nav-main>li a:hover {
        padding-left: 10px !important;
    }

    .sidebar-red .more-config a,
    .sidebar-blue .more-config a,
    .sidebar-green .more-config a,
    .sidebar-dark .sidebar-left .more-config a {
        color: #60769a !important;
    }

    .sidebar-red .more-config a:hover,
    .sidebar-blue .more-config a:hover,
    .sidebar-green .more-config a:hover {
        color: #fff !important;
    }

    .nav-main .nav-children li.nav-item-with-action {
        position: relative;
    }

    .nav-main .nav-children li.nav-item-with-action > .nav-action {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        z-index: 2;
        padding: 2px 6px;
        line-height: 1.2;
    }
    .nav-item-with-action button{
        padding: 0 4px !important;
        font-size: 12px !important;
    }

    .contain-icon-establishment-wrapper {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .contain-icon-establishment {
        transition: all 0.3s ease;
    }

    .establishment-dropdown {
        position: absolute;
        left: 50px;
        top: 94px;
        transform: translateY(-50%);
        background-color: #fff;
        border: 1px solid #e0e6f8;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        min-width: 250px;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
        z-index: 9999;
    }

    .establishment-dropdown.show {
        opacity: 1;
        visibility: visible;
    }

    .establishment-dropdown-header {
        padding: 12px 15px;
        border-bottom: 1px solid #e0e6f8;
        font-weight: 600;
        font-size: 13px;
        color: #333;
        background-color: #f8f9fa;
        border-radius: 8px 8px 0 0;
    }

    .establishment-dropdown-content {
        padding: 10px 15px 15px 15px;
    }

    .tooltip-right {
        position: absolute;
        left: calc(100% + 0px);
        top: 50%;
        transform: translateY(-50%);
        background-color: #000;
        color: #fff;
        font-size: 12px;
        padding: 4px 7px;
        border-radius: 6px;
        white-space: nowrap;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.2s ease, visibility 0.2s ease;
        pointer-events: none;
        z-index: 10000;
    }

    .tooltip-right::after {
        content: '';
        position: absolute;
        top: 50%;
        left: -14px;
        transform: translateY(-50%);
        border-width: 8px;
        border-style: solid;
        border-color: transparent #000 transparent transparent;
    }

    .contain-icon-establishment-wrapper:hover .tooltip-right,
    .contain-icon-establishment-wrapper:focus-within .tooltip-right {
        opacity: 1;
        visibility: visible;
    }

    .contain-icon-establishment.active ~ .tooltip-right {
        opacity: 0;
        visibility: hidden;
    }

    .establishment-selector-container {
        min-height: 76px;
    }

    .sidebar-multi-user-selector-wrapper {
        min-height: 56px;
        display: block;
        position: relative;
    }

    .sidebar-multi-user-placeholder {
        position: absolute;
        left: 0;
        right: 0;
        top: 0;
        height: 58px;
        border-radius: 4px;
        background: linear-gradient(90deg, #eeeeee50 25%, #f5f5f598 50%, #eeeeee4f 75%);
        background-size: 200% 100%;
        animation: shimmer 1.2s linear infinite;
        z-index: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .sidebar-multi-user-placeholder svg{
        width: 12px;
        height: 12px;
        margin-right: 5px
    }
    tenant-multi-users-change-client.sidebar-multi-user-selector {
        display: block;
        position: relative;
        z-index: 1;
    }

    @keyframes shimmer {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }
</style>
