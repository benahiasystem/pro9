<?php

use App\Models\Tenant\Catalogs\AffectationIgvType;
use App\Models\Tenant\Catalogs\Country;
use App\Models\Tenant\Catalogs\CurrencyType;
use App\Models\Tenant\Catalogs\Department;
use App\Models\Tenant\Catalogs\IdentityDocumentType;
use App\Support\Venezuela\IdentityDocument;
use App\Models\Tenant\Catalogs\OperationType;
use Illuminate\Support\Facades\Cache;
// ######## INICIO CAMBIO GEOPOLITICO VENEZUELA
use Illuminate\Support\Facades\DB;
// ######## FIN CAMBIO GEOPOLITICO VENEZUELA
use Illuminate\Support\Facades\File;


if (!function_exists('func_str_to_upper_utf8')) {
    function func_str_to_upper_utf8($text)
    {
        if (is_null($text)) {
            return null;
        }
        return mb_strtoupper($text, 'utf-8');
    }
}

if (!function_exists('func_str_to_lower_utf8')) {
    function func_str_to_lower_utf8($text)
    {
        if (is_null($text)) {
            return null;
        }
        return mb_strtolower($text, 'utf-8');
    }
}

if (!function_exists('func_filter_items')) {
    function func_filter_items($query, $text)
    {
        $text_array = explode(' ', $text);
        foreach ($text_array as $txt) {
            $trim_txt = trim($txt);
            $query->where('text_filter', 'like', "%$trim_txt%");
        }

        return $query;
    }
}

if (!function_exists('func_get_locations')) {
    // ######## INICIO CAMBIO GEOPOLITICO VENEZUELA
    function func_get_locations(?string $countryId = null)
    {
        $countryId = $countryId ?: config('venezuela.country_id', 'VE');
        $tenantDatabase = (string) DB::connection('tenant')->getDatabaseName();
        $cacheKey = "locations:v2:{$tenantDatabase}:{$countryId}";

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        if ($countryId !== config('venezuela.country_id', 'VE')) {
            Cache::put($cacheKey, [], 1440);

            return [];
        }

        $locations = [];
        $departments = Department::query()
            ->with('provinces', 'provinces.districts')
            ->where('active', true)
            ->get();
        foreach ($departments as $department) {
            $children_provinces = [];
            foreach ($department->provinces as $province) {
                $children_districts = [];
                foreach ($province->districts as $district) {
                    $children_districts[] = [
                        'value' => $district->id,
                        'label' => func_str_to_upper_utf8($district->description)
                    ];
                }
                $children_provinces[] = [
                    'value' => $province->id,
                    'label' => func_str_to_upper_utf8($province->description),
                    'children' => $children_districts
                ];
            }
            $locations[] = [
                'value' => $department->id,
                'label' => func_str_to_upper_utf8($department->description),
                'children' => $children_provinces
            ];
        }

        Cache::put($cacheKey, $locations, 1440);

        return $locations;
    }
    // ######## FIN CAMBIO GEOPOLITICO VENEZUELA
}

if (!function_exists('func_get_countries')) {
    function func_get_countries()
    {
        if (Cache::has('countries')) {
            return Cache::get('countries');
        }

        $countries = Country::query()
            ->get();

        Cache::put('countries', $countries, 1440);

        return $countries;
    }
}

if (!function_exists('func_get_operation_types')) {
    function func_get_operation_types()
    {
        if (Cache::has('operation_types')) {
            return Cache::get('operation_types');
        }

        $operation_types = OperationType::query()
            ->where('active', true)
            ->get();

        Cache::put('operation_types', $operation_types, 1440);

        return $operation_types;
    }
}

if (!function_exists('func_get_affectation_igv_types')) {
    function func_get_affectation_igv_types()
    {
        if (Cache::has('affectation_igv_types')) {
            return Cache::get('affectation_igv_types');
        }

        $affectation_igv_types = AffectationIgvType::query()
            ->where('active', true)
            ->get();

        Cache::put('affectation_igv_types', $affectation_igv_types, 1440);

        return $affectation_igv_types;
    }
}

if (!function_exists('func_get_identity_document_types')) {
    function func_get_identity_document_types()
    {
        if (Cache::has('identity_document_types')) {
            return Cache::get('identity_document_types');
        }

        $identity_document_types = IdentityDocumentType::query()
            ->orderByPersonPriority()
            ->get();

        Cache::put('identity_document_types', $identity_document_types, 1440);

        return $identity_document_types;
    }
}

if (!function_exists('format_identity_document')) {
    function format_identity_document($identityDocumentTypeId, $number): string
    {
        return IdentityDocument::format($identityDocumentTypeId, $number);
    }
}

