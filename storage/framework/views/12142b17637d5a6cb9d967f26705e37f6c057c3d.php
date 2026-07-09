<header class="header">
    <div class="logo-container">
        <div class="d-md-none toggle-sidebar-left" data-toggle-class="sidebar-left-opened" data-target="html"
            data-fire-event="sidebar-left-opened">
            <div style="width: 24px; height: 24px; display: flex; align-items: center;">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    class="icon icon-tabler icons-tabler-outline icon-tabler-grid-dots">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                    <path d="M5 5m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" />
                    <path d="M12 5m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" />
                    <path d="M19 5m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" />
                    <path d="M5 12m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" />
                    <path d="M12 12m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" />
                    <path d="M19 12m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" />
                    <path d="M5 19m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" />
                    <path d="M12 19m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" />
                    <path d="M19 19m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" />
                </svg>
            </div>
            <h2>Modulos</h2>
        </div>
        <tenant-dialog-header-menu></tenant-dialog-header-menu>

        <?php if($tenant_show_ads && $url_tenant_image_ads): ?>
            <div class="ms-3 me-3">
                <img src="<?php echo e($url_tenant_image_ads); ?>" style="max-height: 50px; max-width: 500px;">
            </div>
        <?php endif; ?>

        <!-- <?php if(config('configuration.multi_user_enabled')): ?>
            <tenant-multi-users-change-client></tenant-multi-users-change-client>
        <?php endif; ?> -->

        <div class="logo-container-mobile">
            <a href="<?php echo e(route('tenant.dashboard.index')); ?>" class="logo pt-2 pt-md-0">
                <?php if($vc_company->logo): ?>
                    <img src="<?php echo e(asset('storage/uploads/logos/' . $vc_company->logo)); ?>" alt="Logo" class="logo-light"
                        style="<?php echo e($vc_company->logo_dark ? '' : '--show-light-logo: block;'); ?>" />
                <?php else: ?>
                    <img src="<?php echo e(asset('logo/tulogo.png')); ?>" alt="Logo" />
                <?php endif; ?>

                <?php if($vc_company->logo_dark): ?>
                    <img src="<?php echo e(asset('storage/uploads/logos/' . $vc_company->logo_dark)); ?>" alt="Logo" class="logo-dark" />
                <?php endif; ?>
            </a>
        </div>

        <div class="options-user-mobile">
            <i class="fas fa-bars"></i>
        </div>
    </div>
    <div class="header-right">
        <div class="dropdown-menu-mobile" style="display: none;">
            <div class="user-content">
                <div class="close-container-user">
                    <i class="fas fa-times"></i>
                </div>
                <div>

                </div>
                <a href="#" class="user-profile-content">
                    <div class="profile-info" data-lock-name="<?php echo e($vc_user->email); ?>"
                        data-lock-email="<?php echo e($vc_user->email); ?>">
                        <span class="name"><?php echo e($vc_user->name); ?></span>
                        <span class="role"><?php echo e($vc_user->email); ?></span>
                    </div>
                    <figure class="profile-picture">
                        
                        <div class="border rounded-circle text-center">
                            <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round"
                                class="icon icon-tabler icons-tabler-outline icon-tabler-user-square-rounded">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M12 13a3 3 0 1 0 0 -6a3 3 0 0 0 0 6z" />
                                <path d="M12 3c7.2 0 9 1.8 9 9s-1.8 9 -9 9s-9 -1.8 -9 -9s1.8 -9 9 -9z" />
                                <path d="M6 20.05v-.05a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v.05" />
                            </svg>
                        </div>
                    </figure>
                    
                </a>
            </div>
            <ul class="pendingWork-container">
                <li class="li-title-mobile">
                    <h4>Pendientes</h4>
                </li>
                <?php if($vc_document > 0): ?>
                    <li>
                        <a href="<?php echo e(route('tenant.documents.not_sent')); ?>"
                            class="notification-icon text-secondary navigation-options" data-toggle="tooltip"
                            data-placement="bottom" title="Comprobantes no enviados/por enviar">
                            <span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-bell">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path
                                        d="M10 5a2 2 0 1 1 4 0a7 7 0 0 1 4 6v3a4 4 0 0 0 2 3h-16a4 4 0 0 0 2 -3v-3a7 7 0 0 1 4 -6" />
                                    <path d="M9 17v1a3 3 0 0 0 6 0v-1" />
                                </svg>
                                <span class="ms-2">Comprobantes no enviados</span>
                                <span
                                    class="badge badge-pill badge-danger badge-up cart-item-count"><?php echo e($vc_document); ?></span>
                            </span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                class="icon icon-tabler icons-tabler-outline icon-tabler-chevron-right">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M9 6l6 6l-6 6" />
                            </svg>
                        </a>
                    </li>
                <?php endif; ?>
                <li>
                    <a href="<?php echo e(route('tenant_orders_index')); ?>"
                        class="notification-icon text-secondary navigation-options" data-toggle="tooltip"
                        data-placement="bottom" title="Pedidos pendientes">
                        <span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round"
                                class="icon icon-tabler icons-tabler-outline icon-tabler-shopping-cart">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M6 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                <path d="M17 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                <path d="M17 17h-11v-14h-2" />
                                <path d="M6 5l14 1l-1 7h-13" />
                            </svg>
                            <span class="ms-2">Pedidos pendientes</span>
                            <span class="badge badge-pill badge-info badge-up cart-item-count"><?php echo e($vc_orders); ?></span>
                        </span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="icon icon-tabler icons-tabler-outline icon-tabler-chevron-right">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M9 6l6 6l-6 6" />
                        </svg>
                    </a>
                </li>
                <?php if(in_array('cuenta', $vc_modules)): ?>
                    <?php if(in_array('account_users_list', $vc_module_levels)): ?>
                        <li>
                            <a role="menuitem" href="<?php echo e(route('tenant.payment.index')); ?>"
                                class="notification-icon text-secondary navigation-options">
                                <span>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round"
                                        class="icon icon-tabler icons-tabler-outline icon-tabler-receipt-dollar me-2">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path
                                            d="M5 21v-16a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v16l-3 -2l-2 2l-2 -2l-2 2l-2 -2l-3 2" />
                                        <path
                                            d="M14.8 8a2 2 0 0 0 -1.8 -1h-2a2 2 0 1 0 0 4h2a2 2 0 1 1 0 4h-2a2 2 0 0 1 -1.8 -1" />
                                        <path d="M12 6v10" />
                                    </svg>
                                    <span>Mis Pagos</span>
                                </span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                    class="icon icon-tabler icons-tabler-outline icon-tabler-chevron-right">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M9 6l6 6l-6 6" />
                                </svg>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>

            <ul class="pendingWork-container">
                <li class="li-title-mobile">
                    <h4>Sistema</h4>
                </li>
                <?php
                    $is_pse = $vc_company->send_document_to_pse;
                    $environment = 'SUNAT';
                    $is_ose = ($vc_company->soap_send_id === '02') ? true : false;
                    if ($is_pse) {
                        $environment = 'PSE';
                    }
                    if ($is_ose) {
                        $environment = 'OSE';
                    }
                    if ($is_ose && $is_pse) {
                        $environment = 'OSE-PSE';
                    }
                ?>
                <?php if($vc_company->soap_type_id == "01"): ?>
                    <li>
                        <a href="<?php if(in_array('configuration', $vc_modules)): ?><?php echo e(route('tenant.companies.create')); ?><?php else: ?> # <?php endif; ?>"
                            class="notification-icon text-secondary navigation-options" data-toggle="tooltip"
                            data-placement="bottom"
                            title="<?php echo e($environment); ?>: ENTORNO DE DEMOSTRACIÓN, pulse para ir a configuración"
                            style="background-color: transparent !important;">
                            <span class="options-sunat">
                                <i class="fas fa-2x fa-toggle-off me-2" style="font-size: 20px;"></i>
                                <span class="ms-2" style="display: flex; flex-direction: column;">
                                    <span>DEMO</span>
                                    <span>SUNAT Entorno de Demostración</span>
                                </span>
                            </span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                class="icon icon-tabler icons-tabler-outline icon-tabler-chevron-right">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M9 6l6 6l-6 6" />
                            </svg>
                        </a>
                    </li>
                <?php elseif($vc_company->soap_type_id == "02"): ?>
                    <li>
                        <a href="<?php if(in_array('configuration', $vc_modules)): ?><?php echo e(route('tenant.companies.create')); ?><?php else: ?> # <?php endif; ?>"
                            class="notification-icon text-secondary navigation-options" data-toggle="tooltip"
                            data-placement="bottom"
                            title="<?php echo e($environment); ?>: ENTORNO DE PRODUCCIÓN, pulse para ir a configuración">
                            <span class="options-sunat">
                                <i class="text-success fas fa-2x fa-toggle-on me-2"
                                    style="font-size: 20px; color: #28a745 !important"></i>
                                <span class="ms-2" style="display: flex; flex-direction: column;">
                                    <span>PROD</span>
                                    <span>SUNAT Entorno de Demostración</span>
                                </span>
                            </span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                class="icon icon-tabler icons-tabler-outline icon-tabler-chevron-right">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M9 6l6 6l-6 6" />
                            </svg>
                        </a>
                    </li>
                <?php else: ?>
                    <li>
                        <a href="<?php if(in_array('configuration', $vc_modules)): ?><?php echo e(route('tenant.companies.create')); ?><?php else: ?> # <?php endif; ?>"
                            class="notification-icon text-secondary navigation-options" data-toggle="tooltip"
                            data-placement="bottom" title="INTERNO: ENTORNO DE PRODUCCIÓN, pulse para ir a configuración">
                            <span class="options-sunat">
                                <i class="text-info fas fa-2x fa-toggle-on me-2"
                                    style="font-size: 20px; color: #398bf7!important;"></i>
                                <span class="ms-2" style="display: flex; flex-direction: column;">
                                    <span>INT</span>
                                    <span>SUNAT Entorno de Demostración</span>
                                </span>
                            </span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                class="icon icon-tabler icons-tabler-outline icon-tabler-chevron-right">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M9 6l6 6l-6 6" />
                            </svg>
                        </a>
                    </li>
                <?php endif; ?>
                <li>
                    <a class="style-switcher-open notification-icon text-secondary navigation-options" href="#">
                        <span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round"
                                class="icon icon-tabler icons-tabler-outline icon-tabler-paint me-2">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path
                                    d="M5 3m0 2a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v2a2 2 0 0 1 -2 2h-10a2 2 0 0 1 -2 -2z" />
                                <path d="M19 6h1a2 2 0 0 1 2 2a5 5 0 0 1 -5 5l-5 0v2" />
                                <path
                                    d="M10 15m0 1a1 1 0 0 1 1 -1h2a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-2a1 1 0 0 1 -1 -1z" />
                            </svg>
                            Estilos y temas
                        </span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="icon icon-tabler icons-tabler-outline icon-tabler-chevron-right">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M9 6l6 6l-6 6" />
                        </svg>
                    </a>
                </li>
            </ul>

            <ul class="log-out-container">
                <li class="btn-primary" role="menuitem" href="<?php echo e(route('logout')); ?>"
                    onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                    
