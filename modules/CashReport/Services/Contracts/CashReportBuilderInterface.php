<?php

namespace Modules\CashReport\Services\Contracts;

use App\Models\Tenant\Cash;

interface CashReportBuilderInterface
{
    /**
     * Arma la información del reporte.
     *
     * @param  Cash  $cash
     * @param  array $options  format, paper, summary, is_garage, action
     * @return array ['header' => array, 'data' => array]
     */
    public function build(Cash $cash, array $options): array;
}
