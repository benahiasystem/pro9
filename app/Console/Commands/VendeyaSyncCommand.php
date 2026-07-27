<?php

namespace App\Console\Commands;

use App\Services\System\VendeyaConfigurationService;
use App\Services\System\VendeyaLogoService;
use Illuminate\Console\Command;

class VendeyaSyncCommand extends Command
{
    protected $signature = 'vendeya:sync';

    protected $description = 'Reaplica el logo configurado de Vendeya sobre los archivos del build.';

    public function handle(VendeyaConfigurationService $configService, VendeyaLogoService $logoService): int
    {
        $logoService->ensureHtaccess();
        $config = $configService->get();

        if ((bool) ($config['useSystemLogo'] ?? true)) {
            $logoService->applySystemLogo();
            $this->info($logoService->hasSystemLogo()
                ? 'Logo del System reaplicado en Vendeya.'
                : 'El System no tiene logo; se dejó el logo por defecto de Vendeya.');
        } else {
            $logoService->applyCustomLogo();
            $this->info($logoService->hasCustomLogo()
                ? 'Logo personalizado reaplicado en Vendeya.'
                : 'No hay logo personalizado guardado; no se aplicó ningún cambio.');
        }

        return self::SUCCESS;
    }
}
