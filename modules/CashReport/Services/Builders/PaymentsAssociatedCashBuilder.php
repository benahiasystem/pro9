<?php

namespace Modules\CashReport\Services\Builders;

use App\CoreFacturalo\Helpers\Functions\GeneralPdfHelper;
use App\Models\Tenant\Cash;
use App\Models\Tenant\PaymentMethodType;
use Modules\CashReport\Services\CashReportRegistry;
use Modules\CashReport\Services\Contracts\CashReportBuilderInterface;
use Modules\CashReport\Services\HeaderDataBuilder;

/**
 * Pagos en efectivo asociados a caja.
 *  - PDF: pagos con destino caja y en efectivo (cpe y nv).
 *  - Excel: ingresos y egresos en efectivo por moneda.
 * Lógica trasladada desde Modules\Pos\Traits\CashReportTrait.
 */
class PaymentsAssociatedCashBuilder implements CashReportBuilderInterface
{
    public function build(Cash $cash, array $options): array
    {
        if (($options['format'] ?? null) === CashReportRegistry::FORMAT_EXCEL) {
            $cash = Cash::filterDataCashPaymentReport()->findOrFail($cash->id);
            $header = HeaderDataBuilder::build($cash);
            $data = $header;
            $this->setDataCashPaymentReportExcel($cash, $data);

            return [
                'header' => $header,
                'data' => $data,
            ];
        }

        $cash = Cash::with([
                    'global_destination' => function ($query) {
                        return $query->filtersPaymentsAssociatedCash();
                    },
                ])
                ->findOrFail($cash->id);

        $header = HeaderDataBuilder::build($cash);
        $data = $header;
        $result = $this->getDataPaymentsAssociatedCash($cash, $data);

        return [
            'header' => $header,
            'data' => $result['data'],
            'payments' => $result['payments'],
        ];
    }

    /**
     *
     * Reporte excel v2 de caja para pagos en efectivo con destino caja, ingresos y egresos
     * 
     * @return void
     */
    public function setDataCashPaymentReportExcel($cash, &$data)
    {
        $payments = collect();

        foreach ($cash->global_destination as $global_payment) 
        {
            $payments->push($global_payment->payment->getDataCashPaymentReport());
        }

        $payments_pen = $payments->where('currency_type_id', PaymentMethodType::NATIONAL_CURRENCY_ID);
        $payments_usd = $payments->where('currency_type_id', PaymentMethodType::DOLAR_CURRENCY_ID);
        
        $data['payments_pen'] = $payments_pen;
        $data['payments_usd'] = $payments_usd;

        $data['cash_income_pen'] = GeneralPdfHelper::setNumberFormat($payments_pen->where('type_transaction', 'income')->sum('payment'));
        $data['cash_egress_pen'] = GeneralPdfHelper::setNumberFormat($payments_pen->where('type_transaction', 'egress')->sum('payment'));
        
        //saldo inicial de caja se considera en soles
        $data['balance_cash_pen'] = GeneralPdfHelper::setNumberFormat(($data['cash_income_pen'] + $data['cash_beginning_balance']) - $data['cash_egress_pen']); 


        $data['cash_income_usd'] = GeneralPdfHelper::setNumberFormat($payments_usd->where('type_transaction', 'income')->sum('payment'));
        $data['cash_egress_usd'] = GeneralPdfHelper::setNumberFormat($payments_usd->where('type_transaction', 'egress')->sum('payment'));

        $data['balance_cash_usd'] = GeneralPdfHelper::setNumberFormat($data['cash_income_usd'] - $data['cash_egress_usd']);

        $data['cash_beginning_balance'] = GeneralPdfHelper::setNumberFormat($data['cash_beginning_balance']);
    }

    /**
     *
     * Data para reporte de pagos asociados a caja, con destino caja y en efectivo
     * 
     * @return array
     */
    public function getDataPaymentsAssociatedCash($cash, &$data)
    {
        $payments = collect();

        foreach ($cash->global_destination as $global_payment) 
        {
            $payments->push($global_payment->payment->getRowResourceCashPayment());
        }
        
        $data['total_income'] = $payments->sum('payment');

        return [
            'data' => $data,
            'payments' => $payments,
        ];
    }

}
