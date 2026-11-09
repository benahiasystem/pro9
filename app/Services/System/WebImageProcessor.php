<?php

namespace App\Services\System;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class WebImageProcessor
{
    private const MIME_TYPES = [
        'webp' => 'image/webp',
        'avif' => 'image/avif',
    ];

    /** Decode and re-encode modern web images before publishing them. */
    public function encode(UploadedFile $file, string $attribute = 'image'): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $mime = self::MIME_TYPES[$extension] ?? null;
        $path = $file->getPathname();
        $dimensions = @getimagesize($path);

        if (!$file->isValid() || !$mime || $file->getMimeType() !== $mime
            || !$dimensions || ($dimensions['mime'] ?? null) !== $mime
            || $dimensions[0] < 1 || $dimensions[1] < 1) {
            throw ValidationException::withMessages([$attribute => 'El archivo no es una imagen WebP o AVIF válida.']);
        }

        if ($dimensions[0] > 8000 || $dimensions[1] > 8000 || $dimensions[0] * $dimensions[1] > 16000000) {
            throw ValidationException::withMessages([$attribute => 'La imagen no puede superar 8000 píxeles por lado ni 16 megapíxeles.']);
        }

        try {
            $support = function_exists('gd_info') ? gd_info() : [];
            $canUseGd = $support[$extension === 'avif' ? 'AVIF Support' : 'WebP Support'] ?? false;
            $finder = new ExecutableFinder();
            $binary = $finder->find('magick') ?? $finder->find('convert');
            if ($extension === 'avif' && $canUseGd) {
                $contents = $this->encodeWithGd($path, $extension);
            } elseif ($binary !== null) {
                $contents = $this->encodeWithImageMagick($path, $extension, $binary);
            } elseif ($canUseGd) {
                $contents = $this->encodeWithGd($path, $extension);
            } else {
                throw new \RuntimeException('Image decoder unavailable.');
            }

            $encodedDimensions = @getimagesizefromstring($contents);
            if (!$encodedDimensions || ($encodedDimensions['mime'] ?? null) !== $mime
                || $encodedDimensions[0] !== $dimensions[0] || $encodedDimensions[1] !== $dimensions[1]) {
                throw new \RuntimeException('Invalid encoded image.');
            }

            return $contents;
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages([$attribute => 'No se pudo procesar la imagen. Verifique que el archivo sea válido.']);
        }
    }

    private function encodeWithGd(string $path, string $extension): string
    {
        $decoder = 'imagecreatefrom' . $extension;
        $image = @$decoder($path);
        if ($image === false) {
            throw new \RuntimeException('Unable to decode image.');
        }

        try {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            ob_start();
            try {
                $success = $extension === 'avif' ? imageavif($image, null, 85, 8) : imagewebp($image, null, 90);
                $contents = ob_get_contents();
            } finally {
                ob_end_clean();
            }

            if (!$success || $contents === '') {
                throw new \RuntimeException('Unable to encode image.');
            }

            return $contents;
        } finally {
            imagedestroy($image);
        }
    }

    private function encodeWithImageMagick(string $path, string $extension, string $binary): string
    {
        // Decode in a bounded subprocess when available: legacy GD builds do
        // not support AVIF and can fail fatally on malformed WebP streams.
        $process = new Process([
            $binary,
            '-limit', 'thread', '1',
            '-limit', 'memory', '64MiB',
            '-limit', 'map', '128MiB',
            '-limit', 'disk', '0',
            $extension . ':' . $path . '[0]',
            '-colorspace', 'sRGB',
            '-strip',
            '-quality', '85',
            '-define', 'heic:speed=8',
            $extension . ':-',
        ]);
        $process->setTimeout(60);
        $process->mustRun();

        return $process->getOutput();
    }
}
