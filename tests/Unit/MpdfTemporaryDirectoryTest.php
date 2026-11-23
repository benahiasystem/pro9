<?php

namespace Tests\Unit;

use App\Support\Pdf\Mpdf;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\TestCase;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Symfony\Component\Process\Process;

class MpdfTemporaryDirectoryTest extends TestCase
{
    private $previousContainer;
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        $this->root = sys_get_temp_dir() . '/sab-mpdf-test-' . bin2hex(random_bytes(8));
        mkdir($this->root, 0700, true);
        $app = new Application(dirname(__DIR__, 2));
        $app->useStoragePath($this->root);
        Container::setInstance($app);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->root);
        Container::setInstance($this->previousContainer);
        parent::tearDown();
    }

    public function test_default_cache_renders_pdf_and_preserves_page_options(): void
    {
        $pdf = new Mpdf(['format' => [76, 180], 'margin_top' => 3]);
        $pdf->WriteHTML('<p>Ticket de prueba</p>');
        $bytes = $pdf->Output('', 'S');
        self::assertStringStartsWith('%PDF-', $bytes);
        $reader = new Fpdi();
        self::assertSame(1, $reader->setSourceFile(StreamReader::createByString($bytes)));
        self::assertEqualsWithDelta(76, $reader->getTemplateSize($reader->importPage(1))['width'], 0.01);
        if (function_exists('posix_geteuid')) {
            self::assertDirectoryExists($this->root . '/framework/cache/mpdf-user-' . posix_geteuid() . '/mpdf/ttfontdata');
        }
    }

    public function test_explicit_private_cache_is_preserved(): void
    {
        $private = $this->root . '/private-export-cache';
        $pdf = new Mpdf(['tempDir' => $private, 'format' => 'A4']);
        $pdf->WriteHTML('<p>Reporte de prueba</p>');
        self::assertStringStartsWith('%PDF-', $pdf->Output('', 'S'));
        self::assertDirectoryExists($private . '/mpdf/ttfontdata');
        self::assertDirectoryDoesNotExist($this->root . '/framework/cache');
    }

    public function test_concurrent_first_use_creates_the_cache_safely(): void
    {
        $code = <<<'PHP'
require $argv[1] . '/vendor/autoload.php';
$app = new \Illuminate\Foundation\Application($argv[1]);
$app->useStoragePath($argv[2]);
\Illuminate\Container\Container::setInstance($app);
$pdf = new \App\Support\Pdf\Mpdf();
$pdf->WriteHTML('<p>Prueba concurrente</p>');
if (substr($pdf->Output('', 'S'), 0, 5) !== '%PDF-') exit(1);
PHP;
        $processes = [];
        for ($i = 0; $i < 6; $i++) {
            $process = new Process([PHP_BINARY, '-r', $code, dirname(__DIR__, 2), $this->root]);
            $process->start();
            $processes[] = $process;
        }
        foreach ($processes as $process) {
            $process->wait();
            self::assertTrue($process->isSuccessful(), $process->getErrorOutput());
        }
    }
}
