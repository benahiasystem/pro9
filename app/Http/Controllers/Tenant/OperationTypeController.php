<?php
namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Catalogs\OperationType;

class OperationTypeController extends Controller
{
    public function records()
    {
        return OperationType::orderBy('id')->get()->transform(function ($row) {
            return [
                'id' => $row->id,
                'description' => $row->description,
                'incoterm' => $row->incoterm,
                'exportation' => (bool) $row->exportation,
                'active' => (bool) $row->active,
            ];
        });
    }

    public function changeActive($id, $active)
    {
        // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
        if (in_array($id, OperationType::INACTIVE_INCOTERM_IDS, true) && (bool) $active) {
            return response()->json([
                'success' => false,
                'code' => 'EXPORT_OPERATION_DISABLED',
                'message' => 'La exportación por INCOTERM estará disponible al habilitar su flujo fiscal.',
            ], 409);
        }
        // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES

        $record = OperationType::findOrFail($id);
        $record->active = (bool) $active;
        $record->save();

        return [
            'success' => true,
            'message' => 'Tipo de operacion actualizado correctamente',
        ];
    }
}
