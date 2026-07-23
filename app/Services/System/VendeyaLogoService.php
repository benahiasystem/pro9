<?php

namespace App\Services\System;

use App\Models\System\Configuration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

class VendeyaLogoService
{
    /** Todos los SVG que el build de Vendeya referencia directamente. */
    private const LOGO_FILES = [
        'images/logos/logo-horizontal-light.svg',
        'images/logos/logo-iso-light.svg',
        'images/logos/logo-oficial-horizontal.svg',
        'images/logos/logo-oficial-iso.svg',
        'images/logos/logo-oficial-vertical.svg',
        'images/logos/logo-vertical-light.svg',
        'images/svg/logo/isotipo-light.svg',
        'images/svg/logo/isotipo-oficial.svg',
    ];

    private const RASTER_MIMES = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
    ];

    private const HTACCESS_CONTENT = "# Los logos de Vendeya se reemplazan en caliente desde el admin.\n"
        . "# Se fuerza revalidación para evitar copias antiguas del navegador o service worker.\n"
        . "<IfModule mod_headers.c>\n"
        . "    <FilesMatch \"\\.(svg|png|jpe?g|gif|webp)$\">\n"
        . "        Header set Cache-Control \"no-cache, must-revalidate, max-age=0\"\n"
        . "        Header set Pragma \"no-cache\"\n"
        . "        Header set Expires \"0\"\n"
        . "    </FilesMatch>\n"
        . "</IfModule>\n";

    private function liveDir(): string
    {
        return public_path('vendeya');
    }

    private function originalsDir(): string
    {
        return storage_path('app/vendeya/logo-originals');
    }

    private function customLogoPath(): string
    {
        return storage_path('app/vendeya/custom-logo.svg');
    }

    public function ensureOriginalsBackup(): void
    {
        if (!File::isDirectory($this->originalsDir())) {
            File::makeDirectory($this->originalsDir(), 0755, true);
        }

        foreach (self::LOGO_FILES as $file) {
            $backup = $this->originalsDir() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
            $live = $this->livePath($file);

            if (!File::isDirectory(dirname($backup))) {
                File::makeDirectory(dirname($backup), 0755, true);
            }

            if (!File::exists($backup) && File::exists($live)) {
                File::copy($live, $backup);
            }
        }
    }

    public function applySystemLogo(): void
    {
        $this->ensureOriginalsBackup();
        $systemLogo = $this->systemLogoFilePath();

        if ($systemLogo === null) {
            $this->restoreDefaultLogo();
            return;
        }

        $this->writeToLiveFiles($this->buildSvgFromFile($systemLogo));
    }

    public function hasSystemLogo(): bool
    {
        return $this->systemLogoFilePath() !== null;
    }

    public function restoreDefaultLogo(): void
    {
        $this->ensureOriginalsBackup();

        foreach (self::LOGO_FILES as $file) {
            $relative = str_replace('/', DIRECTORY_SEPARATOR, $file);
            $backup = $this->originalsDir() . DIRECTORY_SEPARATOR . $relative;
            $live = $this->livePath($file);

            if (File::exists($backup) && $this->differs($live, md5_file($backup))) {
                File::copy($backup, $live);
            }
        }
    }

    public function ensureHtaccess(): void
    {
        foreach (['images/logos', 'images/svg/logo'] as $directory) {
            $dir = $this->livePath($directory);
            if (!File::isDirectory($dir)) {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . '.htaccess';
            if (!File::exists($path)) {
                File::put($path, self::HTACCESS_CONTENT);
            }
        }
    }

    public function storeCustomLogo(UploadedFile $file): void
    {
        $svg = $this->buildSvgFromFile(
            $file->getRealPath(),
            strtolower((string) $file->getMimeType()),
            strtolower((string) $file->getClientOriginalExtension())
        );

        if (!File::isDirectory(dirname($this->customLogoPath()))) {
            File::makeDirectory(dirname($this->customLogoPath()), 0755, true);
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

    public function applyCustomLogo(): void
    {
        if (!$this->hasCustomLogo()) {
            return;
        }

        $this->ensureOriginalsBackup();
        $this->writeToLiveFiles(File::get($this->customLogoPath()));
    }

    public function previewUrl(?int $version = null): string
    {
        return '/vendeya/images/logos/logo-oficial-horizontal.svg?v=' . ($version ?: time());
    }

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
        $physical = storage_path('app/public/uploads/login/' . $filename);

        return $filename !== '' && File::exists($physical) ? $physical : null;
    }

    private function writeToLiveFiles(string $svg): void
    {
        $hash = md5($svg);
        foreach (self::LOGO_FILES as $file) {
            $live = $this->livePath($file);
            if ($this->differs($live, $hash)) {
                File::put($live, $svg);
            }
        }
    }

    private function livePath(string $relative): string
    {
        return $this->liveDir() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    private function differs(string $livePath, string $expectedHash): bool
    {
        return !File::exists($livePath) || md5_file($livePath) !== $expectedHash;
    }

    private function buildSvgFromFile(string $path, ?string $mime = null, ?string $extension = null): string
    {
        $extension = $extension ?: strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        $mime = $mime ?: strtolower((string) (function_exists('mime_content_type') ? @mime_content_type($path) : ''));
        $isSvg = $extension === 'svg' || in_array($mime, ['image/svg+xml', 'text/xml', 'text/plain'], true);

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
            . '<image width="' . $width . '" height="' . $height . '" preserveAspectRatio="xMidYMid meet" '
            . 'xlink:href="data:' . $rasterMime . ';base64,' . $data . '"/></svg>';
    }
}
