<?php

namespace Modules\Item\Services;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ProductVariablesImportFormatExport implements FromArray, WithHeadings, ShouldAutoSize, WithEvents, WithTitle
{
    use Exportable;

    public function title(): string
    {
        return 'Atributos';
    }

    public function headings(): array
    {
        return ProductVariableCatalogParser::HEADINGS;
    }

    public function array(): array
    {
        return [
            ['Talla', 'lista', 'S', '', '1', '1'],
            ['Talla', 'lista', 'M', '', '2', '1'],
            ['Talla', 'lista', 'L', '', '3', '1'],
            ['Talla', 'lista', 'XL', '', '4', '1'],
            ['Color', 'color', 'Rojo', '#E53935', '1', '1'],
            ['Color', 'color', 'Azul', '#1E88E5', '2', '1'],
            ['Color', 'color', 'Negro', '#111111', '3', '1'],
            ['Material', 'lista', 'Algodón, Poliéster, Lino', '', '1', '1'],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->freezePane('A2');
                $sheet->getStyle('A1:F1')->getFont()->setBold(true);
                $sheet->getStyle('A1:F1')->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setRGB('E8EEF7');
                $sheet->getComment('A1')->getText()->createTextRun(
                    'Crea atributos y sus valores. No vincula productos. '
                    . 'Tipo: lista o color. Valor: uno por fila o varios separados por coma. '
                    . 'Color: #RRGGBB (obligatorio si Tipo es color). Activo: 1 o 0.'
                );
            },
        ];
    }
}
