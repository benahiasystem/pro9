{{-- Identidad fiscal para facturas de 58 mm. --}}
<div>N° de documento: <strong>{{ $ticketFiscal['number_full'] ?? $document_number }}</strong></div>
<div>N° de control: <strong>{{ ($ticketFiscal['control_number'] ?? null) ?: 'No asignado' }}</strong></div>
