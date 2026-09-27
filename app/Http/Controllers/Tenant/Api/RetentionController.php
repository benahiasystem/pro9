<?php
namespace App\Http\Controllers\Tenant\Api;

use App\Http\Controllers\Controller;


class RetentionController extends Controller
{
    public function store()
    {
        return response()->json([
            'success' => false,
            'code' => 'RETENTION_CREATION_DISABLED',
            'message' => 'La creación de retenciones está deshabilitada hasta definir el nuevo cálculo de ISLR.',
        ], 409);
    }
}
