@extends('cashreport::layouts.a4')
{{-- ######## INICIO CLASIFICACION DE COBROS Y CAJA ######## --}}
@section('content')
    @include('cashreport::reports.partials.classified_balances')
    @include('cashreport::reports.partials.classified_movements')
@endsection
{{-- ######## FIN CLASIFICACION DE COBROS Y CAJA ######## --}}
