<?php

namespace Modules\CashReport\Services\Builders;

use App\Models\Tenant\Cash;
use App\Models\Tenant\PaymentMethodType;
use Modules\CashReport\Services\Contracts\CashReportBuilderInterface;
use Modules\CashReport\Services\HeaderDataBuilder;

/**
 * Reporte general de caja V2 (pagos al contado con destino caja agrupados por método de pago).
 * Lógica trasladada desde Modules\Pos\Traits\CashReportTrait.
 */
class GeneralWithPaymentsBuilder implements CashReportBuilderInterface
{
    public function build(Cash $cash, array $options): array
    {
        $cash = Cash::filterDataGeneralCashReport()->findOrFail($cash->id);
        $header = HeaderDataBuilder::build($cash);
        $data = $header;

        $result = $this->getDataCashReportWithPayments($cash, $data);

        return [
            'header' => $header,
            'data' => $result['data'],
            'group_payments' => $result['group_payments'],
            'expense_payments' => $result['expense_payments'],
        ];
    }

    /**
     *
     * Data para reporte de caja v2 asociados a caja
     * 
     * @return array
     */
    public function getDataCashReportWithPayments($cash, &$data)
    {
        $payments = collect();

        foreach ($cash->global_destination as $global_payment) 
        {
            $payments->push($global_payment->payment->getRowResourceCashPayment());
        }
        
        $data['total_income'] = $payments->where('type_transaction', 'income')->where('payment_method_type_id', PaymentMethodType::CASH_PAYMENT_ID)->sum('payment');
        $data['total_egress'] = $payments->where('type_transaction', 'egress')->where('payment_method_type_id', PaymentMethodType::CASH_PAYMENT_ID)->sum('payment');
        $data['total_balance'] =  $data['cash_beginning_balance'] + $data['total_income'] - $data['total_egress'];

        $payments_with_payment_method = $payments->where('type', '!=', 'expense_payment');
        $expense_payments = $payments->where('type', 'expense_payment'); // no tiene relacion con payment_method_type_id, se agregara a la data de efectivo, ya que el registro va directo a caja
        
        // se agrupara pagos que tienen relacion con payment_method_type_id
        $group_payments = $payments_with_payment_method->sortBy('payment_method_type_id')->groupBy('payment_method_type_id');

        return [
            'data' => $data,
            'group_payments' => $group_payments,
            'expense_payments' => $expense_payments,
        ];
    }

}