<!-- <<<<<<< HEAD -->
                    <!-- <a>
                        <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none"
                            stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="icon icon-tabler icons-tabler-outline icon-tabler-logout me-2">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M14 8v-2a2 2 0 0 0 -2 -2h-7a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2 -2v-2" />
                            <path d="M9 12h12l-3 -3" />
                            <path d="M18 15l3 -3" />
                        </svg>
                        Cerrar Sesión
                    </a> -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none"
                        stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="icon icon-tabler icons-tabler-outline icon-tabler-logout me-2">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M14 8v-2a2 2 0 0 0 -2 -2h-7a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2 -2v-2" />
                        <path d="M9 12h12l-3 -3" />
                        <path d="M18 15l3 -3" />
                    </svg>
                    Cerrar Sesión
 <!-- dc3907b4 (fix(ui): mejora en estilos para movil) -->
                    <form id="logout-form" action="<?php echo e(route('logout')); ?>" method="POST" style="display: none;">
                        <?php echo csrf_field(); ?>
                    </form>
                </li>
            </ul>
        </div>
        <ul class="notifications mx-2">
            <?php
                $is_pse = $vc_company->send_document_to_pse;
                $environment = 'SUNAT';
                $is_ose = ($vc_company->soap_send_id === '02') ? true : false;
                if ($is_pse) {
                    $environment = 'PSE';
                }
                if ($is_ose) {
                    $environment = 'OSE';
                }
                if ($is_ose && $is_pse) {
                    $environment = 'OSE-PSE';
                }

                $productionClass = ($vc_company->soap_type_id == "02" && $is_ose) ? 'btn-success' : 'btn-primary';
            ?>
            <?php if($vc_company->soap_type_id == "1"): ?>
                <li>
                    <a href="<?php if(in_array('configuration', $vc_modules)): ?><?php echo e(route('tenant.companies.create')); ?><?php else: ?> # <?php endif; ?>"
                        class="btn-sunat btn-danger" data-toggle="tooltip" data-placement="bottom"
                        title="Clic para ver o cambiar la configuración del entorno y el tipo de conexión">
                        <span class="btn-title">Modo: DEMO</span>
                        <span style="font-size: 12px;">Conectado a <?php echo e($environment); ?></span>
                    </a>
                </li>
            <?php elseif($vc_company->soap_type_id == "02"): ?>
                <li>
                    <a href="<?php if(in_array('configuration', $vc_modules)): ?><?php echo e(route('tenant.companies.create')); ?><?php else: ?> # <?php endif; ?>"
                        class="btn-sunat <?php echo e($productionClass); ?>" data-toggle="tooltip" data-placement="bottom"
                        title="Clic para ver o cambiar la configuración del entorno y el tipo de conexión">
                        <span class="btn-title">PRODUCCIÓN</span>
                        <span style="font-size: 12px;">Conectado a <?php echo e($environment); ?></span>
                    </a>
                </li>
            <?php else: ?>
                <li>
                    <a href="<?php if(in_array('configuration', $vc_modules)): ?><?php echo e(route('tenant.companies.create')); ?><?php else: ?> # <?php endif; ?>"
                        class="btn-sunat btn-info" data-toggle="tooltip" data-placement="bottom"
                        title="Clic para ver o cambiar la configuración del entorno y el tipo de conexión">
                        <span class="btn-title">Modo: INTERNO</span>
                        <span style="font-size: 12px;">Conectado a SERVIDOR</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>

        <?php $systemUser = app('App\Models\System\User'); ?>
        <?php
            $supportUser = $systemUser::first();
            $hasSupportContact = $supportUser && ($supportUser->phone || $supportUser->whatsapp_number || $supportUser->address_contact);
        ?>

        <?php if($hasSupportContact): ?>
        <span class="separator show-left"></span>
        <ul class="notifications">
            <li>
            <a role="menuitem"  class="notification-icon text-secondary"  onclick="toggleSupportSidebar()" title="Soporte" data-toggle="tooltip">
                <svg width="22" height="22" viewBox="0 0 512 512" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                    <g id="Page-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                        <g id="support" fill="currentColor" transform="translate(42.666667, 42.666667)">
                            <path d="M379.734355,174.506667 C373.121022,106.666667 333.014355,-2.13162821e-14 209.067688,-2.13162821e-14 C85.1210217,-2.13162821e-14 45.014355,106.666667 38.4010217,174.506667 C15.2012632,183.311569 -0.101643453,205.585799 0.000508304259,230.4 L0.000508304259,260.266667 C0.000508304259,293.256475 26.7445463,320 59.734355,320 C92.7241638,320 119.467688,293.256475 119.467688,260.266667 L119.467688,230.4 C119.360431,206.121456 104.619564,184.304973 82.134355,175.146667 C86.4010217,135.893333 107.307688,42.6666667 209.067688,42.6666667 C310.827688,42.6666667 331.521022,135.893333 335.787688,175.146667 C313.347976,184.324806 298.68156,206.155851 298.667688,230.4 L298.667688,260.266667 C298.760356,283.199651 311.928618,304.070103 332.587688,314.026667 C323.627688,330.88 300.801022,353.706667 244.694355,360.533333 C233.478863,343.50282 211.780225,336.789048 192.906491,344.509658 C174.032757,352.230268 163.260418,372.226826 167.196286,392.235189 C171.132153,412.243552 188.675885,426.666667 209.067688,426.666667 C225.181549,426.577424 239.870491,417.417465 247.041022,402.986667 C338.561022,392.533333 367.787688,345.386667 376.961022,317.653333 C401.778455,309.61433 418.468885,286.351502 418.134355,260.266667 L418.134355,230.4 C418.23702,205.585799 402.934114,183.311569 379.734355,174.506667 Z M76.8010217,260.266667 C76.8010217,269.692326 69.1600148,277.333333 59.734355,277.333333 C50.3086953,277.333333 42.6676884,269.692326 42.6676884,260.266667 L42.6676884,230.4 C42.6676884,224.302667 45.9205765,218.668499 51.2010216,215.619833 C56.4814667,212.571166 62.9872434,212.571166 68.2676885,215.619833 C73.5481336,218.668499 76.8010217,224.302667 76.8010217,230.4 L76.8010217,260.266667 Z M341.334355,230.4 C341.334355,220.97434 348.975362,213.333333 358.401022,213.333333 C367.826681,213.333333 375.467688,220.97434 375.467688,230.4 L375.467688,260.266667 C375.467688,269.692326 367.826681,277.333333 358.401022,277.333333 C348.975362,277.333333 341.334355,269.692326 341.334355,260.266667 L341.334355,230.4 Z"></path>
                        </g>
                    </g>
                </svg>
            </a>
            </li>
        </ul>
        <?php endif; ?>
        <span class="separator"></span>
        <ul class="notifications">
            <li>
                <a href="<?php echo e(route('tenant_orders_index')); ?>" class="notification-icon text-secondary"
                    data-toggle="tooltip" data-placement="bottom" title="Pedidos pendientes">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="icon icon-tabler icons-tabler-outline icon-tabler-shopping-cart">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M6 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                        <path d="M17 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                        <path d="M17 17h-11v-14h-2" />
                        <path d="M6 5l14 1l-1 7h-13" />
                    </svg>
                    <span class="badge badge-pill badge-info badge-up cart-item-count"><?php echo e($vc_orders); ?></span>
                </a>
            </li>
        </ul>
        <?php if(in_array('reports', $vc_modules) && $vc_finished_downloads > 0): ?>
            <span class="separator"></span>
            <ul class="notifications">
                <li>

                    <a href="<?php echo e(route('tenant.reports.download-tray.index')); ?>" class="notification-icon text-secondary"
                        data-toggle="tooltip" data-placement="bottom" title="Bandeja de descargas (Reportes procesados)">
                        <i class="fas fa-file-download text-secondary"></i>
                        <span
                            class="badge badge-pill badge-info badge-up cart-item-count"><?php echo e($vc_finished_downloads); ?></span>
                    </a>
                </li>
            </ul>
        <?php endif; ?>
        <span class="separator"></span>
        <ul class="notifications">
            <li>
                <tenant-notifications-header
                    :initial-count="<?php echo e($vc_document); ?>"
                    :redirect-url="<?php echo e(json_encode(route('tenant.documents.not_sent'))); ?>">
                </tenant-notifications-header>
            </li>
        </ul>
        <span class="separator"></span>
