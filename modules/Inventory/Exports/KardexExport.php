<?php

namespace Modules\Inventory\Exports;

use App\CoreFacturalo\Helpers\CompanyDocumentDisplay;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomChunkSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class KardexExport implements FromQuery, WithMapping, WithHeadings, WithEvents, WithCustomChunkSize, ShouldAutoSize
{
    use Exportable;

    protected $query;
    protected $balance = 0;   // Saldo corrido: acumula fila a fila entre chunks.
    protected $index = 0;     // Contador de fila (la columna "#").
    protected $item_id;
    protected $models;
    protected $company;
    protected $establishment;
    protected $item;

    public function query()
    {
        return $this->query;
    }

    public function setQuery($query)
    {
        $this->query = $query;

        return $this;
    }

    public function balance($balance)
    {
        $this->balance = $balance;

        return $this;
    }

    public function item_id($item_id)
    {
        $this->item_id = $item_id;

        return $this;
    }

    public function models($models)
    {
        $this->models = $models;

        return $this;
    }

    public function company($company)
    {
        $this->company = $company;

        return $this;
    }

    public function establishment($establishment)
    {
        $this->establishment = $establishment;

        return $this;
    }

    public function item($item)
    {
        $this->item = $item;

        return $this;
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    /**
     * Fila de títulos de columnas. Las columnas "Producto" y "Saldo" son
     * condicionales según haya o no item_id, igual que en el blade original.
     */
    public function headings(): array
    {
        $headings = ['#'];

        if (!$this->item_id) {
            $headings[] = 'Producto';
        }

        $headings = array_merge($headings, [
            'Fecha y hora transacción',
            'Tipo transacción',
            'Número',
            'NV. Asociada',
            'Pedido',
            'CPE. Asociado',
            'Fecha emisión',
            'Entrada',
            'Salida',
        ]);

        if ($this->item_id) {
            $headings[] = 'Saldo';
        }

        return $headings;
    }

    /**
     * Mapea cada InventoryKardex a una fila. getKardexReportCollection recibe
     * $this->balance por referencia, por lo que el saldo se acumula en orden.
     *
     * @param \Modules\Inventory\Models\InventoryKardex $value
     */
    public function map($value): array
    {
        $k = $value->getKardexReportCollection($this->balance);

        $row = [++$this->index];

        if (!$this->item_id) {
            $row[] = $k['item_name'];
        }

        $row = array_merge($row, [
            $k['date_time'],
            $k['type_transaction'],
            $k['number'],
            $k['sale_note_asoc'],
            $k['order_note_asoc'],
            $k['doc_asoc'],
            $k['date_of_issue'],
            $k['input'],
            $k['output'],
        ]);

        if ($this->item_id) {
            $row[] = number_format($k['balance'], 4);
        }

        return $row;
    }

    /**
     * Inserta el bloque de encabezado (empresa, RUC, producto, precios) por
     * encima de la tabla. WithHeadings solo genera la fila de títulos, así que
     * este bloque se escribe a mano desplazando las filas hacia abajo.
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $producto = $this->item->internal_id
                    ? $this->item->internal_id . ' - ' . $this->item->description
                    : $this->item->description;

                $precios = collect($this->item->warehousePrices ?? [])
                    ->map(fn ($w) => $w->getWarehouseDescription() . ': ' . $w->getPrice())
                    ->implode(' | ');

                $establecimiento = $this->establishment->address
                    . ' - ' . optional($this->establishment->department)->description
                    . ' - ' . optional($this->establishment->district)->description;

                $lines = [
                    ['Reporte Kardex'],
                    ['Empresa: ' . CompanyDocumentDisplay::commercialLine($this->company), 'Fecha: ' . date('d/m/Y')],
                    ['Ruc: ' . $this->company->number, 'Establecimiento: ' . $establecimiento],
                    ['Producto: ' . $producto, $precios ? 'Precios por almacenes: ' . $precios : ''],
                    [],
                ];

                $sheet->insertNewRowBefore(1, count($lines));

                foreach ($lines as $rowIndex => $cols) {
                    foreach ($cols as $colIndex => $val) {
                        if ($val === '') {
                            continue;
                        }
                        $cell = Coordinate::stringFromColumnIndex($colIndex + 1) . ($rowIndex + 1);
                        $sheet->setCellValue($cell, $val);
                    }
                }
            },
        ];
    }
}
