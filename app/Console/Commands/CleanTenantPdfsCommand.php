<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CleanTenantPdfsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenants:clean-pdfs
                            {--dry-run : Solo muestra qué se eliminaría, sin borrar}
                            {--tenant= : Limpiar solo un tenant (nombre de carpeta / uuid)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Elimina los archivos .pdf de storage/app/tenancy/tenants/*/pdf/';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $tenantsDir = storage_path('app/tenancy/tenants');
        $dryRun = (bool) $this->option('dry-run');
        $onlyTenant = $this->option('tenant');

        if (! File::isDirectory($tenantsDir)) {
            $this->error("No existe el directorio de tenants: {$tenantsDir}");

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('[DRY-RUN] No se eliminará ningún archivo.');
            $this->newLine();
        }

        $tenants = $onlyTenant
            ? [rtrim($tenantsDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $onlyTenant]
            : File::directories($tenantsDir);

        $totalTenants = 0;
        $tenantsWithPdfs = 0;
        $totalPdfs = 0;

        foreach ($tenants as $tenantPath) {
            if (! File::isDirectory($tenantPath)) {
                $this->warn("Tenant no encontrado: {$onlyTenant}");

                return self::FAILURE;
            }

            $tenantName = basename($tenantPath);
            $pdfDir = $tenantPath . DIRECTORY_SEPARATOR . 'pdf';
            $totalTenants++;

            if (! File::isDirectory($pdfDir)) {
                $this->line("[{$tenantName}] Sin carpeta pdf — omitido");
                continue;
            }

            $pdfs = collect(File::files($pdfDir))
                ->filter(function ($file) {
                    return strtolower($file->getExtension()) === 'pdf';
                })
                ->values();

            $count = $pdfs->count();

            if ($count === 0) {
                $this->line("[{$tenantName}] 0 PDFs");
                continue;
            }

            $tenantsWithPdfs++;
            $totalPdfs += $count;

            if ($dryRun) {
                $this->info("[{$tenantName}] Se eliminarían {$count} PDF(s)");
                continue;
            }

            foreach ($pdfs as $pdf) {
                File::delete($pdf->getPathname());
            }

            $this->info("[{$tenantName}] Eliminados {$count} PDF(s)");
        }

        $this->newLine();
        $this->line("Tenants revisados: {$totalTenants}");
        $this->line("Tenants con PDFs:  {$tenantsWithPdfs}");
        $this->line($dryRun
            ? "PDFs a eliminar:   {$totalPdfs}"
            : "PDFs eliminados:   {$totalPdfs}"
        );

        return self::SUCCESS;
    }
}
