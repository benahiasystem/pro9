<?php

namespace Modules\Dashboard\Helpers;

use App\Models\Tenant\Establishment;
use App\Support\Exact\{Receivables, ReportDataset};

// ######## INICIO CALCULOS EXACTOS PRO9 ########
class DashboardView
{
    public static function getEstablishments()
    {
        return Establishment::all()->map(fn($row)=>['id'=>$row->id,'name'=>$row->description]);
    }

    public static function getUnpaid($request): ReportDataset
    {
        return Receivables::dataset($request);
    }

    public static function getUnpaidFilterUser($request): ReportDataset
    {
        return Receivables::dataset($request);
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
