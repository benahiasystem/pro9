<?php
// ######## INICIO TASAS OCHO DECIMALES: CONVERSIONES EXACTAS ########
// ######## FIN TASAS OCHO DECIMALES: CONVERSIONES EXACTAS ########
namespace Modules\Report\Helpers;

use App\Models\Tenant\Document;
use App\Models\Tenant\SaleNote;
use App\CoreFacturalo\Helpers\Functions\FunctionsHelper;

class UserCommissionHelper
{

    public static function getCommission($user, $utilities){
        // ######## INICIO CALCULOS EXACTOS SAB ########
        return \App\Support\Exact\Decimal::format(\Modules\Report\Services\EstimatedUserCommission::commission($user, $utilities), 2, '.', '');
        // ######## FIN CALCULOS EXACTOS SAB ########

    }


    public static function getUtilities($sale_notes, $documents){

        // ######## INICIO CALCULOS EXACTOS SAB ########
        $sale_notes_utility = \Modules\Report\Services\EstimatedUserCommission::utility($sale_notes, 'sale_note');
        $documents_utility = \Modules\Report\Services\EstimatedUserCommission::utility($documents, 'document');
        $total = $documents_utility->plus($sale_notes_utility);

        return [
            'sale_notes_utility' => \App\Support\Exact\Decimal::format($sale_notes_utility, 2, '.', ''),
            'documents_utility' => \App\Support\Exact\Decimal::format($documents_utility, 2, '.', ''),
            'total_utility' => \App\Support\Exact\Decimal::format($total, 2, '.', ''),
            'calculation_snapshot' => \App\Support\Exact\Decimal::snapshot(['sale_notes_utility'=>$sale_notes_utility,
                'documents_utility'=>$documents_utility, 'total_utility'=>$total]),
        ];
        // ######## FIN CALCULOS EXACTOS SAB ########

    }


    public static function getUtilityRecords($records){
        // ######## INICIO CALCULOS EXACTOS SAB ########
        $documents = $records->filter(fn($record)=>$record instanceof Document);
        $notes = $records->filter(fn($record)=>$record instanceof SaleNote);
        if ($documents->count() + $notes->count() !== $records->count()) {
            throw new \InvalidArgumentException('Origen de utilidad no admitido.');
        }
        return \App\Support\Exact\Decimal::represent(
            \Modules\Report\Services\EstimatedUserCommission::utility($documents, 'document')
                ->plus(\Modules\Report\Services\EstimatedUserCommission::utility($notes, 'sale_note')));
        // ######## FIN CALCULOS EXACTOS SAB ########

    }


    /**
     * 
     * Obtener totales para reporte de comisiones
     * Usado en:
     * Modules\Report\Http\Resources\ReportCommissionCollection
     * Formato excel y pdf (blade) de reportes comisiones
     *
     * @param $user
     * @return array
     */
    public static function getDataForReportCommission($user, $request)
    {
        return \Modules\Report\Services\SalesCommissionReport::forUser($user, $request);
    }

    /**
     * Devuelve los datos formateados para el reporte de cuentas pendientes por vendedor.
     * Incluye id, nombre, monto cobrado y comisión.
     *
     * @param $user
     * @param $request
     * @param int $days_expired
     * @return array
     */
    public static function getDataForPendingAccountCommissionReport($user, $request, $finances = [])
    {
        return \Modules\Report\Services\PendingAccountCommissionReport::forUser($user,$request,$finances);
    }

}
