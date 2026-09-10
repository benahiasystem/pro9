{{-- ######## INICIO MODALIDAD DE EMISIÓN FISCAL ######## --}}
@extends('tenant.layouts.app')

@section('content')

    <affiliation-receipts
        :configuration="{{\App\Models\Tenant\Configuration::getPublicConfig()}}"
        :company-environment="{{ json_encode($company_environment) }}">
    </affiliation-receipts>

@endsection
{{-- ######## FIN MODALIDAD DE EMISIÓN FISCAL ######## --}}