if (!function_exists('format_person_identity_document')) {
    function format_person_identity_document($person): string
    {
        return IdentityDocument::format(
            data_get($person, 'identity_document_type_id'),
            data_get($person, 'number')
        );
    }
}

if (!function_exists('func_get_currency_types')) {
    function func_get_currency_types()
    {
        if (Cache::has('currency_types')) {
            return Cache::get('currency_types');
        }

        $currency_types = CurrencyType::query()
            ->where('active', true)
            ->get();

        Cache::put('currency_types', $currency_types, 1440);

        return $currency_types;
    }
}

if (!function_exists('func_is_windows')) {
    function func_is_windows()
    {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }
}

if (!function_exists('func_get_templates')) {
    function func_get_templates()
    {
        $templates = app_path('CoreFacturalo'.DIRECTORY_SEPARATOR.'Templates'.
                                             DIRECTORY_SEPARATOR.'pdf');
                

        $files = collect(File::directories($templates))
                ->map(fn($dir) => basename($dir))
                ->values()
                ->toArray();

        return $files;            

    }
}

// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
/** Local application assets stay local; tenant uploads are materialized only for processing. */
function tenant_public_path($path = '') {
    $relative = str_replace(DIRECTORY_SEPARATOR, '/', (string) $path);
    if (str_starts_with($relative, 'storage/uploads/') || str_starts_with($relative, 'public/uploads/') || str_starts_with($relative, 'uploads/')) {
        try {
            return app(\App\Services\Storage\TenantMedia::class)->localPath($relative);
        } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
            return '';
        }
    }
    return public_path($path);
}
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE

// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
/** Stable SAB URL; the controller validates the current DB reference and audience. */
function tenant_asset($path, $secure = null) {
    $reference = (string) $path;
    if (!app(\App\Tenancy\Environment::class)->tenant()) return asset($path, $secure);
    foreach(['storage/defaults/'=>'uploads/defaults/','storage/tag_images/'=>'uploads/tag_images/'] as $legacy=>$canonical){
        if(str_starts_with($reference,$legacy))$reference=$canonical.substr($reference,strlen($legacy));
    }
    if(in_array($reference,['storage/uploads/items','uploads/items'],true))return url('media/images/items');
    if(str_starts_with($reference,'storage/uploads/promotions/restaurant/') || str_starts_with($reference,'uploads/promotions/restaurant/')){
        $name=basename($reference);\App\Services\Storage\TenantDisk::relative($reference);
        return url('media/restaurant/promotions/'.rawurlencode($name));
    }
    foreach (['storage/uploads/','public/uploads/','uploads/'] as $prefix) {
        if (str_starts_with($reference,$prefix)) {
            $relative=substr($reference,strlen($prefix));
            $parts=explode('/',$relative);
            if (count($parts)!==2) throw new \InvalidArgumentException('Referencia de imagen inválida.');
            if ($parts[0] === 'items' && $parts[1] === 'imagen-no-disponible.jpg') return asset('logo/imagen-no-disponible.jpg',$secure);
            \App\Services\Storage\TenantDisk::relative('uploads/'.$relative);
            return url('media/images/'.rawurlencode($parts[0]).'/'.rawurlencode($parts[1]));
        }
    }
    return asset($path,$secure);
}
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE

// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
function tenant_skin_url($skin) {
    return $skin->is_system?asset('storage/skins/'.$skin->filename):url('media/skin/'.$skin->id);
}
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE

// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
function tenant_storage_transaction(callable $callback) {
    try {
        return \Illuminate\Support\Facades\DB::connection('tenant')->transaction(function()use($callback){
            $result=$callback();
            $data=$result instanceof \Illuminate\Http\JsonResponse?$result->getData(true):$result;
            if($result===false || (is_array($data) && ($data['success'] ?? null)===false) || ($result instanceof \Symfony\Component\HttpFoundation\Response && $result->getStatusCode()>=400)) {
                throw new \App\Services\Storage\StorageResponseRollback($result);
            }
            return $result;
        });
    } catch(\App\Services\Storage\StorageResponseRollback $rollback) { return $rollback->result; }
}
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE

function authorize_tenant_print(\Illuminate\Database\Eloquent\Model $document): void
{
    if (!app()->isProduction()) return;

    $user = auth()->user();
    abort_unless($user instanceof \App\Models\Tenant\User, 403);
    $branch = $document->getAttribute('establishment_id');
    abort_if(!$user->isAdmin() && $branch !== null && (string) $branch !== (string) $user->establishment_id, 403);
}
