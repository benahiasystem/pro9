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
    public static function newNumber($fiscal_environment, $document_type_id, $series, $number, $model, $establishment_id = null)
    {
        $subject = new $model();
        $subject->fiscal_environment = $fiscal_environment;
        return \App\Services\SeriesNumbering::next($subject, $document_type_id, $series, $number, $establishment_id);
    }

    public static function filename($company, $document_type_id, $series, $number, $establishment_id = null)
    {
        $series = \App\Services\SeriesNumbering::normalizeCode($series);
        if ($series === '') {
            if (!$establishment_id) throw new \InvalidArgumentException('La numeración requiere sucursal.');
            return join('-', [$company->number, $document_type_id, 'SIN_SERIE_S' . $establishment_id, $number]);
        }
        return join('-', [$company->number, $document_type_id, $series, $number]);
    }

    public static function validateUniqueDocument($fiscal_environment, $document_type_id, $series, $number, $model, $establishment_id = null)
    {
        $series = \App\Services\SeriesNumbering::normalizeCode($series);
        $query = $model::where('fiscal_environment', $fiscal_environment)->where('document_type_id', $document_type_id)
                        ->where('series', $series)
                        ->where('number', $number);
        if ($establishment_id !== null) $query->where('establishment_id', $establishment_id);
        if ($series === '' && !$establishment_id) throw new \InvalidArgumentException('La numeración requiere sucursal.');
        $document = $query->first();
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
