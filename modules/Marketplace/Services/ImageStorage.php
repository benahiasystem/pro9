<?php

namespace Modules\Marketplace\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Guarda las imágenes tal cual llegan en base64. Sin thumbnails, sin re-encode,
 * sin variantes: la app ya comprime.
 *
 * Lo único que se valida es el mime real (leído de los bytes, no del nombre) y
 * el peso.
 */
class ImageStorage
{
    private const MAX_BYTES = 1048576; // 1 MB

    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function disk()
    {
        return Storage::disk(config('marketplace.disk'));
    }

    /**
     * @return string|null Ruta relativa en el disk, o null si el base64 es
     *                     inválido, pesa de más o no es un formato permitido.
     *                     Nunca lanza: una imagen mala no debe tumbar un sync
     *                     de 500 ítems.
     */
    public function store(string $base64, string $path, ?string $previousPath = null): ?string
    {
        $binary = $this->decode($base64);

        if ($binary === null || strlen($binary) > self::MAX_BYTES) {
            return null;
        }

        $extension = $this->extensionOf($binary);

        if ($extension === null) {
            return null;
        }

        $fullPath = $path . '.' . $extension;

        $this->disk()->put($fullPath, $binary);

        // Si cambió el formato, el archivo anterior queda huérfano.
        if ($previousPath && $previousPath !== $fullPath) {
            $this->disk()->delete($previousPath);
        }

        return $fullPath;
    }

    public function storeLogo(int $storeId, string $base64, ?string $previousPath = null): ?string
    {
        return $this->store($base64, "marketplace/stores/{$storeId}/logo", $previousPath);
    }

    public function storeItemImage(int $storeId, string $externalId, string $base64, ?string $previousPath = null): ?string
    {
        // El external_id viene de la app: puede traer barras o espacios.
        $safe = Str::slug($externalId) ?: md5($externalId);

        return $this->store($base64, "marketplace/stores/{$storeId}/items/{$safe}", $previousPath);
    }

    private function decode(string $base64): ?string
    {
        // Tolera el prefijo data:image/png;base64,
        if (str_contains($base64, ',')) {
            $base64 = substr($base64, strpos($base64, ',') + 1);
        }

        $binary = base64_decode(trim($base64), true);

        return ($binary === false || $binary === '') ? null : $binary;
    }

    private function extensionOf(string $binary): ?string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo === false) {
            return null;
        }

        $mime = finfo_buffer($finfo, $binary);
        finfo_close($finfo);

        return self::ALLOWED[$mime] ?? null;
    }
}