<ul class="notifications">
<div id="userbox" class="userbox">
        <div id="userbox" class="userbox">
            <a href="#" class="user-profile-content check-double" style="cursor: pointer;">
                <div class="profile-info profile-info-pc" data-lock-name="<?php echo e($vc_user->email); ?>"
                    data-lock-email="<?php echo e($vc_user->email); ?>">
                    <span class="name"><?php echo e($vc_user->name); ?></span>
                    <span class="role"><?php echo e($vc_user->email); ?></span>
                </div>
                <figure class="profile-picture">
                    
                    <div class="border rounded-circle text-center name-initials-container" style="width: 25px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="icon icon-tabler icons-tabler-outline icon-tabler-user-square-rounded svg-profile">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M12 13a3 3 0 1 0 0 -6a3 3 0 0 0 0 6z" />
                            <path d="M12 3c7.2 0 9 1.8 9 9s-1.8 9 -9 9s-9 -1.8 -9 -9s1.8 -9 9 -9z" />
                            <path d="M6 20.05v-.05a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v.05" />
                        </svg>
                        <?php
                            $userName = $vc_user->name;
                            $nameParts = explode(' ', trim($userName));
                            if (count($nameParts) >= 2) {
                                // Si hay al menos 2 palabras, tomar la primera letra de las primeras dos
                                $initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
                            } else {
                                // Si solo hay una palabra, tomar las dos primeras letras
                                $initials = strtoupper(substr($userName, 0, 2));
                            }
                        ?>
                        <span class="name-initials" style="display: none;"><?php echo e($initials); ?></span>
                    </div>
                </figure>
                
             </a>
            <div class="dropdown-menu-desktop">
                <ul class="list-unstyled mb-0">
                    <li class="user-profile-li" style="display: none">
                        <a class="" href="#" style="text-decoration: none;">
                            <div class="profile-info " data-lock-name="<?php echo e($vc_user->email); ?>"
                                data-lock-email="<?php echo e($vc_user->email); ?>">
                                <span class="name text-start"><?php echo e($vc_user->name); ?></span>
                                <span class="role text-start"><?php echo e($vc_user->email); ?></span>
                            </div>
                        </a>
                    </li>
                    <li class="divider divider-theme-black" style="display: none"></li>
                    <?php if(in_array('cuenta', $vc_modules)): ?>
                        <?php if(in_array('account_users_list', $vc_module_levels)): ?>
                            <li>
                                <a class="dropdown-item d-flex align-items-center" role="menuitem" href="<?php echo e(route('tenant.payment.index')); ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round"
                                        class="icon icon-tabler icons-tabler-outline icon-tabler-receipt-dollar me-2">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path
                                            d="M5 21v-16a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v16l-3 -2l-2 2l-2 -2l-2 2l-2 -2l-3 2" />
                                        <path
                                            d="M14.8 8a2 2 0 0 0 -1.8 -1h-2a2 2 0 1 0 0 4h2a2 2 0 1 1 0 4h-2a2 2 0 0 1 -1.8 -1" />
                                        <path d="M12 6v10" />
                                    </svg>
                                    <span>Mis Pagos</span>
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>
                    <li>
                        <a class="dropdown-item d-flex align-items-center style-switcher-open" href="#">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round"
                                class="icon icon-tabler icons-tabler-outline icon-tabler-paint me-2">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path
                                    d="M5 3m0 2a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v2a2 2 0 0 1 -2 2h-10a2 2 0 0 1 -2 -2z" />
                                <path d="M19 6h1a2 2 0 0 1 2 2a5 5 0 0 1 -5 5l-5 0v2" />
                                <path
                                    d="M10 15m0 1a1 1 0 0 1 1 -1h2a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-2a1 1 0 0 1 -1 -1z" />
                            </svg>
                            Estilos y temas</a>
                    </li>

                    <?php
                        $establishments = App\Models\Tenant\Establishment::select('id', 'description')->get();
                        $current =  auth()->user()->establishment_id;

                        $headerVisual = optional(App\Models\Tenant\Configuration::first())->visual;

                        $headerMultiUserCount = 0;
                        if (config('configuration.multi_user_enabled')) {
                            try {
                                $headerWebsite = app(\Hyn\Tenancy\Environment::class)->tenant();
                                $headerCurrentClient = \App\Models\System\Client::currentClientByWebsite($headerWebsite)->first();
                                if ($headerCurrentClient && auth()->check()) {
                                    $headerCurrentUser = auth()->user();
                                    if (!empty($headerCurrentUser->is_multi_user) && $headerCurrentUser->is_multi_user) {
                                        $headerOriginMulti = \Modules\MultiUser\Models\System\MultiUser::find($headerCurrentUser->multi_user_id);
                                        if ($headerOriginMulti) {
                                            $headerMultiUserCount = \Modules\MultiUser\Models\System\MultiUser::where('origin_client_id', $headerOriginMulti->origin_client_id)
                                                ->where('origin_user_id', $headerOriginMulti->origin_user_id)
                                                ->count() + 1;
                                        }
                                    } else {
                                        $headerMultiUserCount = \Modules\MultiUser\Models\System\MultiUser::where('origin_client_id', $headerCurrentClient->id)
                                            ->where('origin_user_id', $headerCurrentUser->id)
                                            ->count() + 1;
                                    }
                                }
                            } catch (\Exception $e) {
                                $headerMultiUserCount = 0;
                            }
                        }
                        $headerShowMultiUser = $headerMultiUserCount > 1 && config('configuration.multi_user_enabled');
                        $headerDefaultSidebarVisibility = (count($establishments) > 1) || $headerShowMultiUser;

                        if (is_object($headerVisual) && property_exists($headerVisual, 'branch_selector_in_sidebar')) {
                            $branchSelectorInSidebar = (bool) $headerVisual->branch_selector_in_sidebar;
                        } elseif (is_array($headerVisual) && array_key_exists('branch_selector_in_sidebar', $headerVisual)) {
                            $branchSelectorInSidebar = (bool) $headerVisual['branch_selector_in_sidebar'];
                        } else {
                            $branchSelectorInSidebar = $headerDefaultSidebarVisibility;
                        }

                        $headerMultiUserPresent = config('configuration.multi_user_enabled') && $headerShowMultiUser;
                        $headerMultiUserVisible = $headerMultiUserPresent && !$branchSelectorInSidebar;
                        $headerBranchVisible = (auth()->user()->type == 'admin') && !$branchSelectorInSidebar;
                        $headerSectionVisible = $headerMultiUserVisible || $headerBranchVisible;
                    ?>

                    <li id="multi-user-content-divider" class="divider my-2" <?php if(!$headerSectionVisible): ?> style="display: none;" <?php endif; ?>></li>

                    <li id="multi-user-content-li" class="multi-user-content px-4 pb-1" data-branch-in-sidebar="<?php echo e($branchSelectorInSidebar ? '1' : '0'); ?>" <?php if(!$headerSectionVisible): ?> style="display: none;" <?php endif; ?>>
                        <?php if(config('configuration.multi_user_enabled')): ?>
                            <div id="header-multi-user-selector-container" style="display: <?php echo e($branchSelectorInSidebar ? 'none' : 'block'); ?>;">
                                <tenant-multi-users-change-client></tenant-multi-users-change-client>
                            </div>
                        <?php endif; ?>
                        
                        <?php if(auth()->user()->type == 'admin'): ?>
                           <div id="header-establishment-selector-container" style="display: <?php echo e($branchSelectorInSidebar ? 'none' : 'block'); ?>;">
                               <tenant-hotel-sucursale
                                :establishments='<?php echo json_encode($establishments, 15, 512) ?>'
                                :current_establishment=<?php echo e($current); ?>

                               ></tenant-hotel-sucursale>
                           </div>
                        <?php endif; ?>
                    </li>
                    <li class="px-4 pb-1" style="height: auto; display: block;">
                        <tenant-dedicated-group-selector></tenant-dedicated-group-selector>
                    </li>

                    <li class="divider my-2"></li>

                    <li>
                        <a class="dropdown-item d-flex align-items-center" role="menuitem" href="<?php echo e(route('logout')); ?>"
                            onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round"
                                class="icon icon-tabler icons-tabler-outline icon-tabler-door-exit me-2">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M13 12v.01" />
                                <path d="M3 21h18" />
                                <path d="M5 21v-16a2 2 0 0 1 2 -2h7.5m2.5 10.5v7.5" />
                                <path d="M14 7h7m-3 -3l3 3l-3 3" />
                            </svg>
                            Cerrar Sesión
                        </a>
                        <form id="logout-form" action="<?php echo e(route('logout')); ?>" method="POST" style="display: none;">
                            <?php echo csrf_field(); ?>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</header>

