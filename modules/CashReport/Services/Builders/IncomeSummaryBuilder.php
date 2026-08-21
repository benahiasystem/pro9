<?php

namespace Modules\CashReport\Services\Builders;

use App\Models\Tenant\Cash;
use App\Models\Tenant\Company;
use App\Models\Tenant\Configuration;
use Modules\CashReport\Services\Contracts\CashReportBuilderInterface;
use Modules\CashReport\Services\HeaderDataBuilder;

/**
 * Resumen de ingresos por método de pago.
 * Lógica trasladada desde Modules\Report\Http\Controllers\ReportIncomeSummaryController.
 */
class IncomeSummaryBuilder implements CashReportBuilderInterface
{
    public function build(Cash $cash, array $options): array
    {
        $cash = Cash::filterDataIncomeSummaryPayment()->findOrFail($cash->id);
        $cash_data = $this->getDataIncomeSummaryPayment($cash);

        return [
            'header' => HeaderDataBuilder::build($cash),
            'data' => $cash_data,
            'cash' => $cash,
            'company' => Company::active(),
            'cash_data' => $cash_data,
            'order_cash_income' => Configuration::getOrderCashIncome(),
        ];
    }

    public function getDataIncomeSummaryPayment($cash)
    {
        $payments = collect();
        $total_document_payments = 0;
        $total_sale_note_payments = 0;

        foreach ($cash->global_destination as $global_payment) 
        {
            $row = $global_payment->payment->getRowIncomeSummaryPayment();
            $payments->push($row);

            if($row['type'] === 'document')
            {
                $total_document_payments += $row['payment_for_calculate'];
            }
            else
            {
                $total_sale_note_payments += $row['payment_for_calculate'];
            }
        }

        return [
            'total_document_payments' => number_format($total_document_payments, 2, '.', ''),
            'total_sale_note_payments' => number_format($total_sale_note_payments, 2, '.', ''),
            'payments' => $payments,
        ];
    }
}
