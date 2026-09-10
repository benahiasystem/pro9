<?php
// ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########

namespace App\CoreFacturalo\Requests\Inputs;

use App\Models\Tenant\Document;
use App\Models\Tenant\Series;
use Carbon\Carbon;
use Exception;
use Modules\Document\Models\SeriesConfiguration;

class Functions
{
    public static function newNumber($fiscal_environment, $document_type_id, $series, $number, $model)
    {
        // Marca la serie como en uso al asignarle n├║mero en emisi├│n (┬º4.7).
        Series::markInUse($document_type_id, $series);

        // if ($number === '#') {
        //     // max() num├®rico evita saltos/duplicados frente a orderBy string.
        //     $max = $model::where('document_type_id', $document_type_id)
        //         ->where('series', $series)
        //         ->max(\Illuminate\Support\Facades\DB::raw('CAST(number AS UNSIGNED)'));

        //     if ($max !== null) {
        //         return (int) $max + 1;
        //     }

        //     $series_configuration = SeriesConfiguration::where([
        //         ['document_type_id', $document_type_id],
        //         ['series', $series],
        //     ])->first();

        //     return ($series_configuration) ? (int) $series_configuration->number : 1;
        // }

        // return $number;

        if ($number === '#') {
            $document = $model::select('number')
                                ->where('fiscal_environment', $fiscal_environment)
                                ->where('document_type_id', $document_type_id)
                                ->where('series', $series)
                                ->orderBy('number', 'desc')
                                ->first();
            return ($document)?(int)$document->number+1:1;
        }
        return $number;
    }

    public static function filename($company, $document_type_id, $series, $number)
    {
        return join('-', [$company->number, $document_type_id, $series, $number]);
    }

    public static function validateUniqueDocument($fiscal_environment, $document_type_id, $series, $number, $model)
    {
        $document = $model::where('document_type_id', $document_type_id)
                        ->where('series', $series)
                        ->where('number', $number)
                        ->first();
        if($document) {
            throw new Exception("El documento: {$document_type_id} {$series}-{$number} ya se encuentra registrado.");
        }
    }

    public static function identifier($fiscal_environment, $date_of_issue, $model)
    {
        $documents = $model::where('fiscal_environment', $fiscal_environment)
                        ->where('date_of_issue', $date_of_issue)
                        ->get();
        $numeration = count($documents) + 1;
        $path = explode('\\', $model);
        switch (array_pop($path)) {
            case 'Voided':
                $prefix = 'RA';
                break;
            default:
                $prefix = 'RC';
                break;
        }

        return join('-', [$prefix, Carbon::parse($date_of_issue)->format('Ymd'), $numeration]);
    }

    /**
     * @param      $inputs
     * @param      $key
     * @param null $default
     *
     * @return mixed|null
     */
    public static function valueKeyInArray($inputs, $key, $default = null)
    {
        return (isset($inputs[$key]) && null !== $inputs[$key]) ? $inputs[$key] : $default;
    }
}
// ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
