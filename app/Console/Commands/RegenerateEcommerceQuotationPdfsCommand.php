<?php

namespace App\Console\Commands;

use App\Http\Controllers\Tenant\QuotationController;
use App\Models\Tenant\Quotation;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;
use Throwable;

class RegenerateEcommerceQuotationPdfsCommand extends Command
{
    protected $signature = 'tenant:regenerate-ecommerce-pdfs {--tenant=} {--dry}';

    protected $description = 'Regenera los PDF de cotizaciones de tienda virtual con el correlativo estándar COT-{id}';

    public function handle(): int
    {
        $tenants = explode(',', (string) $this->option('tenant'));
        $dry = (bool) $this->option('dry');

        $websites = (count($tenants) === 1 && $tenants[0] === '')
            ? Website::all()
            : Website::whereIn('id', $tenants)->get();

        if ($websites->isEmpty()) {
            $this->error('No se encontraron tenants para procesar.');

            return 1;
        }

        foreach ($websites as $website) {
            app(Environment::class)->tenant($website);
            $this->line("== Tenant #{$website->id} ({$website->uuid}) ==");
            $this->regenerateForCurrentTenant($dry);
        }

        return 0;
    }

    private function regenerateForCurrentTenant(bool $dry): void
    {
        $query = Quotation::query()
            ->whereSourceEcommerce()
            ->orderBy('id');

        $total = $query->count();
        if ($total === 0) {
            $this->warn('  Sin cotizaciones ecommerce.');

            return;
        }

        $this->info("  Procesando {$total} cotización(es)...");
        $ok = 0;
        $fail = 0;

        $query->chunkById(50, function ($rows) use ($dry, &$ok, &$fail) {
            foreach ($rows as $quotation) {
                /** @var Quotation $quotation */
                $filename = join('-', [
                    $quotation->prefix ?: Quotation::SERIES_STANDARD,
                    $quotation->id,
                    optional($quotation->date_of_issue)->format('Ymd') ?: date('Ymd'),
                ]);

                if ($dry) {
                    $this->line("  [dry] #{$quotation->id} → {$filename} ({$quotation->identifier})");
                    $ok++;
                    continue;
                }

                try {
                    $quotation->series = '';
                    $quotation->number = 0;
                    $quotation->number_year = null;
                    $quotation->filename = $filename;
                    $quotation->save();

                    app(QuotationController::class)->createPdf($quotation, 'a4', $quotation->filename);
                    $ok++;
                } catch (Throwable $e) {
                    $fail++;
                    $this->error("  Error #{$quotation->id}: ".$e->getMessage());
                }
            }
        });

        $this->info("  OK: {$ok} | Fallidas: {$fail}");
    }
}
