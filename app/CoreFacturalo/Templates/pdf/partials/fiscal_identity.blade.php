{{-- ######## INICIO NUMERACIÓN FISCAL VENEZUELA ######## --}}
<div style="border: 1px solid #555; padding: 5px; margin-bottom: 6px; font-size: 9pt;">
    @if($fiscal['environment'] === 'demo' || $fiscal['simulated'])
        <div style="font-weight: bold; text-align: center;">DEMO — SIN VALIDEZ FISCAL</div>
    @endif
    <div><strong>{{ $fiscal['status_label'] }}</strong></div>
    @if(!empty($fiscal['contingency']))
        <div><strong>Emisión en contingencia</strong></div>
    @endif
    @if(!empty($fiscal['print_replacement']))
        <div>Reemplaza el control inutilizado {{ $fiscal['print_replacement']['replaced_control_number'] }}.</div>
    @endif
    <div>N° de documento: <strong>{{ $fiscal['document_number'] }}</strong></div>
    @if($fiscal['series'] !== '')
        <div>Serie: {{ $fiscal['series'] }}</div>
    @endif
    <div>N° de control: <strong>{{ $fiscal['control_number'] ?: 'No asignado' }}</strong></div>
    @if($fiscal['device_serial'])
        <div>Registro del equipo: {{ $fiscal['device_serial'] }}</div>
    @endif
    @if(!empty($fiscal['affected_document']))
        <div><strong>Factura afectada:</strong> {{ $fiscal['affected_document']['series'] ? $fiscal['affected_document']['series'].'-' : '' }}{{ $fiscal['affected_document']['number'] }}</div>
        <div>Fecha de factura: {{ $fiscal['affected_document']['date_of_issue'] }}</div>
        @if($fiscal['affected_document']['control_number'])
            <div>Control de factura: {{ $fiscal['affected_document']['control_number'] }}</div>
        @elseif($fiscal['affected_document']['device_serial'])
            <div>Equipo de factura: {{ $fiscal['affected_document']['device_serial'] }}</div>
        @endif
    @endif
    @if($fiscal['printer'])
        <div>Imprenta: {{ $fiscal['printer']['name'] }} · RIF: {{ $fiscal['printer']['rif'] }}</div>
        <div>Autorización: {{ $fiscal['printer']['authorization'] }} · Fecha: {{ $fiscal['printer']['authorization_date'] }}</div>
        <div>Elaboración: {{ $fiscal['printer']['prepared_at'] }}</div>
        <div>Rango de controles: {{ $fiscal['printer']['start'] }} al {{ $fiscal['printer']['end'] }}</div>
    @endif
</div>
{{-- ######## FIN NUMERACIÓN FISCAL VENEZUELA ######## --}}
