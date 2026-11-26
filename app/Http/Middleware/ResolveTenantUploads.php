<?php
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
namespace App\Http\Middleware;

use App\Services\Storage\TenantUploads;
use Closure;

final class ResolveTenantUploads
{
    public function handle($request, Closure $next)
    {
        if (!app(\App\Tenancy\Environment::class)->tenant()) return $next($request);
        $uploads = app(TenantUploads::class);
        $original = $request->input();
        try {
            $action=(string) optional($request->route())->getActionName();
            $defaultPurpose=preg_match('/Item|Category|Promotion|Configuration|User/',$action)?'image':'attachment';
            $resolve = function (array $input, string $scope = '') use (&$resolve,$uploads,$defaultPurpose): array {
                $id = $input['upload_id'] ?? null;
                if (is_string($id) && \Illuminate\Support\Str::isUuid($id) &&
                    \App\Models\Storage\StorageUpload::whereKey($id)->exists()) {
                    if (!empty($input['temp_path']) && $input['temp_path'] !== $id) {
                        abort(422,'Las referencias de carga no coinciden.');
                    }
                    $input['temp_path']=$id;
                }
                foreach ($input as $key=>$value) {
                    if (is_array($value)) $input[$key]=$resolve($value,$scope==='payments'?'payments':(string)$key);
                    elseif ($value && is_string($key) && preg_match('/(?:^|_)temp_path(?:_|$)/',$key)) {
                        if (!is_string($value)) abort(422,'Referencia de carga inválida.');
                        $purpose=(str_contains($key,'attached') || $scope==='payments')?'attachment':((str_contains($key,'photo') || str_contains($key,'yape'))?'image':$defaultPurpose);
                        $input[$key]=$uploads->materialize($value,$purpose);
                    }
                }
                return $input;
            };
            if (!in_array($request->method(),['GET','HEAD','OPTIONS'],true)) $request->merge($resolve($original));
            return $next($request);
        } finally {
            $request->replace($original);
            $uploads->cleanup();
        }
    }
}
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
