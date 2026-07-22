<?php

namespace Modules\Marketplace\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Marketplace\Models\ContactRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Registro de solicitudes de contacto (plan de seguridad, A4.1).
 *
 * Quién pidió el contacto de qué tienda, cuándo y con qué datos. Es el rastro
 * de la puerta de contacto; el export CSV existe para poder entregarlo tal
 * cual a la PNP/Fiscalía cuando una tienda denuncia.
 */
class ContactRequestController extends Controller
{
    public function index(Request $request)
    {
        if ($request->boolean('export')) {
            return $this->export($request);
        }

        $requests = $this->query($request)->paginate(30);

        $requests->getCollection()->transform(fn (ContactRequest $r) => [
            'id' => $r->id,
            'code' => $r->code(),
            'store' => $r->store?->name,
            'store_id' => $r->store_id,
            'type' => $r->type,
            'items' => $r->items,
            'buyer_name' => $r->buyer_name,
            'buyer_whatsapp' => $r->buyer_whatsapp,
            'visitor_id' => $r->visitor_id,
            'ip' => $r->ip,
            'created_at' => $r->created_at?->format('d/m/Y H:i'),
        ]);

        return response()->json($requests);
    }

    private function query(Request $request)
    {
        $query = ContactRequest::with('store')->orderByDesc('id');

        if ($storeId = $request->input('store_id')) {
            $query->where('store_id', $storeId);
        }

        if ($q = trim((string) $request->input('q'))) {
            $query->where(function ($sub) use ($q) {
                $sub->where('buyer_name', 'like', "%{$q}%")
                    ->orWhere('buyer_whatsapp', 'like', "{$q}%")
                    ->orWhere('ip', 'like', "{$q}%");
            });
        }

        return $query;
    }

    /**
     * CSV en streaming: el registro puede ser largo y no hace falta cargarlo
     * entero en memoria para descargarlo.
     */
    private function export(Request $request): StreamedResponse
    {
        $query = $this->query($request);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');

            // BOM para que Excel abra el UTF-8 sin romper tildes.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Código', 'Fecha', 'Tienda', 'Tipo', 'Comprador', 'WhatsApp', 'Cookie (visitor_id)', 'IP', 'Ítems']);

            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $r) {
                    fputcsv($out, [
                        $r->code(),
                        $r->created_at?->format('d/m/Y H:i:s'),
                        $r->store?->name,
                        $r->type,
                        $r->buyer_name,
                        $r->buyer_whatsapp,
                        $r->visitor_id,
                        $r->ip,
                        $r->items ? json_encode($r->items, JSON_UNESCAPED_UNICODE) : '',
                    ]);
                }
            });

            fclose($out);
        }, 'contactos-marketplace-' . now()->format('Ymd-His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
