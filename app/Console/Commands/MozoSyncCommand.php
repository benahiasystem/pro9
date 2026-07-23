<?php

namespace App\Console\Commands;

use App\Services\System\MozoConfigurationService;
use App\Services\System\MozoLogoService;
use Illuminate\Console\Command;

class MozoSyncCommand extends Command
{
    protected $signature = 'mozo:sync';

    protected $description = 'Reaplica el logo configurado de Mozo sobre los archivos del build.';

    public function handle(MozoConfigurationService $configService, MozoLogoService $logoService): int
    {
        $logoService->ensureHtaccess();

        $config = $configService->get();
        $useSystemLogo = (bool) ($config['useSystemLogo'] ?? true);

        if ($useSystemLogo) {
            $logoService->applySystemLogo();

            $this->info($logoService->hasSystemLogo()
                ? 'Logo del System reaplicado en el build del Mozo.'
                : 'El System no tiene logo; se dejó el logo por defecto de Mozo.');
        } else {
            $logoService->applyCustomLogo();

            $this->info($logoService->hasCustomLogo()
                ? 'Logo personalizado reaplicado en el build del Mozo.'
                : 'No hay logo personalizado guardado; no se aplicó ningún cambio.');
        }

        return self::SUCCESS;
    }
}
