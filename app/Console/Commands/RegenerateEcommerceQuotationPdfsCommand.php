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
    protected $signature = 'quotations:regenerate-ecommerce-pdfs {--tenant=} {--dry}';

    protected $description = 'Regenera los PDF de cotizaciones de tienda virtual con el correlativo COT-TV';

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
            ->where('number', '>', 0)
            ->orderBy('id');

        $total = $query->count();
        if ($total === 0) {
            $this->warn('  Sin cotizaciones ecommerce numeradas.');

            return;
        }

        $this->info("  Procesando {$total} cotización(es)...");
        $ok = 0;
        $fail = 0;

        $query->chunkById(50, function ($rows) use ($dry, &$ok, &$fail) {
            foreach ($rows as $quotation) {
                /** @var Quotation $quotation */
                $year = $quotation->date_of_issue
                    ? $quotation->date_of_issue->format('Y')
                    : date('Y');

                $filename = join('-', [
                    'COT-TV',
                    $year,
                    str_pad((string) $quotation->number, 4, '0', STR_PAD_LEFT),
                    optional($quotation->date_of_issue)->format('Ymd') ?: date('Ymd'),
                ]);

                if ($dry) {
                    $this->line("  [dry] #{$quotation->id} → {$filename}");
                    $ok++;
                    continue;
                }

                try {
                    if (! $quotation->number_year) {
                        $quotation->number_year = (int) $year;
                    }
                    if ($quotation->series !== Quotation::SERIES_ECOMMERCE) {
                        $quotation->series = Quotation::SERIES_ECOMMERCE;
                    }
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
