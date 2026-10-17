<?php

namespace Modules\Finance\Helpers;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
use App\Support\Exact\Payables;
use App\Support\Exact\ReportDataset;

class ToPay
{
    public static function getToPay($request): ReportDataset
    {
        return Payables::dataset($request);
    }

    public static function getToPayNoFilter(): ReportDataset
    {
        return Payables::dataset([], true);
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
