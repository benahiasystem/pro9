<div style="border: 1px solid #555; padding: 5px; margin-bottom: 6px; font-size: 9pt;">
    @if($fiscal['environment'] === 'demo')
        <div style="font-weight: bold; text-align: center;">DEMO — SIN VALIDEZ FISCAL</div>
    @endif
    <div>N° de documento: <strong>{{ \App\Services\Fiscal\FiscalIdentity::displayNumber($fiscal['document_number']) }}</strong></div>
    @if($fiscal['series'] !== '')<div>Serie: {{ $fiscal['series'] }}</div>@endif
    <div>N° de control: <strong>{{ $fiscal['control_number'] ?: 'No asignado' }}</strong></div>
</div>
