<?php

namespace App\Services\System;

use App\Models\System\Configuration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

class MozoLogoService
{
    /**
     * Archivos de logo que el build del Mozo referencia con rutas fijas.
     * El Mozo siempre pide estas mismas URLs, por eso basta con reemplazar
     * el contenido de los archivos para cambiar el logo sin recompilar.
     */
    private const LOGO_FILES = [
        'logo-horizontal.svg',
        'logo-horizontal-light.svg',
        'logo-iso.svg',
        'logo-iso-light.svg',
    ];

    /** Recursos usados por el navegador como favicon, fuera de images/logos. */
    private const FAVICON_FILES = [
        'images/svg/logo/isotipo-oficial.svg',
    ];

    private const RASTER_MIMES = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
    ];

    private const HTACCESS_CONTENT = "# Los logos del Mozo se reemplazan en caliente desde el admin (mismo nombre de archivo).\n"
        . "# Forzamos revalidacion para que el navegador y el service worker no sirvan una copia vieja.\n"
        . "<IfModule mod_headers.c>\n"
        . "    <FilesMatch \"\\.(svg|png|jpe?g|gif|webp)$\">\n"
        . "        Header set Cache-Control \"no-cache, must-revalidate, max-age=0\"\n"
        . "        Header set Pragma \"no-cache\"\n"
        . "        Header set Expires \"0\"\n"
        . "    </FilesMatch>\n"
        . "</IfModule>\n";

    private function liveDir(): string
    {
        return public_path('mozo/images/logos');
    }

    private function originalsDir(): string
    {
        return storage_path('app/mozo/logo-originals');
    }

    private function customLogoPath(): string
    {
        return storage_path('app/mozo/custom-logo.svg');
    }

    /**
     * Guarda una copia pristina de los logos originales la primera vez.
     * Se llama antes de cualquier reemplazo para poder restaurar el logo del sistema.
     */
    public function ensureOriginalsBackup(): void
    {
        $originalsDir = $this->originalsDir();

        if (!File::isDirectory($originalsDir)) {
            File::makeDirectory($originalsDir, 0755, true);
        }

        foreach (self::LOGO_FILES as $file) {
            $backup = $originalsDir . DIRECTORY_SEPARATOR . $file;
            $live = $this->liveDir() . DIRECTORY_SEPARATOR . $file;

            if (!File::exists($backup) && File::exists($live)) {
                File::copy($live, $backup);
            }
        }

        foreach (self::FAVICON_FILES as $file) {
            $backup = $this->originalsDir() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
            $live = $this->liveRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);

            if (!File::isDirectory(dirname($backup))) {
                File::makeDirectory(dirname($backup), 0755, true);
            }

            if (!File::exists($backup) && File::exists($live)) {
                File::copy($live, $backup);
            }
        }
    }

    /**
     * Aplica el logo configurado a nivel System (Configuration->login->logo).
     * Si el System no tiene un logo subido, restaura el logo por defecto de Mozo.
     */
    public function applySystemLogo(): void
    {
        $this->ensureOriginalsBackup();

        $systemLogo = $this->systemLogoFilePath();

        if ($systemLogo === null) {
            $this->restoreDefaultLogo();
            return;
        }

        $svg = $this->buildSvgFromFile($systemLogo);
        $this->writeToLiveFiles($svg);
    }

    /**
     * Indica si el System tiene un logo subido en su configuración de login.
     */
    public function hasSystemLogo(): bool
    {
        return $this->systemLogoFilePath() !== null;
    }

    /**
     * Restaura los logos originales de Mozo sobre las rutas del build.
     */
    public function restoreDefaultLogo(): void
    {
        $this->ensureOriginalsBackup();

        foreach (self::LOGO_FILES as $file) {
            $backup = $this->originalsDir() . DIRECTORY_SEPARATOR . $file;
            $live = $this->liveDir() . DIRECTORY_SEPARATOR . $file;

            if (File::exists($backup) && $this->differs($live, md5_file($backup))) {
                File::copy($backup, $live);
            }
        }

        foreach (self::FAVICON_FILES as $file) {
            $relative = str_replace('/', DIRECTORY_SEPARATOR, $file);
            $backup = $this->originalsDir() . DIRECTORY_SEPARATOR . $relative;
            $live = $this->liveRoot() . DIRECTORY_SEPARATOR . $relative;

            if (File::exists($backup) && $this->differs($live, md5_file($backup))) {
                File::copy($backup, $live);
            }
        }
    }

    /**
     * Recrea el .htaccess de no-caché en la carpeta de logos si falta
     * (por ejemplo, tras actualizar el compilado del Mozo).
     */
    public function ensureHtaccess(): void
    {
        foreach ([$this->liveDir(), $this->liveRoot() . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'svg' . DIRECTORY_SEPARATOR . 'logo'] as $directory) {
            if (!File::isDirectory($directory)) {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . '.htaccess';
            if (!File::exists($path)) {
                File::put($path, self::HTACCESS_CONTENT);
            }
        }
    }

    /**
     * Ruta física del logo del System, o null si no hay uno subido.
     * El valor almacenado es una URL tipo asset('storage/uploads/login/xxx.png').
     */
    private function systemLogoFilePath(): ?string
    {
        $configuration = Configuration::query()->first();
        $login = $configuration?->login;
        $logoUrl = is_object($login) ? ($login->logo ?? null) : null;

        if (!is_string($logoUrl) || trim($logoUrl) === '') {
            return null;
        }

        $path = parse_url($logoUrl, PHP_URL_PATH) ?: $logoUrl;
        $filename = basename($path);

        if ($filename === '') {
            return null;
        }

        $physical = storage_path('app/public/uploads/login/' . $filename);

        return File::exists($physical) ? $physical : null;
    }

    /**
     * Convierte el archivo subido en un SVG válido y lo persiste como
     * logo personalizado (para poder reaplicarlo al alternar el interruptor).
     */
    public function storeCustomLogo(UploadedFile $file): void
    {
        $svg = $this->buildSvgFromFile(
            $file->getRealPath(),
            strtolower((string) $file->getMimeType()),
            strtolower((string) $file->getClientOriginalExtension())
        );

        $dir = dirname($this->customLogoPath());
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        File::put($this->customLogoPath(), $svg);
    }

    public function hasCustomLogo(): bool
    {
        return File::exists($this->customLogoPath());
    }

    /** Elimina el logo personalizado y restaura los recursos originales del build. */
    public function deleteCustomLogo(): void
    {
        if ($this->hasCustomLogo()) {
            File::delete($this->customLogoPath());
        }

        $this->restoreDefaultLogo();
    }

    /**
     * Escribe el logo personalizado guardado sobre las 4 rutas del build.
     */
    public function applyCustomLogo(): void
    {
        if (!$this->hasCustomLogo()) {
            return;
        }

        $this->ensureOriginalsBackup();

        $this->writeToLiveFiles(File::get($this->customLogoPath()));
    }

    /**
     * Escribe el mismo contenido SVG sobre las 4 rutas de logo del build.
     * Solo escribe si el contenido cambió (idempotente, apto para el scheduler).
     */
    private function writeToLiveFiles(string $svg): void
    {
        $hash = md5($svg);

        foreach (self::LOGO_FILES as $file) {
            $live = $this->liveDir() . DIRECTORY_SEPARATOR . $file;

            if ($this->differs($live, $hash)) {
                File::put($live, $svg);
            }
        }

        foreach (self::FAVICON_FILES as $file) {
            $live = $this->liveRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);

            if ($this->differs($live, $hash)) {
                File::put($live, $svg);
            }
        }
    }

    private function liveRoot(): string
    {
        return public_path('mozo');
    }

    /**
     * Indica si el archivo vivo no existe o su contenido difiere del hash dado.
     */
    private function differs(string $livePath, string $expectedHash): bool
    {
        return !File::exists($livePath) || md5_file($livePath) !== $expectedHash;
    }

    /**
     * URL pública del logo horizontal (para previsualización en el admin),
     * con parámetro de versión para evitar caché.
     */
    public function previewUrl(?int $version = null): string
    {
        $version = $version ?: time();

        return '/mozo/images/logos/logo-horizontal.svg?v=' . $version;
    }

    /**
     * Genera un SVG válido a partir de un archivo de imagen.
     * - SVG: se usa tal cual.
     * - Raster (PNG/JPG/GIF/WEBP): se envuelve dentro de un SVG que embebe la
     *   imagen en base64, así el archivo servido como image/svg+xml se renderiza.
     */
    private function buildSvgFromFile(string $path, ?string $mime = null, ?string $extension = null): string
    {
        $extension = $extension ?: strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        $mime = $mime ?: strtolower((string) (function_exists('mime_content_type') ? @mime_content_type($path) : ''));

        $isSvg = $extension === 'svg'
            || $mime === 'image/svg+xml'
            || $mime === 'text/xml'
            || $mime === 'text/plain';

        if ($isSvg) {
            return File::get($path);
        }

        $rasterMime = self::RASTER_MIMES[$extension]
            ?? (in_array($mime, self::RASTER_MIMES, true) ? $mime : 'image/png');

        $data = base64_encode((string) file_get_contents($path));

        $dimensions = @getimagesize($path);
        $width = ($dimensions && !empty($dimensions[0])) ? (int) $dimensions[0] : 240;
        $height = ($dimensions && !empty($dimensions[1])) ? (int) $dimensions[1] : 84;

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" '
            . 'viewBox="0 0 ' . $width . ' ' . $height . '" width="' . $width . '" height="' . $height . '">'
            . '<image width="' . $width . '" height="' . $height . '" '
            . 'preserveAspectRatio="xMidYMid meet" '
            . 'xlink:href="data:' . $rasterMime . ';base64,' . $data . '"/>'
            . '</svg>';
    }
}
