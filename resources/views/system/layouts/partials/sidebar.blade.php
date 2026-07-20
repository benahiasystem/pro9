@php
    $path = explode('/', request()->path());
    $path[1] = (array_key_exists(1, $path)> 0)?$path[1]:'';
    $path[2] = (array_key_exists(2, $path)> 0)?$path[2]:'';
    $path[0] = ($path[0] === '')?'documents':$path[0];
    $sysAdmin = auth()->guard('admin')->user();
@endphp
<aside id="sidebar-left" class="sidebar-left mt-0" style="z-index: 900">
        <div class="header-mobile d-flex flex-column d-md-none">
            <div class="logo-container m-2 d-flex align-items-center justify-content-between w-100">
            @php
                use App\Models\System\Configuration;
                $configuration = Configuration::first();
                $logo = $configuration->login->logo ?? null;
            @endphp
            @if ($logo)
                <a href="{{ route('system.dashboard') }}" class="logo pt-2 pt-md-0 position-relative">
                    <img class="uk-logo-inverse" width="100" height="auto" src="{{ $logo }}" alt="Logo" />
                </a>
            @elseif (file_exists(public_path('theme/logo.svg')))
                <a href="{{ route('system.dashboard') }}" class="logo pt-2 pt-md-0 position-relative">
                    <img class="uk-logo-inverse" width="100" height="auto" src="{{ asset('theme/logo.svg') }}" alt="Logo" />
                </a>
            @else
                <a href="{{ route('system.dashboard') }}" class="text-logo pt-md-0 position-relative">
                    PANEL RESELLER
                </a>
            @endif
            <div class="d-md-none toggle-sidebar-left" role="button" tabindex="0" aria-label="Alternar menú">
                <i class="fas fa-times icon-close-sidebar" aria-label="Cerrar menú"></i>
            </div>
        </div>

        <div class="d-flex alig-items-center justify-content-start user-mobile d-md-none">
            @php
                $userName = trim(\Auth::getUser()->name ?? '');
                $nameParts = preg_split('/\s+/', $userName, -1, PREG_SPLIT_NO_EMPTY);
                $initials = '';
                if (!empty($nameParts)) {
                    $initials = mb_strtoupper(mb_substr($nameParts[0], 0, 1));
                    if (count($nameParts) > 1) {
                        $initials .= mb_strtoupper(mb_substr(end($nameParts), 0, 1));
                    }
                }
                $initials = $initials !== '' ? $initials : '?';
            @endphp
            <div class="icon-user">
                <span>
                    {{ $initials }}
                </span>
            </div>
            <div class="d-flex flex-column align-items-start justify-content-center" data-lock-name="{{ \Auth::getUser()->email }}"
                data-lock-email="{{ \Auth::getUser()->email }}">
                <span class="name fw-semibold text-primary-new">{{ \Auth::getUser()->name }}</span>
                <span class="role">{{ \Auth::getUser()->email }}</span>
            </div>
        </div>
    </div>
    <div class="nano px-2">
        <div class="nano-content">
            <nav id="menu" class="nav-main pt-1" role="navigation">
                <ul class="nav nav-main">
                    <li class="{{ (in_array($path[0], ['clients', 'dashboard']))?'nav-active':'' }}">
                        @if($sysAdmin && ($sysAdmin->reseller_id === null || $sysAdmin->canAccessSystemModule('clients')))
                        <a class="nav-link" href="{{route('system.dashboard')}}">
                            <svg  xmlns="http://www.w3.org/2000/svg"  width="30"  height="30"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-layout-dashboard"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 4h4a1 1 0 0 1 1 1v6a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1v-6a1 1 0 0 1 1 -1" /><path d="M5 16h4a1 1 0 0 1 1 1v2a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1v-2a1 1 0 0 1 1 -1" /><path d="M15 12h4a1 1 0 0 1 1 1v6a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1v-6a1 1 0 0 1 1 -1" /><path d="M15 4h4a1 1 0 0 1 1 1v2a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1v-2a1 1 0 0 1 1 -1" /></svg>
                            <span>Dashboard</span>
                        </a>
                        @endif
                    </li>
                </ul>
            </nav>
            <nav id="menu" class="nav-main" role="navigation">
                <ul class="nav nav-main">
                    @if($sysAdmin && $sysAdmin->canAccessSystemModule('admin-reseller'))
                    <li class="{{ ($path[0] === 'admin-reseller')?'nav-active':'' }}">
                        <a class="nav-link" href="{{ route('system.admin_reseller.administrators.index') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-user-shield"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M6 21v-2a4 4 0 0 1 4 -4h2" /><path d="M22 16c0 4 -2.5 6 -3.5 6s-3.5 -2 -3.5 -6c1 0 2.5 -.5 3.5 -1.5c1 1 2.5 1.5 3.5 1.5z" /><path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" /></svg>
                            <span>Administradores</span>
                        </a>
                    </li>
                    @endif
                </ul>
            </nav>
            <nav id="menu" class="nav-main" role="navigation">
                <ul class="nav nav-main">
                    @if($sysAdmin && $sysAdmin->canAccessSystemModule('payment-orders'))
                    <li class="{{ ($path[0] === 'payment-orders')?'nav-active':'' }}">
                        <a class="nav-link" href="{{route('system.payments.index')}}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-credit-card"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 5m0 3a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v8a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3z" /><path d="M3 10l18 0" /><path d="M7 15l.01 0" /><path d="M11 15l2 0" /></svg>
                            <span>Pagos</span>
                        </a>
                    </li>
                    @endif
                </ul>
            </nav>
            @if(config('configuration.multi_user_enabled') && $sysAdmin && $sysAdmin->canAccessSystemModule('multi-users'))
                <nav id="menu" class="nav-main" role="navigation">
                    <ul class="nav nav-main">
                        <li class="{{ ($path[0] === 'multi-users')?'nav-active':'' }}">
                            <a class="nav-link" href="{{route('system.multi-users.index')}}">
                                <svg  xmlns="http://www.w3.org/2000/svg"  width="30"  height="30" viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-users-group"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10 13a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" /><path d="M8 21v-1a2 2 0 0 1 2 -2h4a2 2 0 0 1 2 2v1" /><path d="M15 5a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" /><path d="M17 10h2a2 2 0 0 1 2 2v1" /><path d="M5 5a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" /><path d="M3 13v-1a2 2 0 0 1 2 -2h2" /></svg>
                                <span>Multi Usuarios</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            @endif

            @if($sysAdmin && $sysAdmin->canAccessSystemModule('plans'))
            <nav id="menu" class="nav-main" role="navigation">
                <ul class="nav nav-main">
                    <li class="{{ ($path[0] === 'plans')?'nav-active':'' }}">
                        <a class="nav-link" href="{{route('system.plans.index')}}">
                            <svg  xmlns="http://www.w3.org/2000/svg"  width="30"  height="30" viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-shopping-cart"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M6 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M17 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M17 17h-11v-14h-2" /><path d="M6 5l14 1l-1 7h-13" /></svg>
                            <span>Planes</span>
                        </a>
                    </li>
                </ul>
            </nav>
            @endif

            @if($sysAdmin && $sysAdmin->canAccessSystemModule('massive-invoice'))
            <nav id="menu" class="nav-main" role="navigation">
                <ul class="nav nav-main">
                    <li class="{{ ($path[0] === 'massive-invoice')?'nav-active':'' }}">
                        <a class="nav-link" href="{{route('system.massive-invoice.index')}}">
                            <svg  xmlns="http://www.w3.org/2000/svg"  width="30"  height="30" viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-files"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M15 3v4a1 1 0 0 0 1 1h4" /><path d="M18 17h-7a2 2 0 0 1 -2 -2v-10a2 2 0 0 1 2 -2h4l5 5v7a2 2 0 0 1 -2 2z" /><path d="M16 17v2a2 2 0 0 1 -2 2h-7a2 2 0 0 1 -2 -2v-10a2 2 0 0 1 2 -2h2" /></svg>
                            <span>Facturación Masiva</span>
                        </a>
                    </li>
                </ul>
            </nav>
            {{-- 
            @endif

            @if($sysAdmin && $sysAdmin->canAccessSystemModule('accounting'))
            <nav id="menu" class="nav-main" role="navigation">
                <ul class="nav nav-main">
                    <li class="{{ ($path[0] === 'accounting')?'nav-active':'' }}">
                        <a class="nav-link" href="{{route('system.accounting.index')}}">
                            <svg  xmlns="http://www.w3.org/2000/svg"  width="30"  height="30" viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-calculator"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 3m0 2a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v14a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2z" /><path d="M8 7m0 1a1 1 0 0 1 1 -1h6a1 1 0 0 1 1 1v1a1 1 0 0 1 -1 1h-6a1 1 0 0 1 -1 -1z" /><path d="M8 14l0 .01" /><path d="M12 14l0 .01" /><path d="M16 14l0 .01" /><path d="M8 17l0 .01" /><path d="M12 17l0 .01" /><path d="M16 17l0 .01" /></svg>
                            <span>Contabilidad</span>
                        </a>
                    </li>
                </ul>
            </nav>
            --}}
            @endif
            
            @if($sysAdmin && $sysAdmin->canAccessSystemModule('auto-update'))
            <nav id="menu" class="nav-main" role="navigation">
                <ul class="nav nav-main">
                    <li class="{{ ($path[0] === 'auto-update')?'nav-active':'' }}">
                        <a class="nav-link" href="{{route('system.update')}}">
                            <svg  xmlns="http://www.w3.org/2000/svg"  width="30"  height="30"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-git-branch"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7 18m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M7 6m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M17 6m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M7 8l0 8" /><path d="M9 18h6a2 2 0 0 0 2 -2v-5" /><path d="M14 14l3 -3l3 3" /></svg>
                            <span>Actualización</span>
                        </a>
                    </li>
                </ul>
            </nav>
            @endif
            @if($sysAdmin && $sysAdmin->canAccessSystemModule('backup'))
            <nav id="menu" class="nav-main" role="navigation">
                <ul class="nav nav-main">
                    <li class="{{ ($path[0] === 'backup')?'nav-active':'' }}">
                        <a class="nav-link" href="{{route('system.backup')}}">
                            <svg  xmlns="http://www.w3.org/2000/svg"  width="30"  height="30"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-cloud-down"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 18.004h-5.343c-2.572 -.004 -4.657 -2.011 -4.657 -4.487c0 -2.475 2.085 -4.482 4.657 -4.482c.393 -1.762 1.794 -3.2 3.675 -3.773c1.88 -.572 3.956 -.193 5.444 1c1.488 1.19 2.162 3.007 1.77 4.769h.99c1.38 0 2.573 .813 3.13 1.99" /><path d="M19 16v6" /><path d="M22 19l-3 3l-3 -3" /></svg>
                            <span>Backup</span>
                        </a>
                    </li>
                </ul>
            </nav>
            @endif
            @if($sysAdmin && $sysAdmin->canAccessSystemModule('information'))
            <nav id="menu" class="nav-main" role="navigation">
                <ul class="nav nav-main">
                    <li class="{{ ($path[0] === 'information')?'nav-active':'' }}">
                        <a class="nav-link" href="{{route('system.information')}}">
                            <svg  xmlns="http://www.w3.org/2000/svg"  width="30"  height="30"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-info-hexagon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M19.875 6.27c.7 .398 1.13 1.143 1.125 1.948v7.284c0 .809 -.443 1.555 -1.158 1.948l-6.75 4.27a2.269 2.269 0 0 1 -2.184 0l-6.75 -4.27a2.225 2.225 0 0 1 -1.158 -1.948v-7.285c0 -.809 .443 -1.554 1.158 -1.947l6.75 -3.98a2.33 2.33 0 0 1 2.25 0l6.75 3.98h-.033z" /><path d="M12 9h.01" /><path d="M11 12h1v4h1" /></svg>
                            <span>Información</span>
                        </a>
                    </li>
                </ul>
            </nav>
            @endif
            @if($sysAdmin && $sysAdmin->canAccessSystemModule('logs'))
            <nav id="menu" class="nav-main" role="navigation">
                <ul class="nav nav-main">
                    <li class="">
                        <a class="nav-link" href="{{url('logs')}}" target="_BLANK">
                            <svg  xmlns="http://www.w3.org/2000/svg"  width="30"  height="30"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-bug"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 9v-1a3 3 0 0 1 6 0v1" /><path d="M8 9h8a6 6 0 0 1 1 3v3a5 5 0 0 1 -10 0v-3a6 6 0 0 1 1 -3" /><path d="M3 13l4 0" /><path d="M17 13l4 0" /><path d="M12 20l0 -6" /><path d="M4 19l3.35 -2" /><path d="M20 19l-3.35 -2" /><path d="M4 7l3.75 2.4" /><path d="M20 7l-3.75 2.4" /></svg>
                            <span>Logs</span>
                        </a>
                    </li>
                </ul>
            </nav>
            @endif

            @if($sysAdmin && $sysAdmin->canAccessSystemModule('reports'))
            <nav id="menu" class="nav-main pb-2" role="navigation">
                <ul class="nav nav-main">
                    <li class="{{ ($path[0] === 'reports')?'nav-active':'' }}">
                        <a class="nav-link" href="{{ route('system.list-reports') }}">
                            <svg  xmlns="http://www.w3.org/2000/svg"  width="30"  height="30"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-report-analytics"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 5h-2a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-12a2 2 0 0 0 -2 -2h-2" /><path d="M9 3m0 2a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v0a2 2 0 0 1 -2 2h-2a2 2 0 0 1 -2 -2z" /><path d="M9 17v-5" /><path d="M12 17v-1" /><path d="M15 17v-3" /></svg>
                            <span>Reportes</span>
                        </a>
                    </li>
                </ul>
            </nav>
            @endif
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

            // Add shadow to configuration nav on scroll
            document.addEventListener('DOMContentLoaded', function() {
                var nanoElement = document.querySelector('.sidebar-left .nano');
                var configNav = document.querySelector('.configuration-nav');
                var scrollTimeout;
                
                if (nanoElement && configNav) {
                    nanoElement.addEventListener('scroll', function() {
                        if (this.scrollTop > 10) {
                            configNav.classList.add('show-shadow');
                        } else {
                            configNav.classList.remove('show-shadow');
                        }
                        
                        clearTimeout(scrollTimeout);
                        
                        scrollTimeout = setTimeout(function() {
                            configNav.classList.remove('show-shadow');
                        }, 50);
                    });
                }
            });
        </script>

    </div>
    @if($sysAdmin && ($sysAdmin->reseller_id === null || $sysAdmin->canAccessSystemModule('configurations')))
    <nav id="menu" class="nav-main configuration-nav pt-0 px-2" role="navigation">
        <div class="more-config more-config-mobile">
            <div class="nano-content nano-content-config pt-0">
                <ul class="nav nav-main">
                    <li class="mb-0">
                        <a class="nav-link configuration-dropdown-trigger" href="#" aria-haspopup="true" aria-expanded="false">
                            <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-settings"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10.325 4.317c.426 -1.756 2.924 -1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543 -.94 3.31 .826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756 .426 1.756 2.924 0 3.35a1.724 1.724 0 0 0 -1.066 2.573c.94 1.543 -.826 3.31 -2.37 2.37a1.724 1.724 0 0 0 -2.572 1.065c-.426 1.756 -2.924 1.756 -3.35 0a1.724 1.724 0 0 0 -2.573 -1.066c-1.543 .94 -3.31 -.826 -2.37 -2.37a1.724 1.724 0 0 0 -1.065 -2.572c-1.756 -.426 -1.756 -2.924 0 -3.35a1.724 1.724 0 0 0 1.066 -2.573c-.94 -1.543 .826 -3.31 2.37 -2.37c1 .608 2.296 .07 2.572 -1.065z"/><path d="M9 12a3 3 0 1 0 6 0a3 3 0 0 0 -6 0"/></svg>
                            <span>Configuración</span>
                        </a>
                    </li>
                </ul>
            </div>

            <ul class="nav list-config">
                <li class="{{ ($path[0] === 'configurations' && $path[1] === 'mozo') ? 'nav-active' : '' }}">
                    <a class="nav-link text-nowrap" href="{{ route('system.mozo.index') }}">
                        <svg
                          xmlns="http://www.w3.org/2000/svg"
                          viewBox="0 0 690.77 908.59"
                          width="20" height="20"
                        >
                          <style>
                            .cls-1 { fill: var(--dark-color); }
                            .cls-2 { fill: var(--dark-color); }
                            .cls-3 { fill: var(--dark-color); }
                          </style>
                          <g id="Layer_2" data-name="Layer 2">
                            <g id="Layer_1-2" data-name="Layer 1">
                              <path
                                class="cls-1"
                                d="M342.17,445.17C306.48,434.8,277.36,417.8,258,396c-19.83-22.34-27.37-48.22-20.72-71.16s27.37-42.19,56.73-52.58c29.79-10.58,65.54-10.82,100.54-.65l-9.12,31.46c-28.43-8.24-57-8.23-80.42,0-19,6.74-32.54,18.27-36.19,30.84-3.46,11.88,1.54,26.53,13.72,40.23,15.06,16.93,39.51,30.95,68.89,39.47Z"
                              />
                              <path
                                class="cls-1"
                                d="M412.54,539.72a367.79,367.79,0,0,1-101.79-15.17c-56.47-16.41-106.22-44.26-140.09-78.43-36-36.3-50.27-75.87-40.4-111.52,1.65-9.18,12.77-54.76,73-75.5l10.67,31c-45.69,15.73-51.2,48.93-51.41,50.27l-.16,1.28L162,342.7c-7,24,4.38,52.48,31.93,80.3,30,30.27,74.74,55.15,126,70,100.74,29.25,198.68,9.83,213.83-42.39,7-24-4.37-52.49-31.93-80.3-30-30.28-74.73-55.15-126-70L385,268.84c56.47,16.4,106.13,44.25,140.09,78.42,36.3,36.64,50.57,76.6,40.14,112.49C550.44,510.64,489.93,539.72,412.54,539.72Z"
                              />
                              <path
                                class="cls-2"
                                d="M397,596.82c-155.83,0-291.56-83.41-363.62-207a307.52,307.52,0,0,0-3.91,49.34c0,172.6,139.83,312.42,312.42,312.42,145.42,0,267.74-99.42,302.56-234A422.56,422.56,0,0,1,397,596.82Z"
                              />
                              <path
                                class="cls-2"
                                d="M290,815.76a240,240,0,0,1-153.92-68.31l-38.73,96a241,241,0,0,1,259,65.16l12.57-101A240.47,240.47,0,0,1,290,815.76Z"
                              />
                              <path
                                class="cls-3"
                                d="M690.36,458.21c6.48-88.8-71.23-181.48-201.08-236.09L473.73,259C593.47,309.42,665.19,396.37,648.14,470.5,638.12,514,599,548.79,538,568.58c-64.57,20.93-144.76,22.17-225.77,3.54S159.54,517.3,110.67,470.25C67.46,428.7,47.2,383.68,53,342.49c.28-1,.54-1.86.84-2.88l-.32-.1c.32-1.86.62-3.72,1.05-5.58,10-43.46,49.11-78.29,110.13-98.07,64.58-20.93,144.77-22.19,225.78-3.55l9-39.1c-88-20.26-175.74-18.62-247.11,4.46-75,24.21-123.53,69.47-136.83,127.21-.67,3-1.23,6-1.73,8.94A347,347,0,0,0,0,430.58C0,621.33,155.19,776.51,345.94,776.51c171.32,0,316.84-125.4,342.17-294.84,1.14-7.73,2-15.61,2.66-23.43ZM546.61,661.43a305.73,305.73,0,0,1-200.67,75c-162.86,0-296.36-127.92-305.35-288.59A275.72,275.72,0,0,0,83,499.11c54.11,52,132.35,91.79,220.32,112a541.24,541.24,0,0,0,121,14.1c45.08,0,88-6.2,126.16-18.62a272.1,272.1,0,0,0,64.24-29.92A307.25,307.25,0,0,1,546.61,661.43Z"
                              />
                              <path
                                class="cls-3"
                                d="M424.51,389.13s-101.27-53.36-4.06-197.36c0,0,82.39-118.15-32.68-180.28A211.9,211.9,0,0,0,362.06,0s46.79,55-20.48,130.33C341.54,130.33,174.46,327.15,424.51,389.13Z"
                              />
                              <path
                                class="cls-3"
                                d="M535.44,432.49S458.73,392.05,532.38,283c0,0,71-101.84-44.25-145.39,0,0,35.37,41.67-15.57,98.68C472.56,236.46,346,385.41,535.44,432.49Z"
                              />
                            </g>
                          </g>
                        </svg>
                        Mozo
                    </a>
                </li>
                <li class="{{ ($path[0] === 'configurations' && $path[1] !== 'mozo') ? 'nav-active' : '' }}">
                    <a class="nav-link text-nowrap" href="{{ route('system.configuration.index') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-briefcase"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 7m0 2a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v9a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2z"/><path d="M8 7v-2a2 2 0 0 1 2 -2h4a2 2 0 0 1 2 2v2"/><path d="M12 12l0 .01"/><path d="M3 13a20 20 0 0 0 18 0"/></svg>
                        Configuraciones globales
                    </a>
                </li>
            </ul>
        </div>
        <ul class="nav nav-main d-md-none">
            <li class="{{ ($path[0] === 'users')?'nav-active':'' }}">
                <a class="nav-link" href="{{ route('system.users.create') }}">
                    <svg  xmlns="http://www.w3.org/2000/svg"  width="18"  height="18"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-user"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" /><path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" /></svg>
                    <span>Perfil</span>
                </a>
            </li>
        </ul>
        <ul class="nav nav-main d-md-none">
            <li class="{{ ($path[0] === 'themes')?'nav-active':'' }}">
                <a class="nav-link" onclick="toggleThemeSidebar()">
                    <svg  xmlns="http://www.w3.org/2000/svg"  width="18"  height="18"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-paint"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 3m0 2a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v2a2 2 0 0 1 -2 2h-10a2 2 0 0 1 -2 -2z" /><path d="M19 6h1a2 2 0 0 1 2 2a5 5 0 0 1 -5 5l-5 0v2" /><path d="M10 15m0 1a1 1 0 0 1 1 -1h2a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-2a1 1 0 0 1 -1 -1z" /></svg>
                    <span>Estilos y Temas</span>
                </a>
            </li>
        </ul>
        <ul class="nav nav-main d-md-none">
            <li class="{{ ($path[0] === 'logout')?'nav-active':'' }}">
                <a class="nav-link text-danger" href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                    <svg  xmlns="http://www.w3.org/2000/svg"  width="18"  height="18"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-logout"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14 8v-2a2 2 0 0 0 -2 -2h-7a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2 -2v-2" /><path d="M9 12h12l-3 -3" /><path d="M18 15l3 -3" /></svg>
                    <span>@lang('app.buttons.logout')</span>
                </a>
            </li>
        </ul>
    </nav>
    @endif

