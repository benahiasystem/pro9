{{-- Identidad fiscal para facturas de 58 mm. --}}
{{-- ######## INICIO NÚMERO DE FACTURA EN TICKET ######## --}}
<div>N° : <strong>{{ $ticketFiscal['number_full'] ?? $document_number }}</strong></div>
{{-- ######## FIN NÚMERO DE FACTURA EN TICKET ######## --}}
<div>N° de control: <strong>{{ ($ticketFiscal['control_number'] ?? null) ?: 'No asignado' }}</strong></div>
