<?php

namespace App\Support\Pdf;

/** mPDF with an application-owned cache, separate for each runtime user. */
class Mpdf extends \Mpdf\Mpdf
{
    public function __construct(array $config = [], $container = null)
    {
        if (!array_key_exists('tempDir', $config)) {
            $config['tempDir'] = function_exists('posix_geteuid')
                ? storage_path('framework/cache/mpdf-user-' . posix_geteuid())
                : self::isolatedTempDir();
        }

        $fontCache = rtrim($config['tempDir'], '/\\') . '/mpdf/ttfontdata';
        if (!is_dir($fontCache) && !@mkdir($fontCache, 0775, true) && !is_dir($fontCache)) {
            throw new \RuntimeException('No se pudo preparar el directorio temporal de PDF.');
        }

        parent::__construct($config, $container);
    }

    private static function isolatedTempDir(): string
    {
        static $directory;
        if ($directory === null) {
            $directory = sys_get_temp_dir() . '/sab-mpdf-' . bin2hex(random_bytes(16));
            if (!mkdir($directory, 0700)) {
                throw new \RuntimeException('No se pudo preparar el directorio temporal de PDF.');
            }
            register_shutdown_function(static function () use ($directory): void {
                (new \Illuminate\Filesystem\Filesystem())->deleteDirectory($directory);
            });
        }
        return $directory;
    }
}
