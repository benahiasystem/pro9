{{-- ######## INICIO MODALIDAD DE EMISIÓN FISCAL ######## --}}
@extends('tenant.layouts.app')

@section('content')

    <tenant-dashboard-index
    	:type-user="{{ json_encode(auth()->user()->type) }}"
        :company-environment="{{ json_encode($company_environment) }}"
        :configuration="{{ json_encode($configuration) }}">
    </tenant-dashboard-index>

@endsection
{{-- ######## FIN MODALIDAD DE EMISIÓN FISCAL ######## --}}
