<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class MakeTenancyMigrationCommand extends Command
{
    /**
     * El nombre y la firma del comando en la terminal.
     *
     * @var string
     */
    protected $signature = 'make:tenancy:migration {name : El nombre de la migración}';

    /**
     * La descripción del comando.
     *
     * @var string
     */
    protected $description = 'Crea una nueva migración directamente dentro de la carpeta tenant';

    /**
     * Ejecuta el comando.
     */
    public function handle()
    {
        $name = $this->argument('name');

        // Asegura que el nombre comience con "tenant_" automáticamente
        if (!Str::startsWith($name, 'tenant_')) {
            $name = 'tenant_' . $name;
        }

        // Ejecuta el comando nativo de Laravel de manera silenciosa
        Artisan::call('make:migration', [
            'name' => $name,
            '--path' => 'database/migrations/tenant'
        ]);

        // Busca el último archivo creado en la carpeta tenant para obtener la ruta exacta
        $files = glob(database_path('migrations/tenant/*.php'));
        $actualFile = end($files);

        // Imprime el formato de éxito nativo de Laravel
        if ($actualFile) {
            $this->components->info(sprintf('Migration [%s] created successfully.', $actualFile));
        } else {
            $this->components->error('No se pudo determinar el archivo creado.');
        }
    }
}