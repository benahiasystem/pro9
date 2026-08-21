<?php

namespace Modules\CashReport\Services\Builders;

use App\Models\Tenant\Cash;
use Modules\CashReport\Services\Contracts\CashReportBuilderInterface;
use Modules\CashReport\Services\HeaderDataBuilder;

/**
 * Ingresos y egresos en efectivo con destino caja.
 * Lógica trasladada desde Modules\Pos\Http\Controllers\CashController@reportCashIncomeEgress.
 */
class IncomeEgressBuilder implements CashReportBuilderInterface
{
    public function build(Cash $cash, array $options): array
    {
        $header = HeaderDataBuilder::build($cash);
        $data = $header;
        $data_payments = collect();

        foreach ($cash->cash_documents as $cash_document)
        {
            $model_associated = $cash_document->getDataModelAssociated();
            $payments = $model_associated->getCashPayments();

            $payments->each(function($payment) use($data_payments){
                $data_payments->push($payment);
            });
        }

        $data['total_income'] = $data_payments->where('type_transaction', 'income')->sum('payment');
        $data['total_egress'] = $data_payments->where('type_transaction', 'egress')->sum('payment');
        $data['total_balance'] = $data['total_income'] - $data['total_egress'];

        return [
            'header' => $header,
            'data' => $data,
            'data_payments' => $data_payments,
        ];
    }
}
