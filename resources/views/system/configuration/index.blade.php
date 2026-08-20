@extends('system.layouts.app')

@section('content')
    <div class="page-header pr-0">
        <h2><a href="/configurations">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-settings" style="margin-top: -3px"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M10.325 4.317c.426 -1.756 2.924 -1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543 -.94 3.31 .826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756 .426 1.756 2.924 0 3.35a1.724 1.724 0 0 0 -1.066 2.573c.94 1.543 -.826 3.31 -2.37 2.37a1.724 1.724 0 0 0 -2.572 1.065c-.426 1.756 -2.924 1.756 -3.35 0a1.724 1.724 0 0 0 -2.573 -1.066c-1.543 .94 -3.31 -.826 -2.37 -2.37a1.724 1.724 0 0 0 -1.065 -2.572c-1.756 -.426 -1.756 -2.924 0 -3.35a1.724 1.724 0 0 0 1.066 -2.573c-.94 -1.543 .826 -3.31 2.37 -2.37c1 .608 2.296 .07 2.572 -1.065z"></path><path d="M9 12a3 3 0 1 0 6 0a3 3 0 0 0 -6 0"></path></svg>
        </a></h2>
        <ol class="breadcrumbs">
            <li class="active"><span> Configuraciones </span></li>
        </ol>
    </div>

    {{--
        Las 16 secciones de configuración estaban apiladas en dos columnas sin
        agrupar: había que recorrer toda la página para encontrar cualquier
        ajuste. Ahora se reparten en cinco grupos temáticos. En escritorio la
        navegación va a la izquierda (patrón habitual de páginas de ajustes) y
        en celular pasa a una barra horizontal desplazable — ese cambio vive en
        mobile-admin.css, sección B8.
    --}}
    <div class="row">
        <div class="col-12">
            {{-- value: sin sección inicial Element UI no activa ninguna y el contenido queda en blanco --}}
            <el-tabs class="config-tabs" tab-position="left" value="apariencia">

                <el-tab-pane name="apariencia">
                    <span slot="label">Apariencia</span>
                    <div class="row">
                        <div class="col-xl-6 col-12">
                            <system-login-settings :configuration='@json($configuration)'></system-login-settings>
                            <system-configuration-themes></system-configuration-themes>
                        </div>
                        <div class="col-xl-6 col-12">
                            <system-configuration-visible-columns></system-configuration-visible-columns>
                        </div>
                    </div>
                </el-tab-pane>

                <el-tab-pane name="integraciones">
                    <span slot="label">Integraciones</span>
                    <div class="row">
                        <div class="col-xl-6 col-12">
                            <system-configuration-payment-gateway></system-configuration-payment-gateway>
                            <system-configuration-token></system-configuration-token>
                        </div>
                        <div class="col-xl-6 col-12">
                            <system-openai-configuration></system-openai-configuration>
                            <system-google-maps-configuration :configuration='@json($configuration)'></system-google-maps-configuration>
                        </div>
                    </div>
                </el-tab-pane>

                <el-tab-pane name="whatsapp">
                    <span slot="label">WhatsApp</span>
                    <div class="row">
                        <div class="col-xl-6 col-12">
                            <system-whatsapp-provider-configuration></system-whatsapp-provider-configuration>
                            <system-waha-servers-index></system-waha-servers-index>
                        </div>
                        <div class="col-xl-6 col-12">
                            <system-whatsapp-notify-configuration :configuration='@json($configuration)'></system-whatsapp-notify-configuration>
                        </div>
                    </div>
                </el-tab-pane>

                <el-tab-pane name="comunicacion">
                    <span slot="label">Comunicación</span>
                    <div class="row">
                        <div class="col-xl-6 col-12">
                            <system-email-configuration :configuration='@json($configuration)'></system-email-configuration>
                            <system-support-configuration></system-support-configuration>
                        </div>
                        <div class="col-xl-6 col-12">
                            <system-terms-configuration></system-terms-configuration>
                        </div>
                    </div>
                </el-tab-pane>

                <el-tab-pane name="sistema">
                    <span slot="label">Sistema</span>
                    <div class="row">
                        <div class="col-xl-6 col-12">
                            <system-login-other-configuration :plans='@json($plans)'></system-login-other-configuration>
                            <system-configuration-apk-url></system-configuration-apk-url>
                        </div>
                        <div class="col-xl-6 col-12">
                            <system-cron-order-configuration :configuration='@json($configuration)'></system-cron-order-configuration>
                        </div>
                    </div>
                </el-tab-pane>

            </el-tabs>
        </div>
    </div>

@endsection
