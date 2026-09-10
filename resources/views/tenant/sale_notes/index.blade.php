{{-- ######## INICIO MODALIDAD DE EMISIÓN FISCAL ######## --}}
@extends('tenant.layouts.app')

@section('content')

    <tenant-sale-notes-index
        :company-environment="{{ json_encode($company_environment) }}"
        :type-user="{{ json_encode(auth()->user()->type) }}"
        :configuration="{{\App\Models\Tenant\Configuration::getPublicConfig()}}"
    ></tenant-sale-notes-index>

@endsection
{{-- ######## FIN MODALIDAD DE EMISIÓN FISCAL ######## --}}
