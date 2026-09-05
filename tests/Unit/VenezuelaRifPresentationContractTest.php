<?php

namespace Tests\Unit;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class VenezuelaRifPresentationContractTest extends TestCase
{
    /** @test */
    public function presentation_sources_do_not_show_legacy_ruc_labels(): void
    {
        $roots = [
            base_path('app/CoreFacturalo/Templates/pdf'),
            base_path('app/CoreFacturalo/Templates/preprinted_pdf'),
            base_path('modules'),
            resource_path('views'),
            resource_path('js/views/tenant'),
        ];

        foreach ($roots as $root) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

            foreach ($files as $file) {
                if (! $file->isFile() || ! preg_match('/\.(php|vue|js)$/', $file->getFilename())) {
                    continue;
                }

                $path = $file->getPathname();

                if (strpos($path, base_path('modules').DIRECTORY_SEPARATOR) === 0
                    && strpos($path, DIRECTORY_SEPARATOR.'Resources'.DIRECTORY_SEPARATOR) === false
                    && strpos($path, DIRECTORY_SEPARATOR.'Exports'.DIRECTORY_SEPARATOR) === false) {
                    continue;
                }

                self::assertDoesNotMatchRegularExpression(
                    '/R\.U\.C\.?|\bRUCs?\b|\bRuc\b/',
                    file_get_contents($path),
                    "Todavía existe una etiqueta visible RUC en {$path}"
                );
            }
        }
    }
}
