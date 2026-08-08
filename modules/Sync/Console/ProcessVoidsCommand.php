<?php

namespace Modules\Sync\Console;

use App\Models\Tenant\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Modules\Sync\Models\SyncEvent;
use Modules\Sync\Services\VoidProcessor;

/**
 * Retoma los eventos void pendientes (su venta aún no estaba aceptada por
 * SUNAT cuando llegó el lote) y genera la baja/resumen de anulación en cuanto
 * el documento alcanza el estado aceptado. Pensado para cron del tenant, junto
 * a los envíos existentes (online:send-all, summary:send).
 */
class ProcessVoidsCommand extends Command
{
    protected $signature = 'sync:process-voids';

    protected $description = 'Genera las anulaciones (baja/resumen) de los eventos void pendientes cuyo documento ya fue aceptado';

    public function handle()
    {
        $pending = SyncEvent::where('type', 'void')
            ->where('status', 'pending')
            ->orderBy('seq')
            ->get();

        if ($pending->isEmpty()) {
            $this->info('Sin anulaciones pendientes');

            return 0;
        }

        // Los Inputs firman con el usuario autenticado (igual que el canal web).
        Auth::shouldUse('api');
        Auth::guard('api')->setUser(User::where('type', 'admin')->firstOrFail());

        $processor = new VoidProcessor();

        foreach ($pending as $event) {
            try {
                $outcome = $processor->attempt($event->machine, $event->payload);

                $event->status = $outcome['status'];
                $event->message = $outcome['message'];
                if (!empty($outcome['document_id'])) {
                    $event->document_id = $outcome['document_id'];
                }
                $event->save();

                $this->info("{$event->external_id}: {$outcome['status']} — {$outcome['message']}");
            } catch (\Throwable $e) {
                // Se conserva pending: un fallo transitorio (SUNAT caída, etc.)
                // se reintenta en la siguiente corrida.
                $this->error("{$event->external_id}: " . $e->getMessage());
            }
        }

        return 0;
    }
}
