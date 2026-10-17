<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class MassiveInvoiceExport extends DefaultValueBinder implements
    FromCollection,
    WithHeadings,
    WithMapping,
    WithColumnFormatting,
    WithCustomValueBinder,
    \Maatwebsite\Excel\Concerns\WithEvents,
    ShouldAutoSize
{
    use Exportable;

    /** @var Collection */
    protected $records;

    public function records($records)
    {
        $this->records = $records;

        return $this;
    }

    public function collection()
    {
        return $this->records;
    }

    public function headings(): array
    {
        return [
            'Fecha emisión',
            'Fecha vencimiento',
            'RUC emisor',
            'Razón social emisor',
            'Tipo comprobante',
            'Serie-Número',
            'Doc. receptor',
            'Razón social cliente',
            'Correo',
            'Moneda',
            'Estado',
            'Total gravado',
            // ########## INICIO CAMBIO IGV A IVA
            'Total IVA',
            // ######### FIN CAMBIO IGV A IVA
            'Total venta',
        ];
    }

    public function map($row): array
    {
        [$rucEmisor, $razonEmisor] = $this->splitDocumentAndName($row->ruc_emisor);
        [$docReceptor, $razonCliente] = $this->splitDocumentAndName($row->ruc);

        return [
            $this->formatDate($row->fecha_emision),
            $this->formatDate($row->fecha_vencimiento),
            (string) $rucEmisor,
            $razonEmisor,
            $this->tipoComprobante($row->tipo_comprobante),
            (string) $row->serie_comprobante,
            (string) $docReceptor,
            $razonCliente,
            $row->correo ?? '',
            $row->moneda ?? '',
            $row->estado_emision ?: ($row->status ?? ''),
            \App\Support\Exact\Decimal::represent(\App\Support\Exact\Decimal::readRequired($row,'total_gravado')),
            \App\Support\Exact\Decimal::represent(\App\Support\Exact\Decimal::readRequired($row,'total_igv')),
            \App\Support\Exact\Decimal::represent(\App\Support\Exact\Decimal::readRequired($row,'total_venta')),
        ];
    }

    public function columnFormats(): array
    {
        return [
            'C' => NumberFormat::FORMAT_TEXT,
            'F' => NumberFormat::FORMAT_TEXT,
            'G' => NumberFormat::FORMAT_TEXT,
            'L' => NumberFormat::FORMAT_TEXT,
            'M' => NumberFormat::FORMAT_TEXT,
            'N' => NumberFormat::FORMAT_TEXT,
        ];
    }

    public function bindValue(Cell $cell, $value)
    {
        if (is_string($value)) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function registerEvents(): array
    {
        return [\Maatwebsite\Excel\Events\AfterSheet::class=>function ($event) {
            \App\Support\Exact\FractionSheet::append($event->sheet->getDelegate(),$this->records,
                ['cantidad','precio','total_gravado','total_igv','total_venta']);
        }];
    }

    protected function splitDocumentAndName(?string $value): array
    {
        if (!$value) {
            return ['', ''];
        }

        $parts = explode(' - ', $value, 2);

        return [
            $parts[0] ?? '',
            $parts[1] ?? ($parts[0] ?? ''),
        ];
    }

    protected function formatDate($date): string
    {
        if (!$date) {
            return '';
        }

        try {
            return Carbon::parse($date)->format('d/m/Y');
        } catch (\Throwable $e) {
            return (string) $date;
        }
    }

    protected function tipoComprobante(?string $tipo): string
    {
        $tipos = [
            // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
            '01' => 'FACTURA',
            // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES
        ];

        return $tipos[$tipo] ?? (string) $tipo;
    }
}
