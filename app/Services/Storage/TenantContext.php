<?php
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
namespace App\Services\Storage;

use App\Tenancy\Environment;
use App\Tenancy\Models\Website;

final class TenantContext
{
    public function run(string $uuid, callable $callback)
    {
        TenantStorage::validateUuid($uuid);
        $website=Website::where('uuid',$uuid)->firstOrFail();
        $environment=app(Environment::class);
        return $environment->run($website, function () use ($callback, $website) {
            try {
                return $callback($website);
            } finally {
                try {
                    app(TenantMedia::class)->cleanup();
                } finally {
                    app(TenantUploads::class)->cleanup();
                }
            }
        });
    }
}
