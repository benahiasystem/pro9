{{-- ######## INICIO CALCULOS EXACTOS SAB ######## --}}
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Reporte por cliente</title></head>
<body>
    <h3>REPORTE POR CLIENTE</h3>
    <table>
        @if($records->isNotEmpty())
        <tr><td>Cliente:</td><td>{{ format_person_identity_document($records->first()->customer) }} - {{ $records->first()->customer->name }}</td></tr>
        @endif
        <tr><td>Empresa:</td><td>@include('partials.report_company_header', ['showEmpresaLabel'=>false])</td><td>Fecha:</td><td>{{ date('Y-m-d') }}</td></tr>
        <tr><td>RIF:</td><td>{{ $company->number }}</td><td>Establecimiento:</td><td>{{ $establishment->address }} - {{ optional($establishment->department)->description }} - {{ optional($establishment->district)->description }}</td></tr>
    </table>
    @if($records->isNotEmpty())
    @php $report=\Modules\Report\Services\CustomerReportAmounts::presentation($records); @endphp
    <table>
        <thead><tr><th>#</th><th>Fecha</th><th>Tipo Documento</th><th>Moneda</th><th>Serie</th><th>Número</th><th>Monto</th></tr></thead>
        <tbody>
        @foreach($report['rows'] as $entry)
            @php $document=$entry['model']; @endphp
            <tr>
                <td>{{ $loop->iteration }}</td><td>{{ $document->date_of_issue->format('Y-m-d') }}</td>
                <td>{{ $document->document_type->description }}</td><td>{{ $document->currency_type_id }}</td>
                <td data-type="s">{{ $document->series }}</td><td data-type="s">{{ $document->number }}</td>
                <td data-type="s" style="mso-number-format:'\@'">{{ $entry['display']['total'] }}</td>
            </tr>
        @endforeach
        @foreach($report['totals'] as $currency=>$total)
            <tr><td colspan="5"></td><td>TOTAL {{ $currency }}</td><td data-type="s" style="mso-number-format:'\@'">{{ \App\Support\Exact\Decimal::format($total,2,'.','') }}</td></tr>
        @endforeach
        </tbody>
    </table>
    @else
        <p>No se encontraron registros.</p>
    @endif
</body>
</html>
{{-- ######## FIN CALCULOS EXACTOS SAB ######## --}}
