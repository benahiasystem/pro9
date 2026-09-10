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
     * Meses de consumo del administrador que se listan como maximo.
     */
    private const SYSTEM_USAGE_MONTHS = 12;

    /**
     * Devuelve el uso de API del administrador, el de los clientes (paginado)
     * y la cuota del reseller.
     */
    public function records(Request $request)
    {
        $perPage = max(1, min((int) $request->input('per_page', 10), 100));

        // Solo tenants: el consumo del admin / reseller va en su propio listado
        // porque no tiene cliente ni hostname al que asociarse.
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
                'system_usage' => $this->systemUsage(),
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

    /**
     * Consumo del administrador / reseller: las consultas hechas desde el panel
     * del sistema, que se guardan con client_id null.
     *
     * El mes en curso siempre aparece, aunque todavia no se haya consultado
     * nada; en ese caso no existe fila en la tabla y se devuelve en cero.
     *
     * @return array
     */
    private function systemUsage(): array
    {
        $currentMonth = now()->format('Y-m');

        $rows = DB::table('extra_services_client_usage_apidocs')
            ->whereNull('client_id')
            ->select('quantity', 'month')
            ->orderByDesc('month')
            ->limit(self::SYSTEM_USAGE_MONTHS)
            ->get()
            ->map(fn ($row) => [
                'quantity' => (int) $row->quantity,
                'month' => $row->month,
            ])
            ->all();

        if (! collect($rows)->contains(fn ($row) => $row['month'] === $currentMonth)) {
            array_unshift($rows, [
                'quantity' => 0,
                'month' => $currentMonth,
            ]);
        }

        return $rows;
    }
}
