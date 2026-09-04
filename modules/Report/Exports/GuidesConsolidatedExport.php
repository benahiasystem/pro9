<?php

namespace Modules\Report\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnWidths;

class GuidesConsolidatedExport implements FromView, WithColumnWidths
{
    use Exportable;

    public function records($records) {
        $this->records = $records;

        return $this;
    }

    public function company($company) {
        $this->company = $company;

        return $this;
    }

    public function establishment($establishment) {
        $this->establishment = $establishment;

        return $this;
    }

    public function params($params) {
        $this->params = $params;

        return $this;
    }

    public function view(): View {
        return view('report::guides.report_excel', [
            'records'=> $this->records,
            'company' => $this->company,
            'establishment'=>$this->establishment,
            'params'=>$this->params
        ]);
    }

    /**
     * Anchos fijos: evita ShouldAutoSize (muy pesado en +500 filas)
     * pero mantiene el Excel legible.
     */
    public function columnWidths(): array
    {
        return [
            'A' => 6,   // #
            'B' => 14,  // Fecha Emisión
            'C' => 30,  // Cliente
            'D' => 16,  // Vendedor
            'E' => 14,  // Número
            'F' => 14,  // Estado
            'G' => 14,  // Fecha Envío
            'H' => 36,  // Producto
            'I' => 12,  // Cantidad
            'J' => 22,  // Motivo de Traslado
            'K' => 30,  // Descripción motivo
            'L' => 18,  // Transportista tipo doc
            'M' => 14,  // # Documento
            'N' => 26,  // Nombre transportista
            'O' => 12,  // # Pedido
            'P' => 14,  // O.Pedido
        ];
    }

}
