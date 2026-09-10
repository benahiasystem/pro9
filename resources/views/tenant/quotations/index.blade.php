{{-- ######## INICIO MODALIDAD DE EMISIÓN FISCAL ######## --}}
@extends('tenant.layouts.app')

@section('content')

    <tenant-quotations-index
    	:type-user="{{json_encode(Auth::user()->type)}}"
        :company-environment="{{ json_encode($company_environment) }}"
    	:generate-order-note-from-quotation="{{ json_encode($generate_order_note_from_quotation) }}">
    </tenant-quotations-index>

@endsection
{{-- ######## FIN MODALIDAD DE EMISIÓN FISCAL ######## --}}
