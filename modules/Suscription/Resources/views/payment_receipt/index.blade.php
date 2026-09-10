{{-- ######## INICIO MODALIDAD DE EMISIÓN FISCAL ######## --}}
@extends('tenant.layouts.app')

@section('content')

    <tenant-index-payment-receipt
        :configuration="{{\App\Models\Tenant\Configuration::getPublicConfig()}}"
        :company-environment="{{ json_encode($company_environment) }}">
        </tenant-index-payment-receipt>




@endsection
{{-- ######## FIN MODALIDAD DE EMISIÓN FISCAL ######## --}}
