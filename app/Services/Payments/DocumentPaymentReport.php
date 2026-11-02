<?php

namespace App\Services\Payments;

use App\Models\Tenant\DocumentPayment;
use App\Support\Exact\Decimal as D;

// ######## INICIO MONEDA DEL MEDIO DE COBRO ########
/** Separate real receipts from documentary applications in payment reports. */
final class DocumentPaymentReport
{
    public static function row(DocumentPayment $payment): array
    {
        $document=$payment->document;
        $snapshot=$payment->payment_method_snapshot ?? [];
        $derived=(bool)$payment->receipt_parent_id;
        $reused=!empty($snapshot['receipt_origin']);
        $voided=$document->isVoidedOrRejected();
        $reversed=(bool)$payment->reversed_at;
        $active=!$voided && !$reversed;
        $receipt=$active && !$derived && !$reused
            ? ($payment->original_amount === null ? null : $payment->cash_received_amount) : '0';
        $applied=$active ? D::represent($payment->exactValue('payment')) : '0';
        $rate = \App\Services\ExchangeRates\ExchangeRateMath::optionalRate($payment->exchange_rate);

        return [
            'id'=>$payment->id,
            'date_of_payment'=>$payment->date_of_payment->format('d/m/Y'),
            'payment_method_type_description'=>ReceiptPresentation::method($payment),
            'destination_description'=>ReceiptPresentation::destination($payment) ?? ($reused ? match ($snapshot['receipt_origin']['type'] ?? null) {
                \Modules\Sale\Models\QuotationPayment::class => 'Cobro de cotización',
                \Modules\Sale\Models\TechnicalServicePayment::class => 'Cobro de servicio técnico',
                default => 'Cobro de nota de venta',
            } : '—'),
            'change'=>$active && !$derived && !$reused ? ($payment->original_amount === null ? null : D::represent($payment->exactValue('change'))) : '0',
            'payment'=>$receipt,
            'currency_type_id'=>$payment->currency_type_id,
            'exchange_rate'=>$rate,
            'applied_payment'=>$applied,
            'document_currency_type_id'=>$document->currency_type_id,
            'kind'=>$derived ? 'Aplicación de IGTF' : ($reused ? 'Cobro reutilizado' : 'Cobro'),
            'status'=>$reversed ? 'Revertido' : ($voided ? 'Documento anulado o rechazado' : 'Vigente'),
            'reference'=>$payment->reference,
            'customer'=>data_get($document->customer,'name',''),
            'number'=>$document->number_full,
            'total'=>D::represent($document->exactValue('total')),
        ];
    }
}
// ######## FIN MONEDA DEL MEDIO DE COBRO ########
