<?php

namespace App\Http\Controllers\Tenant\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Purchase;

class PurchaseController extends Controller
{
    /**
     * Devuelve una compra por su id.
     *
     * whereTypeUser() evita que un vendedor pueda leer registros de otro usuario
     * pasando ids ajenos; para los demas perfiles no restringe nada.
     */
    public function record($id)
    {
        $record = Purchase::whereTypeUser()->find($id);

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró la compra solicitada.',
            ], 404);
        }

        // Mismo envoltorio "data" que document/find y sale-note/find.
        return response()->json([
            'data' => $record->getApiResourceFind(),
        ]);
    }
}
