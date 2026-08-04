@extends('tenant.layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pos.css') }}"/>
@endpush

@section('content')
    <tenant-pos-fast
      :configuration2="{{\App\Models\Tenant\Configuration::getPublicConfig()}}"
      :configuration="{{\App\Models\Tenant\Configuration::getPublicConfig()}}"
      :soap-company="{{ json_encode($soap_company) }}"
      :business-turns="{{ $business_turns }}"
      :type-user="{{json_encode(Auth::user()->type)}}"
      :is-print="{{json_encode($configuration->auto_print)}}">
    </tenant-pos-fast>
@endsection
