<?php
namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Document;
use App\Models\Tenant\Configuration;

class NoteController extends Controller
{
    public function create($document_id)
    {
        $document_affected = $this->authorizedDocuments()->findOrFail($document_id);
        $configuration = Configuration::first();

        return view('tenant.documents.note', compact('document_affected', 'configuration'));
    }

    public function record($document_id)
    {
        $record = $this->authorizedDocuments()->findOrFail($document_id);

        return $record;
    }

    public function hasDocuments($document_id)
    {

        $record = $this->authorizedDocuments()->wherehas('affected_documents', function ($q) {
            $q->whereHas('document', function ($q) {
                $q->whereIn('state_type_id', ['01', '05']);
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

    // ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
    private function authorizedDocuments()
    {
        $user = auth()->user();
        abort_unless($user instanceof \App\Models\Tenant\User && in_array($user->type, ['admin', 'seller'], true), 403);
        $query = Document::query()->where('establishment_id', $user->establishment_id);
        if ($user->type !== 'admin') {
            $query->where(function ($scope) use ($user) {
                $scope->where('user_id', $user->id)->orWhere('seller_id', $user->id);
            });
        }
        return $query;
    }
    // ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
}
