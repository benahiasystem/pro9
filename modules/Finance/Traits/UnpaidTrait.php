<?php

namespace Modules\Finance\Traits;

use App\Support\Exact\{Decimal, ReportDataset};

// ######## INICIO CALCULOS EXACTOS PRO9 ########
trait UnpaidTrait
{
    public function transformRecords($records): ReportDataset
    {
        return $records instanceof ReportDataset ? $records : new ReportDataset($records);
    }

    public function getTotalToPay($row): \Brick\Math\BigRational
    {
        return Decimal::readRequired($row,'total_to_pay');
    }

    public function getTotalCreditNote($row): \Brick\Math\BigRational
    {
        return Decimal::readRequired($row,'total_credit_notes');
    }

    public function getRetentionAmount($row): \Brick\Math\BigRational
    {
        return Decimal::readRequired($row,'retention_amount');
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