<!-- Sidebar de Soporte -->
<?php if($hasSupportContact): ?>
<div id="supportSidebar" class="support-sidebar">
    <div class="support-header">
        Soporte al Cliente
        <button class="close-btn" onclick="toggleSupportSidebar()">&times;</button>
    </div>
    <div class="support-body">
        <?php if($supportUser && $supportUser->introduction): ?>
        <div class="support-intro richtext mb-3">
            <?php echo $supportUser->introduction; ?>

        </div>
        <?php endif; ?>

        <div class="support-contacts">
            <?php if($supportUser && $supportUser->whatsapp_number): ?>
            <div class="support-container support-whatsapp">
                <div class="icon-support-container support-left">
                    <div>
                        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-brand-whatsapp">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                            <path d="M3 21l1.65 -3.8a9 9 0 1 1 3.4 2.9l-5.05 .9" />
                            <path d="M9 10a.5 .5 0 0 0 1 0v-1a.5 .5 0 0 0 -1 0v1a5 5 0 0 0 5 5h1a.5 .5 0 0 0 0 -1h-1a.5 .5 0 0 0 0 1" />
                        </svg>
                    </div>
                </div>
                <div class="support-right">
                    <strong>WhatsApp</strong>
                    <a class="support-link support-link-whatsapp d-flex flex-column" href="https://wa.me/<?php echo e($supportUser->whatsapp_number); ?>" target="_blank" style="color: #04966a !important">
                        <?php echo e($supportUser->whatsapp_number); ?>

                    </a>
                </div>
            </div>
            <?php endif; ?>

            <?php if($supportUser && $supportUser->phone): ?>
            <div class="support-container support-phone">
                <div class="icon-support-container support-left">
                    <div>
                        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-phone">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                            <path d="M5 4h4l2 5l-2.5 1.5a11 11 0 0 0 5 5l1.5 -2.5l5 2v4a2 2 0 0 1 -2 2a16 16 0 0 1 -15 -15a2 2 0 0 1 2 -2" />
                        </svg>
                    </div>
                </div>
                <div class="support-right">
                    <strong>Teléfono</strong>
                    <a class="support-link support-link-phone d-flex flex-column" href="tel:<?php echo e($supportUser->phone); ?>" style="color: #ea580b !important">
                        <?php echo e($supportUser->phone); ?>

                    </a>
                </div>
            </div>
            <?php endif; ?>

            <?php if($supportUser && $supportUser->address_contact): ?>
            <div class="support-container support-email">
                <div class="icon-support-container support-left">
                    <div>
                        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-mail">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                            <path d="M3 7a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v10a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-10z" />
                            <path d="M3 7l9 6l9 -6" />
                        </svg>
                    </div>
                </div>
                <div class="support-right">
                    <strong>Correo Electrónico</strong>
                    <a class="support-link support-link-email d-flex flex-column" href="mailto:<?php echo e($supportUser->address_contact); ?>" target="_blank" style="color: #2663eb !important">
                        <?php echo e($supportUser->address_contact); ?>

                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<div id="supportBackdrop" class="support-backdrop" onclick="toggleSupportSidebar()"></div>
<?php endif; ?>

<?php $__env->startPush('scripts'); ?>
<script>
    function toggleSupportSidebar() {
        const sidebar = document.getElementById('supportSidebar');
        const backdrop = document.getElementById('supportBackdrop');
        sidebar.classList.toggle('show');
        backdrop.classList.toggle('show');
    }
</script>
<?php $__env->stopPush(); ?>
<?php /**PATH C:\Aplicaciones\laragon\sites\buho\pro9dev001\resources\views/tenant/layouts/partials/header.blade.php ENDPATH**/ ?>