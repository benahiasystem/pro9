<?php

namespace Modules\MultiUser\Console\Commands;

use App\Models\System\Client;
use App\Models\Tenant\User;
use Hyn\Tenancy\Environment;
use Illuminate\Console\Command;
use Modules\MultiUser\Services\MultiUserPermissionSync;

class SyncMultiUserPermissionsCommand extends Command
{
    protected $signature = 'multi-user:sync-permissions {--client= : ID del cliente destino (opcional)}';

    protected $description = 'Sincroniza módulos de usuarios espejo multi-usuario con el admin (user id=1) de cada tenant destino';

    public function handle(): int
    {
        $clientId = $this->option('client');
        $clients = $clientId
            ? Client::where('id', $clientId)->get()
            : Client::all();

        $tenancy = app(Environment::class);
        $total = 0;

        foreach ($clients as $client) {
            if (!$client->hostname?->website) {
                continue;
            }

            $tenancy->tenant($client->hostname->website);

            $shadowUsers = User::whereFilterWithOutRelations()
                ->where('is_multi_user', true)
                ->get();

            foreach ($shadowUsers as $user) {
                MultiUserPermissionSync::syncFromTenantAdmin($user);
                $total++;
                $this->line("  {$client->name} → {$user->email}");
            }
        }

        $this->info("Permisos sincronizados para {$total} usuario(s) espejo.");

        return self::SUCCESS;
    }
}
