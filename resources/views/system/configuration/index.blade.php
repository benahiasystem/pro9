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

    <system-configuration-sections>

        {{-- General --}}
        <system-login-other-configuration slot="other" :plans='@json($plans)'></system-login-other-configuration>
        <system-configuration-apk-url slot="apk"></system-configuration-apk-url>

        {{-- Apariencia --}}
        <system-login-settings slot="login" :configuration='@json($configuration)'></system-login-settings>
        <system-configuration-themes slot="themes"></system-configuration-themes>
        <system-configuration-visible-columns slot="columns"></system-configuration-visible-columns>

        {{-- Integraciones --}}
        <system-openai-configuration slot="openai"></system-openai-configuration>
        <system-google-maps-configuration slot="maps" :configuration='@json($configuration)'></system-google-maps-configuration>
        <system-configuration-token slot="ruc"></system-configuration-token>
        <system-git-repository-configuration slot="git"></system-git-repository-configuration>

        {{-- Pagos --}}
        <system-configuration-payment-gateway slot="gateway"></system-configuration-payment-gateway>
        <system-cron-order-configuration slot="cron" :configuration='@json($configuration)'></system-cron-order-configuration>

        {{-- Notificaciones --}}
        <system-email-configuration slot="email" :configuration='@json($configuration)'></system-email-configuration>
        <system-whatsapp-notify-configuration slot="whatsapp" :configuration='@json($configuration)'></system-whatsapp-notify-configuration>

        {{-- Soporte y legal --}}
        <system-support-configuration slot="support"></system-support-configuration>
        <system-terms-configuration slot="terms"></system-terms-configuration>

    </system-configuration-sections>

@endsection
