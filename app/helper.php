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
