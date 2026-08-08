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
            // Cada serie con su rango emitido: "FV01 de 1 a 3".
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
            'message' => "Máquina {$machine->name} revocada: su token ya no autoriza y la emisión online del establecimiento queda liberada si no hay otras máquinas activas",
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
