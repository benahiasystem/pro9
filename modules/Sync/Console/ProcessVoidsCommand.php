<?php

namespace Modules\Sync\Console;

use App\Models\Tenant\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Modules\Sync\Models\SyncEvent;
use Modules\Sync\Services\VoidProcessor;

/** Retoma anulaciones locales pendientes creadas por versiones anteriores. */
class ProcessVoidsCommand extends Command
{
    protected $signature = 'sync:process-voids';

    protected $description = 'Registra localmente los eventos de anulación offline que continúan pendientes';

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

        // Conserva el autor administrativo requerido por los observers del tenant.
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
                // Se conserva pending ante un fallo transitorio de base de datos
                // o de las reglas locales y se reintenta en la siguiente corrida.
                $this->error("{$event->external_id}: " . $e->getMessage());
            }
        }

        return 0;
    }
}
