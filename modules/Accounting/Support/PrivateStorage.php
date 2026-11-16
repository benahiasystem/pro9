<?php

namespace Modules\Accounting\Support;

use Modules\Accounting\Domain\CompanyContext;
use Illuminate\Support\Facades\DB;

/** Physical tenant boundary plus owned organization/company. API paths are never accepted. */
final class PrivateStorage
{
    public function root(): string
    {
        $connection = DB::connection('tenant');
        $database = $connection->getDatabaseName();
        $selected = $connection->selectOne('SELECT DATABASE() AS name')->name;
        if (!$database || $database !== $selected) { throw new \LogicException('La conexión no acredita el tenant de almacenamiento.'); }
        $key = hash('sha256', 'accounting-storage-v1:' . $database);
        $context = app(CompanyContext::class);
        if (!\Illuminate\Support\Str::isUuid($context->organizationId()) || !\Illuminate\Support\Str::isUuid($context->id())) { throw new \LogicException('La empresa no acredita un identificador de almacenamiento válido.'); }
        $path = rtrim(config('accounting.private_storage', storage_path('app/accounting/private')), '/\\');
        foreach (['tenants', $key, $context->organizationId(), $context->id()] as $part) {
            $path .= '/' . $part;
            if (is_link($path)) { throw new \RuntimeException('El almacenamiento del tenant no admite enlaces entre directorios.'); }
        }
        return $path;
    }

    public function directory(string $relative): string
    {
        if (!preg_match('/^[a-zA-Z0-9_\/-]+$/D', $relative) || str_contains($relative, '..') || str_starts_with($relative, '/')) { throw new \LogicException('Directorio privado inválido.'); }
        $path = $this->root() . '/' . $relative;
        if (!is_dir($path) && !@mkdir($path, 0700, true) && !is_dir($path)) { throw new \RuntimeException('No se pudo preparar el directorio privado.'); }
        if (is_link($path)) { throw new \RuntimeException('El directorio privado no admite enlaces.'); }
        return $path;
    }

    public function resolve(string $relative): string
    {
        if (!preg_match('/^[a-zA-Z0-9_.\/-]+$/D', $relative) || str_contains($relative, '..') || str_starts_with($relative, '/')) { throw new \LogicException('Referencia de archivo inválida.'); }
        return $this->root() . '/' . $relative;
    }
}
