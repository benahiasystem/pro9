@extends('tenant.layouts.app')

@section('content')
    {{-- ######## INICIO VERSIONES DE IMÁGENES DE TICKETS ######## --}}
    @php
        $ticketImageVersions = [];
        foreach (glob(public_path('templates/pdf/*/ticket.png')) ?: [] as $ticketImage) {
            $ticketImageVersions[basename(dirname($ticketImage))] = filemtime($ticketImage);
        }
    @endphp
    {{-- ######## FIN VERSIONES DE IMÁGENES DE TICKETS ######## --}}
    <div class="row">
        <div class="col-md-12">
            <tenant-configurations-ticket-pdf
	            :type-user="{{ json_encode(auth()->user()->type) }}"
                :establishment-id="{{ json_encode($establishment_id) }}"
                :ticket-image-versions="{{ json_encode($ticketImageVersions) }}"
                :establishments="{{ json_encode($establishments) }}">
	        </tenant-configurations-ticket-pdf>
        </div>
    </div>
@endsection
