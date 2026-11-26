<?php
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
namespace App\Support\ItemImport;

use App\Models\Tenant\Item;
use App\Services\Storage\ItemImageWriter;

final class ImportedItemImage
{
    /** The Excel URL denotes an external source, never a private storage key. */
    public function load(?string $url, string $description, ?string $internalId): array
    {
        $placeholder = array_fill_keys(['image','image_medium','image_small'], 'imagen-no-disponible.jpg');
        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) return $placeholder;
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');
        $port = $scheme === 'https' ? 443 : 80;
        if (!in_array($scheme,['http','https'],true) || !$host || isset($parts['user']) || isset($parts['pass'])
            || ($parts['port'] ?? $port) !== $port) return $placeholder;

        // Pin the validated public IPv4 address. Redirects and proxies must not change the destination.
        $addresses = filter_var($host,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4) ? [$host] : (gethostbynamel($host) ?: []);
        if (!$addresses) return $placeholder;
        foreach ($addresses as $address) {
            if (!filter_var($address,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4|FILTER_FLAG_GLOBAL_RANGE)) return $placeholder;
        }

        $temporary = tmpfile();
        if (!$temporary) throw new \RuntimeException('No se pudo preparar la imagen del Excel.');
        $client = curl_init($url);
        $written = 0;
        try {
            curl_setopt_array($client,[
                CURLOPT_CONNECTTIMEOUT=>3, CURLOPT_TIMEOUT=>12, CURLOPT_FOLLOWLOCATION=>false,
                CURLOPT_PROTOCOLS=>CURLPROTO_HTTP|CURLPROTO_HTTPS, CURLOPT_PROXY=>'',
                CURLOPT_RESOLVE=>[$host.':'.$port.':'.$addresses[0]], CURLOPT_IPRESOLVE=>CURL_IPRESOLVE_V4,
                CURLOPT_WRITEFUNCTION=>static function ($handle, string $data) use ($temporary, &$written): int {
                    $size = strlen($data);
                    if ($written + $size > 5 * 1024 * 1024) return 0;
                    $bytes = fwrite($temporary,$data);
                    $written += $bytes ?: 0;
                    return $bytes ?: 0;
                },
            ]);
            if (!curl_exec($client) || curl_getinfo($client,CURLINFO_RESPONSE_CODE) !== 200 || !$written) return $placeholder;
            $path = stream_get_meta_data($temporary)['uri'];
            if (!in_array(mime_content_type($path),['image/jpeg','image/png','image/gif','image/webp'],true)) return $placeholder;
            $item = new Item(['description'=>$description,'internal_id'=>$internalId]);
            app(ItemImageWriter::class)->replace($item,$path);
            return $item->only(array_keys($placeholder));
        } finally {
            curl_close($client);
            fclose($temporary);
        }
    }
}
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
