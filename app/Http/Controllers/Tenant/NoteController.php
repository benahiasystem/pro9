<?php
namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Document;
use App\Models\Tenant\Configuration;
use App\Models\Tenant\Item;

class NoteController extends Controller
{
    /**
     * Código interno del ítem de servicio usado en las notas de débito
     * por penalidad (motivo 13), creado por migración.
     */
    public const PENALTY_ITEM_INTERNAL_ID = 'PENALIDAD';

    public function create($document_id)
    {
        $document_affected = Document::find($document_id);
        $configuration = Configuration::first();

        return view('tenant.documents.note', compact('document_affected', 'configuration'));
    }

    public function record($document_id)
    {
        $record = Document::find($document_id);

        return $record;
    }

    public function hasDocuments($document_id)
    {

        $record = Document::wherehas('affected_documents', function ($q) {
            $q->whereHas('document', function ($q) {
                $q->where('state_type_id', '05');
            });
        })->find($document_id);

        if($record){

            return [
                'success' => true,
                'data' => $record->affected_documents->transform(function($row, $key) {
                            return [
                                'id' => $row->id,
                                'document_id' => $row->document_id,
                                'document_type_description' => $row->document->document_type->description,
                                'description' => $row->document->number_full,
                            ];
                        })
            ];
            
        }

        return [
            'success' => false,
            'data' => []
        ];

    }

    /**
     * Devuelve el ítem de penalidad para preseleccionarlo en la nota de débito
     * con motivo 13, donde solo se debe ingresar el monto.
     */
    public function penaltyItem()
    {
        $item = Item::where('internal_id', self::PENALTY_ITEM_INTERNAL_ID)
                    ->where('item_type_id', '02')
                    ->whereIsActive()
                    ->first();

        if (!$item) {
            return [
                'success' => false,
                'message' => 'No se encontró el servicio "Penalidad". Regístrelo como servicio inafecto al IGV para emitir notas de débito por penalidad.',
            ];
        }

        return [
            'success' => true,
            'data' => [
                'id' => $item->id,
                'description' => $item->description,
            ],
        ];
    }


}
