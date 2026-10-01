@php $reportFiscalIdentity = \App\Services\Fiscal\FiscalIdentity::forDocument($value); @endphp
@if($reportFiscalIdentity['control_number'])<br>Control: {{$reportFiscalIdentity['control_number']}}@endif
