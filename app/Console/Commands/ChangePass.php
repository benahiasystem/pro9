<?php
namespace App\Console\Commands;

// ########## INICIO CAMBIO POSTGRESQL 17
class ChangePass extends \Illuminate\Console\Command
{
    protected $signature = 'passwords';
    protected $description = 'Actualiza credenciales tenant sin exponer contraseñas';
    public function handle(): int
    {
        $connection=app(\App\Tenancy\Database\Connection::class);
        foreach (\App\Tenancy\Models\Website::cursor() as $website) {
            $config=$connection->generateConfigurationArray($website);
            app(\App\Tenancy\TenantProvisioning::class)->password($website->uuid,$config['password']);
            $this->info('Credencial actualizada: '.$website->uuid);
        }
        $connection->purge();return 0;
    }
}
// ######### FIN CAMBIO POSTGRESQL 17
