<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8"><title>Documentos de caja</title>
    <style>
        @page { margin: 14px; } body { font-family: sans-serif; font-size: 9px; }
        table { border-collapse: collapse; width: 100%; } th,td { border: 1px solid #777; padding: 4px; }
        th { background: #dcebf7; } .amount { text-align: right; }
    </style>
</head>
<body>
    <h3>Documentos asociados a caja</h3>
    <p>@include('partials.report_company_header', ['showEmpresaLabel'=>false]) — RIF {{ $company->number }}</p>
    <p>Importes documentales; no representan necesariamente dinero cobrado.</p>
    @if(count($report['rows']))
    <table>
        <thead><tr><th>Tipo</th><th>Documento</th><th>Fecha</th><th>Doc. afectado</th><th>Cliente</th><th>Identificación</th><th>Estado</th><th>Moneda</th><th>Base imponible</th><th>IVA</th><th>Total</th></tr></thead>
        <tbody>
        @foreach($report['rows'] as $row)
            <tr>
                <td data-type="s">{{ $row['document_type_description'] }}</td><td data-type="s">{{ $row['number'] }}</td>
                <td data-type="s">{{ $row['date_of_issue'] }}</td><td data-type="s">{{ $row['affected_document'] }}</td>
                <td data-type="s">{{ $row['customer_name'] }}</td><td data-type="s">{{ $row['customer_number'] }}</td>
                <td data-type="s">{{ $row['state_type_description'] }}</td><td data-type="s">{{ $row['currency_type_id'] }}</td>
                @foreach(\Modules\Report\Services\PosCashDocumentsReport::FIELDS as $field)
                    <td class="amount" data-type="s" style="mso-number-format:'\@'">{{ $row[$field] }}</td>
                @endforeach
            </tr>
        @endforeach
        @foreach($report['totals'] as $currency=>$totals)
            <tr><td colspan="8">Total {{ $currency }}</td>
                @foreach(\Modules\Report\Services\PosCashDocumentsReport::FIELDS as $field)
                    <td class="amount" data-type="s" style="mso-number-format:'\@'">{{ \App\Support\Exact\Decimal::format($totals[$field],2,'.','') }}</td>
                @endforeach
            </tr>
        @endforeach
        </tbody>
    </table>
    @else
        <p>No se encontraron documentos para los filtros indicados.</p>
    @endif
</body>
</html>