</aside>

<style>
    .configuration-nav .more-config {
        position: relative;
        display: inline-block;
        width: 100%;
        overflow: visible;
    }

    .configuration-nav .list-config {
        position: absolute;
        z-index: 1000;
        display: none;
        bottom: 100%;
        left: 7px;
        min-width: 230px;
        padding: 10px;
        background-color: #fff;
        border: 1px solid #e0e6f8;
        border-radius: 8px;
        box-shadow: 0 0 16px 0 rgb(0 36 96 / 12%);
    }

    .configuration-nav .more-config:hover > .list-config,
    .configuration-nav .more-config:focus-within > .list-config,
    .configuration-nav .more-config.is-open > .list-config {
        display: block;
    }

    .configuration-nav .list-config li:hover {
        background: #f3f4fb;
        border-radius: 5px;
    }

    .configuration-nav .list-config .nav-link {
        display: flex;
        align-items: center;
    }
</style>

<script>
    (function () {
        var sidebar = document.getElementById('sidebar-left');
        if (!sidebar) return;

        var html = document.documentElement;
        var OPEN_CLASS = 'sidebar-left-opened';
        var mql = window.matchMedia('(max-width: 767.98px)');
        var configurationMenu = sidebar.querySelector('.configuration-nav .more-config');
        var configurationTrigger = sidebar.querySelector('.configuration-dropdown-trigger');

        if (configurationMenu && configurationTrigger) {
            configurationTrigger.addEventListener('click', function (e) {
                e.preventDefault();
                var isOpen = configurationMenu.classList.toggle('is-open');
                configurationTrigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            });

            document.addEventListener('click', function (e) {
                if (!configurationMenu.contains(e.target)) {
                    configurationMenu.classList.remove('is-open');
                    configurationTrigger.setAttribute('aria-expanded', 'false');
                }
            });
        }

        // Agrega/quita la clase sidebar-mobile según el ancho de pantalla (< 768px).
        function toggleSidebarMobile(e) {
            sidebar.classList.toggle('sidebar-mobile', e.matches);
            if (!e.matches) {
                closeSidebar();
            }
        }

        function openSidebar() {
            html.classList.add(OPEN_CLASS);
        }

        function closeSidebar() {
            html.classList.remove(OPEN_CLASS);
        }

        function toggleSidebar(e) {
            if (e) e.preventDefault();
            html.classList.toggle(OPEN_CLASS);
        }

        // Botones de apertura/cierre (header y sidebar).
        var toggles = document.querySelectorAll('.toggle-sidebar-left');
        for (var i = 0; i < toggles.length; i++) {
            toggles[i].addEventListener('click', toggleSidebar);
            toggles[i].addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') {
                    toggleSidebar(e);
                }
            });
        }

        // Cierra al hacer clic fuera del sidebar (solo en móvil).
        document.addEventListener('click', function (e) {
            if (!mql.matches || !html.classList.contains(OPEN_CLASS)) return;
            if (sidebar.contains(e.target) || e.target.closest('.toggle-sidebar-left')) return;
            closeSidebar();
        });

        // Cierra al tocar cualquier opción del menú (solo en móvil).
        sidebar.addEventListener('click', function (e) {
            if (mql.matches && e.target.closest('.nav-link, a, li')) {
                closeSidebar();
            }
        });

        // Cierra con la tecla Escape.
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeSidebar();
        });

        toggleSidebarMobile(mql);

        if (mql.addEventListener) {
            mql.addEventListener('change', toggleSidebarMobile);
        } else if (mql.addListener) {
            mql.addListener(toggleSidebarMobile);
        }
    })();
</script>
