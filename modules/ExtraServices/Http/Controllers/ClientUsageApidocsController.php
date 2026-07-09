<?php

namespace Modules\ExtraServices\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\ExtraServices\Models\ClientUsageApidocs;

class ClientUsageApidocsController extends Controller
{
    /**
     * Devuelve el listado de uso de API de los clientes.
     */
    public function records()
    {
        // Traemos el uso agrupado por cliente
        $data = DB::table('extra_services_client_usage_apidocs as usage')
            ->join('clients as c', 'usage.client_id', '=', 'c.id')
            ->leftJoin('hostnames as h', 'c.hostname_id', '=', 'h.id')
            ->select(
                'c.id as client_id',
                'c.name as client_name',
                'h.fqdn as hostname',
                'usage.quantity',
                'usage.month'
            )
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }
}