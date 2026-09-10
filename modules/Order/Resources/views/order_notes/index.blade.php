{{-- ######## INICIO MODALIDAD DE EMISIÓN FISCAL ######## --}}
@extends('tenant.layouts.app')

@section('content')

    <tenant-order-notes-index :type-user="{{json_encode(Auth::user()->type)}}"
                              :company-environment="{{ json_encode($company_environment) }}"
                              :configuration="{{$configuration}}"></tenant-order-notes-index>

@endsection
{{-- ######## FIN MODALIDAD DE EMISIÓN FISCAL ######## --}}
