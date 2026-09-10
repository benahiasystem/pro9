{{-- ######## INICIO MODALIDAD DE EMISIÓN FISCAL ######## --}}
@extends('tenant.layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pos.css') }}?v={{ filemtime(public_path('css/pos.css')) }}"/>
@endpush

@section('content')
    <tenant-pos-fast
      :configuration2="{{\App\Models\Tenant\Configuration::getPublicConfig()}}"
      :configuration="{{\App\Models\Tenant\Configuration::getPublicConfig()}}"
      :company-environment="{{ json_encode($company_environment) }}"
      :business-turns="{{ $business_turns }}"
      :type-user="{{json_encode(Auth::user()->type)}}"
      :is-print="{{json_encode($configuration->auto_print)}}">
    </tenant-pos-fast>
@endsection
{{-- ######## FIN MODALIDAD DE EMISIÓN FISCAL ######## --}}
