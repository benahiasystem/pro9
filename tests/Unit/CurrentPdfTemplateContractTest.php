<?php

namespace Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use PhpParser\ParserFactory;
use Tests\TestCase;

class CurrentPdfTemplateContractTest extends TestCase
{
    public function test_pdf_templates_compile_without_retired_fiscal_branches(): void
    {
        $compiler = new BladeCompiler(new Filesystem(), sys_get_temp_dir());
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $errors = [];
        $count = 0;
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path('CoreFacturalo/Templates/pdf')));
        foreach ($files as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $source = file_get_contents($file->getPathname());
            self::assertDoesNotMatchRegularExpression('/BOLETA|C[oÓ]DIGO HASH|document->hash|Amazon[ií]a|Comprobante de Pago Electr[oó]nico|total_isc|system_isc|total_plastic_bag_taxes|showIsc\s*\(/i', $source, $file->getPathname());
            if (preg_match('/^(?:invoice|note|pedido)_/', $file->getFilename())) {
                self::assertStringNotContainsString('document->qr', $source, $file->getPathname());
            }
            self::assertDoesNotMatchRegularExpression(
                '/document_type(?:->id|_id)[^\n]{0,80}[\'\"]03[\'\"]|[\'\"]03[\'\"][^\n]{0,80}document_type(?:->id|_id)/i',
                $source,
                $file->getPathname()
            );
            try {
                $parser->parse($compiler->compileString($source));
            } catch (\Throwable $exception) {
                $errors[] = $file->getPathname() . ': ' . $exception->getMessage();
            }
            $count++;
        }
        self::assertGreaterThan(0, $count);
        self::assertSame([], $errors, implode("\n", $errors));
    }
    public function test_document_report_templates_compile_after_retiring_old_totals(): void
    {
        $compiler = new BladeCompiler(new Filesystem(), sys_get_temp_dir());
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        foreach (['documents','state_account','cash','status_client','customers','commercial_analysis','document_hotels'] as $directory) {
            foreach (glob(base_path('modules/Report/Resources/views/'.$directory.'/*.blade.php')) as $path) {
                $source=file_get_contents($path);
                self::assertDoesNotMatchRegularExpression('/total_free|total_unaffected|total_igv_free/',$source,$path);
                $parser->parse($compiler->compileString($source));
            }
        }
        foreach (['modules/Finance/Resources/views/global_payments/*.blade.php','modules/Finance/Resources/views/movements/*.blade.php','resources/views/pdf/partials/*.blade.php','resources/views/tenant/document_payments/report.blade.php'] as $pattern) {
            foreach (glob(base_path($pattern)) as $path) {
                self::assertNotNull($parser->parse($compiler->compileString(file_get_contents($path))),$path);
            }
        }
    }

}
