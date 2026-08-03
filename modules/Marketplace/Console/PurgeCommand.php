<?php

namespace Modules\Marketplace\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Marketplace\Models\ContactRequest;
use Modules\Marketplace\Services\Settings;

/**
 * Minimización de retención (plan de seguridad, A4.3 / Ley 29733).
 *
 * Corre a diario (programado en el service provider):
 *  - Borra las solicitudes de contacto más antiguas que contact_retention_days.
 *  - Anonimiza la IP de las recomendaciones con la misma antigüedad (la
 *    recomendación se conserva — es el ranking —, el rastro personal no).
 *
 * Una base que no guarda datos viejos no puede filtrarlos: en este contexto,
 * una filtración del registro de contactos sería entregar una lista de
 * objetivos.
 */
class PurgeCommand extends Command
{
    protected $signature = 'marketplace:purge';

    protected $description = 'Purga solicitudes de contacto y rastros de IP más antiguos que contact_retention_days';

    public function handle(): int
    {
        $days = max(1, (int) Settings::get('contact_retention_days', 365));
        $cutoff = now()->subDays($days);

        $contacts = ContactRequest::where('created_at', '<', $cutoff)->delete();

        $ips = DB::connection('system')->table('marketplace_recommendations')
            ->where('created_at', '<', $cutoff)
            ->whereNotNull('ip')
            ->update(['ip' => null]);

        $this->info("Purga completada (> {$days} días): {$contacts} solicitudes de contacto eliminadas, {$ips} IPs de recomendaciones anonimizadas.");

        return self::SUCCESS;
    }
}
