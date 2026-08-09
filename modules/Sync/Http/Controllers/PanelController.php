<?php

namespace Modules\Sync\Http\Controllers;

use App\Models\Tenant\Document;
use App\Models\Tenant\Series;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Sync\Models\OfflineMachine;
use Modules\Sync\Models\SyncEvent;
use Modules\Sync\Services\BatchProcessor;

/**
 * Panel de la Conexión Offline (VendeYa) en el facturador: máquinas enroladas
 * (revocar, liberar series) y bandeja de eventos (reintentar, descartar).
 */
class PanelController extends Controller
{
    public function index()
    {
        return view('sync::panel');
    }

    public function machines()
    {
        $machines = OfflineMachine::orderBy('id', 'desc')->get()->map(function ($m) {
            // Activa: sus series vigentes con rango emitido ("FV01 de 1 a 3").
            // Revocada: el HISTÓRICO real desde sus eventos (las series ya se
            // liberaron o liberarán, pero lo emitido por esta máquina queda).
            if ($m->status === 'active') {
                $series = Series::where('series_device_group_id', $m->series_device_group_id)
                    ->get()
                    ->map(function ($s) {
                        $base = Document::where('document_type_id', $s->document_type_id)
                            ->where('series', $s->number);
                        $first = (clone $base)->min('number');
                        $last = (clone $base)->max('number');

                        return $first
                            ? "{$s->number} de {$first} a {$last}"
                            : "{$s->number} sin emisiones";
                    })
                    ->values();
            } else {
                $series = SyncEvent::where('machine_id', $m->id)
                    ->whereIn('type', ['sale', 'sale_note'])
                    ->selectRaw(
                        "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.serie_documento')), JSON_UNQUOTE(JSON_EXTRACT(payload, '$.series'))) AS serie,
                         MIN(CAST(COALESCE(JSON_EXTRACT(payload, '$.numero_documento'), JSON_EXTRACT(payload, '$.number')) AS UNSIGNED)) AS first_n,
                         MAX(CAST(COALESCE(JSON_EXTRACT(payload, '$.numero_documento'), JSON_EXTRACT(payload, '$.number')) AS UNSIGNED)) AS last_n"
                    )
                    ->groupBy('serie')
                    ->orderBy('serie')
                    ->get()
                    ->filter(fn ($r) => $r->serie)
                    ->map(fn ($r) => "{$r->serie} de {$r->first_n} a {$r->last_n}")
                    ->values();
            }

            $counters = SyncEvent::where('machine_id', $m->id)
                ->selectRaw('status, COUNT(*) as n')
                ->groupBy('status')
                ->pluck('n', 'status');

            return [
                'id' => $m->id,
                'uuid' => $m->uuid,
                'name' => $m->name,
                'establishment_id' => $m->establishment_id,
                'status' => $m->status,
                'series' => $series,
                // Aún tiene series amarradas a su grupo (habilita "Liberar")
                'has_group' => Series::where('series_device_group_id', $m->series_device_group_id)->exists(),
                'created_at' => optional($m->created_at)->format('Y-m-d H:i'),
                'accepted' => (int) ($counters['accepted'] ?? 0),
                'pending' => (int) ($counters['pending'] ?? 0),
                'error' => (int) ($counters['error'] ?? 0),
            ];
        });

        return response()->json(['success' => true, 'data' => $machines]);
    }

    public function revoke($id)
    {
        $machine = OfflineMachine::findOrFail($id);
        $machine->status = 'revoked';
        $machine->save();

        return response()->json([
            'success' => true,
            'message' => "Máquina {$machine->name} revocada: su acceso quedó deshabilitado. Lo que tuviera sin sincronizar podrá enviarse al reconectarla.",
        ]);
    }

    /**
     * Devuelve las series dedicadas de una máquina revocada al pool libre
     * (quedan seleccionables para un nuevo enrolamiento). El correlativo lo
     * seguirá dictando el último documento registrado, como siempre.
     */
    public function releaseSeries($id)
    {
        $machine = OfflineMachine::findOrFail($id);

        if ($machine->status !== 'revoked') {
            return response()->json([
                'success' => false,
                'message' => 'Primero revoca la máquina: no se liberan series de una conexión activa',
            ], 422);
        }

        $released = Series::where('series_device_group_id', $machine->series_device_group_id)
            ->update(['series_device_group_id' => null]);

        return response()->json([
            'success' => true,
            'message' => "{$released} serie(s) liberadas",
        ]);
    }

    public function events(Request $request)
    {
        $query = SyncEvent::with('machine:id,name')->orderBy('id', 'desc');

        if ($request->filled('machine_id')) {
            $query->where('machine_id', $request->machine_id);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $page = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data' => collect($page->items())->map(function ($e) {
                $detail = $e->type;
                if (in_array($e->type, ['sale', 'void'])) {
                    $p = $e->payload;
                    $detail = ($p['serie_documento'] ?? '') . '-' . ($p['numero_documento'] ?? '');
                } elseif ($e->type === 'sale_note') {
                    // Visualmente es un comprobante de venta más.
                    $p = $e->payload;
                    $detail = ($p['series'] ?? 'NV') . '-' . ($p['number'] ?? '');
                }

                return [
                    'id' => $e->id,
                    'machine' => optional($e->machine)->name,
                    'seq' => $e->seq,
                    'type' => $e->type,
                    'detail' => $detail,
                    'occurred_at' => optional($e->occurred_at)->format('Y-m-d H:i:s'),
                    'status' => $e->status,
                    'message' => $e->message,
                    'document_id' => $e->document_id,
                    // reintentable: error con datos suficientes, o void en espera
                    'can_retry' => ($e->status === 'error' && ($e->type !== 'sale' || $e->xml_unsigned))
                        || ($e->status === 'pending' && $e->type === 'void'),
                    'can_discard' => $e->status === 'error',
                ];
            }),
            'meta' => [
                'total' => $page->total(),
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
            ],
        ]);
    }

    /**
     * KPIs de la bandeja: los pendientes dependen de las tareas programadas
     * (anulaciones esperando aceptación, boletas de resumen) o del reintento
     * manual — este número es el "trabajo por continuar".
     */
    public function eventStats()
    {
        $byStatus = SyncEvent::selectRaw('status, COUNT(*) AS n')
            ->groupBy('status')
            ->pluck('n', 'status');

        return response()->json([
            'success' => true,
            'data' => [
                'pending' => (int) ($byStatus['pending'] ?? 0),
                'error' => (int) ($byStatus['error'] ?? 0),
                'accepted' => (int) ($byStatus['accepted'] ?? 0),
                'discarded' => (int) ($byStatus['discarded'] ?? 0),
            ],
        ]);
    }

    public function retryEvent($id)
    {
        $event = SyncEvent::findOrFail($id);

        if (!in_array($event->status, ['error', 'pending'])) {
            return response()->json(['success' => false, 'message' => 'Este evento no requiere reintento'], 422);
        }

        $event = (new BatchProcessor())->retry($event);

        return response()->json([
            'success' => $event->status !== 'error',
            'message' => $event->message ?: "Evento {$event->external_id}: {$event->status}",
        ]);
    }

    /** Descarta un error: queda registrado (auditoría) pero sale de la bandeja. */
    public function discardEvent($id)
    {
        $event = SyncEvent::findOrFail($id);

        if ($event->status !== 'error') {
            return response()->json(['success' => false, 'message' => 'Solo se descartan eventos con error'], 422);
        }

        $event->status = 'discarded';
        $event->save();

        return response()->json(['success' => true, 'message' => 'Evento descartado']);
    }
}
