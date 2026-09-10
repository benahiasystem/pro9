<?php

namespace App\Traits;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Hostname;
use Hyn\Tenancy\Models\Website;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Utilidades compartidas por los comandos de catálogo demo:
 * resolución de tenants, descarga de imágenes y generación de miniaturas.
 */
trait DemoCatalogTrait
{
    /** Carpeta (disco local) donde el sistema guarda las imágenes de productos. */
    private function itemsDirectory(): string
    {
        return 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'items' . DIRECTORY_SEPARATOR;
    }

    /**
     * Resuelve los tenants a procesar. Acepta id, uuid o fqdn separados por coma.
     * Sin --tenant no devuelve nada: preferimos fallar antes que tocar todos los tenants.
     *
     * @return Collection<int, Website>
     */
    private function resolveTenants(?string $option): Collection
    {
        $keys = collect(explode(',', (string) $option))
            ->map(fn($k) => trim($k))
            ->filter()
            ->values();

        if ($keys->isEmpty()) {
            return collect();
        }

        return $keys->map(function ($key) {
            if (ctype_digit($key)) {
                return Website::find($key);
            }

            $website = Website::where('uuid', $key)->first();

            if (!$website) {
                $hostname = Hostname::where('fqdn', $key)->first();
                $website = optional($hostname)->website;
            }

            if (!$website) {
                $this->warn("Tenant no encontrado: {$key}");
            }

            return $website;
        })->filter()->unique('id')->values();
    }

    private function useTenant(Website $website): void
    {
        app(Environment::class)->tenant($website);
    }

    private function tenantLabel(Website $website): string
    {
        $fqdn = Hostname::where('website_id', $website->id)->value('fqdn');

        return "#{$website->id} {$website->uuid}" . ($fqdn ? " ({$fqdn})" : '');
    }

    /**
     * Descarga una imagen remota y genera las tres variantes que usa el sistema
     * (original, _medium y _small). Si el archivo ya existe no vuelve a bajarlo.
     *
     * @return string|null Nombre del archivo principal, o null si falló la descarga.
     */
    private function storeRemoteImage(string $url, string $baseName, bool $force = false): ?string
    {
        $directory = $this->itemsDirectory();
        $fileName = "{$baseName}.jpg";

        if (!$force && Storage::exists($directory . $fileName)) {
            return $fileName;
        }

        $content = $this->downloadImage($url);

        if ($content === null) {
            return null;
        }

        Storage::put($directory . $fileName, $content);

        foreach (['medium' => 512, 'small' => 256] as $suffix => $width) {
            $image = \Image::make($content)->resize($width, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });

            Storage::put(
                $directory . "{$baseName}_{$suffix}.jpg",
                (string) $image->encode('jpg', $suffix === 'medium' ? 80 : 70)
            );
        }

        return $fileName;
    }

    /**
     * La CDN de origen entrega el original a 2500px; pedimos 1000px para no
     * inflar el storage del demo.
     */
    private function downloadImage(string $url): ?string
    {
        $url = str_contains($url, '?') ? $url : $url . '?q=90&iw=1000&ih=1000&crop=1';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 45,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; Pro71DemoCatalog/1.0)',
        ]);

        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $status !== 200 || strlen($body) < 1024) {
            return null;
        }

        return $body;
    }

    private function readCatalog(?string $path): array
    {
        $path = $path ?: database_path('data/iittala-catalog.json');

        if (!is_file($path)) {
            $this->error("No se encontró el catálogo: {$path}");

            return [];
        }

        $data = json_decode(file_get_contents($path), true);

        if (!is_array($data) || empty($data['products'])) {
            $this->error("El catálogo {$path} no tiene productos.");

            return [];
        }

        return $data;
    }
}
