<?php

namespace Modules\ExtraServices\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\ExtraServices\Services\ApidocsService;

class ClientUsageApidocsController extends Controller
{
    protected $apidocsService;

    public function __construct(ApidocsService $apidocsService)
    {
        $this->apidocsService = $apidocsService;
    }

    /**
     * Devuelve el listado de uso de API de los clientes (paginado) y la cuota del reseller.
     */
    public function records(Request $request)
    {
        $perPage = max(1, min((int) $request->input('per_page', 10), 100));

        $clients = DB::table('extra_services_client_usage_apidocs as usage')
            ->join('clients as c', 'usage.client_id', '=', 'c.id')
            ->leftJoin('hostnames as h', 'c.hostname_id', '=', 'h.id')
            ->select(
                'c.id as client_id',
                'c.name as client_name',
                'h.fqdn as hostname',
                'usage.quantity',
                'usage.month'
            )
            ->orderByDesc('usage.month')
            ->orderBy('c.name')
            ->paginate($perPage);

        $quotaResponse = $this->apidocsService->getQuota();
        $quotaPayload = ($quotaResponse['success'] ?? false) ? ($quotaResponse['data'] ?? []) : [];

        return response()->json([
            'success' => true,
            'data' => [
                'clients' => $clients->items(),
                'pagination' => [
                    'current_page' => $clients->currentPage(),
                    'per_page' => $clients->perPage(),
                    'total' => $clients->total(),
                    'last_page' => $clients->lastPage(),
                ],
                'quota' => $quotaPayload['quota'] ?? null,
                'current_usage' => $quotaPayload['current_usage'] ?? null,
            ],
        ]);
    }
}
