{{-- ######## INICIO NUMERACIÓN FISCAL VENEZUELA ######## --}}
@php $reportFiscalIdentity = \App\Services\Fiscal\FiscalIdentity::forDocument($value); @endphp
@if($reportFiscalIdentity['control_number'])<br>Control: {{$reportFiscalIdentity['control_number']}}@endif
@if($reportFiscalIdentity['device_serial'])<br>Equipo: {{$reportFiscalIdentity['device_serial']}}@endif
@if($reportFiscalIdentity['original_number_full'])<br>Reserva original: {{$reportFiscalIdentity['original_number_full']}}@endif
{{-- ######## FIN NUMERACIÓN FISCAL VENEZUELA ######## --}}
