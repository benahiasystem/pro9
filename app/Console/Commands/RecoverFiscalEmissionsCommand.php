<?php
namespace App\Console\Commands;

use App\Services\Fiscal\FiscalEmissionService;
use Hyn\Tenancy\Environment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
final class RecoverFiscalEmissionsCommand extends Command
{
    protected $signature = 'fiscal:recover';
    protected $description = 'Retoma emisiones fiscales pendientes del tenant sin recrear ventas';

    public function handle(): int
    {
        $tenant = app(Environment::class)->tenant();
        if (!$tenant) {
            $this->error('Ejecute fiscal:recover mediante tenancy:run para seleccionar el tenant.');
            return self::FAILURE;
        }
        $db = DB::connection('tenant');
        if (!$db->getSchemaBuilder()->hasTable('fiscal_number_reservations')) {
            $this->warn('Este tenant todavía no dispone del esquema de numeración fiscal.');
            return self::SUCCESS;
        }
        $result = (new FiscalEmissionService($db))->recoverPending();
        $this->line(json_encode(['tenant_id' => (int) $tenant->id] + $result, JSON_THROW_ON_ERROR));
        return $result['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
