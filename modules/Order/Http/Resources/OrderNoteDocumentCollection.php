<?php

namespace Modules\Order\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Models\Tenant\Series;
use App\Services\SeriesResolver;
use App\Models\Tenant\Catalogs\DocumentType;

class OrderNoteDocumentCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {

        $all_series = app(SeriesResolver::class)->applyContext(Series::where('establishment_id', auth()->user()->establishment_id))->get();
        // ########## INICIO CAMBIO FACTURAS Y NOTAS DE VENTA EN PEDIDOS
        $all_document_types_invoice = DocumentType::whereIn('id', ['01', '80'])->get();
        // ######### FIN CAMBIO FACTURAS Y NOTAS DE VENTA EN PEDIDOS

        return $this->collection->transform(function($row, $key) use($all_series, $all_document_types_invoice){

            // ########## INICIO CAMBIO FACTURAS Y NOTAS DE VENTA EN PEDIDOS
            $document_types = $all_document_types_invoice->values();
            $series = $all_series->filter(function($row){ return $row->document_type_id === '01'; })->values();
            // ######### FIN CAMBIO FACTURAS Y NOTAS DE VENTA EN PEDIDOS

            return [
                'id' => null,
                'index_id' => $row->id,
                'establishment_id' => $row->establishment_id,
                'date_of_issue' => $row->date_of_issue->format('Y-m-d'),
                'identifier' => $row->identifier,
                'customer_name' => $row->customer->name,
                'customer_number' => $row->customer->number,
                'total' => number_format($row->total,2),
                'selected' => false,
                // ########## INICIO CAMBIO FACTURAS Y NOTAS DE VENTA EN PEDIDOS
                'document_type_id' => '01',
                // ######### FIN CAMBIO FACTURAS Y NOTAS DE VENTA EN PEDIDOS
                'series_id' => count($series) > 0 ? $series->first()->id : null,
                'series' => $series,
                'document_types' => $document_types,
                'order_note' => $row,
            ];
        });
    }

}
