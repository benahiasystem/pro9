<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 *
 * Collection ligero para el modal de generación de resúmenes.
 *
 * A diferencia de DocumentCollection (resource genérico del listado que
 * dispara múltiples consultas N+1 por fila), solo devuelve los campos que
 * consume el modal (resources/js/views/tenant/summaries/form.vue) y el id
 * necesario para el guardado. Al guardar, SummaryValidation::setDocuments
 * reduce cada fila a ['document_id' => id], por lo que no se requiere ningún
 * otro dato del payload.
 *
 */
class SummaryDocumentCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function toArray($request)
    {
        return $this->collection->transform(function ($row) {
            return [
                'id' => $row->id,
                'number' => $row->number_full,
                'document_type_description' => optional($row->document_type)->description,
                'affected_document' => null,
                'currency_type_id' => $row->currency_type_id,
                'total_exportation' => $row->total_exportation,
                'total_free' => $row->total_free,
                'total_unaffected' => $row->total_unaffected,
                'total_exonerated' => $row->total_exonerated,
                'total_charge' => $row->total_charge,
                'total_taxed' => $row->total_taxed,
                'total_igv' => $row->total_igv,
                'total' => $row->total,
            ];
        });
    }
}
