<?php

namespace App\Services\Fiscal;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

// ######## INICIO ENVÍO AUTOMÁTICO HKA ########
final class HkaAutoSendSettingUpgrade
{
    public function upgrade(Connection $connection, bool $dryRun = false): bool
    {
        $schema = $connection->getSchemaBuilder();
        if (!$schema->hasTable('configurations')) {
            throw new \RuntimeException('La empresa no tiene una tabla de configuración.');
        }
        if ($schema->hasColumn('configurations', 'auto_send_hka')) return false;
        if (!$dryRun) {
            $schema->table('configurations', function (Blueprint $table) {
                $table->boolean('auto_send_hka')->default(true)->after('options_pos')
                    ->comment('Envío automático de comprobantes digitales a HKA');
            });
        }
        return true;
    }
}
// ######## FIN ENVÍO AUTOMÁTICO HKA ########
