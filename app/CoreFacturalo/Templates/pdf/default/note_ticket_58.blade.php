{{-- Representación local compacta; las fracciones se conservan en el documento. --}}
@php
    $note=$document->note;
    $affected=$note->affected_document ? $note->affected_document->number_full
        : \App\Services\Fiscal\FiscalIdentity::numberFull($note->data_affected_document->series ?? '',$note->data_affected_document->number ?? '');
    $credit=$document->document_type_id === '07';
@endphp
<html><head><meta charset="UTF-8"></head><body style="font-family: sans-serif; font-size: 8px;">
<div style="text-align:center"><strong>{{ $company->name }}</strong><br>RIF {{ $company->number }}<br>
<strong>{{ $credit ? 'NOTA DE CRÉDITO' : 'NOTA DE DÉBITO' }}</strong><br>{{ $document->number_full }}</div>
<p>Fecha: {{ $document->date_of_issue->format('Y-m-d') }}<br>Cliente: {{ $document->customer->name }}<br>
Documento afectado: {{ $affected }}<br>Motivo: {{ $note->note_description }}</p>
<table style="width:100%; border-collapse:collapse"><tbody>
@foreach($document->items as $row)
<tr style="border-top:1px solid #555"><td colspan="2">{{ $row->item->description }} @include('pdf.partials.note_lots', ['row' => $row])</td></tr>
<tr><td>{{ \App\Support\Exact\Decimal::represent(\App\Support\Exact\Decimal::read($row,'quantity')) }} {{ $row->item->unit_type_id }} × {{ $row->formatPdfUnitPrice() }}</td>
<td style="text-align:right">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\PdfAmounts::read($row,'total'),2) }}</td></tr>
@endforeach
@if(\App\Support\Exact\Decimal::read($document,'total_taxed')->isPositive())<tr style="border-top:1px solid #555"><td>Gravado</td><td style="text-align:right">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\PdfAmounts::read($document,'total_taxed'),2) }}</td></tr>@endif
@if(\App\Support\Exact\Decimal::read($document,'total_exonerated')->isPositive())<tr><td>Exonerado</td><td style="text-align:right">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\PdfAmounts::read($document,'total_exonerated'),2) }}</td></tr>@endif
@if(\App\Support\Exact\Decimal::read($document,'total_exportation')->isPositive())<tr><td>Exportación</td><td style="text-align:right">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\PdfAmounts::read($document,'total_exportation'),2) }}</td></tr>@endif
@if(\App\Support\Exact\Decimal::read($document,'total_discount')->isPositive())<tr><td>Descuentos</td><td style="text-align:right">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\PdfAmounts::read($document,'total_discount'),2) }}</td></tr>@endif
@if(\App\Support\Exact\Decimal::read($document,'total_charge')->isPositive())<tr><td>Cargos</td><td style="text-align:right">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\PdfAmounts::read($document,'total_charge'),2) }}</td></tr>@endif
<tr><td>IVA</td><td style="text-align:right">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\PdfAmounts::read($document,'total_igv'),2) }}</td></tr>
@if(\App\Support\Exact\Decimal::read($document,'total_other_taxes')->isPositive())<tr><td>Otros impuestos</td><td style="text-align:right">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\PdfAmounts::read($document,'total_other_taxes'),2) }}</td></tr>@endif
<tr style="border-top:1px solid #555"><td><strong>{{ $credit ? 'TOTAL NOTA DE CRÉDITO' : 'TOTAL NOTA DE DÉBITO' }} ({{ $document->currency_type_id }})</strong></td>
<td style="text-align:right"><strong>{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\PdfAmounts::read($document,'total'),2) }}</strong></td></tr>
</tbody></table>
@foreach((array)$document->legends as $legend)<p style="font-size:7px">{{ $legend->value ?? '' }}</p>@endforeach
</body></html>
