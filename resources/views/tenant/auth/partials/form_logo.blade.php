@if ($useLoginGlobal)
    @if ($login->logo ?? false)
        @if ($login->show_logo_in_form)
            <img class="auth__logo-form" src="{{ $login->logo }}" alt="Logo formulario" />
        @endif
    @endif
@else
    @if ($login->show_logo_in_form)
        @if($company->logo)
{{-- ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE --}}
        <img class="auth__logo-form" src="{{ tenant_asset('storage/uploads/logos/' . $company->logo) }}" alt="Logo formulario" width="250" />
{{-- ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE --}}
        @else
        <img class="auth__logo-form" src="{{asset('logo/tulogo.png')}}" alt="Logo formulario" width="250" />
        @endif
    @endif
@endif
<br>
