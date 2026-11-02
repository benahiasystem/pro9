<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Resources\Json\ResourceCollection;

class DocumentPaymentCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function toArray($request)
    {
        return $this->collection->transform(function($row, $key) {
            return [
                'id' => $row->id,
                'date_of_payment' => $row->date_of_payment->format('d/m/Y'),
                // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
                'payment_method_type_description' => \App\Services\Payments\ReceiptPresentation::method($row),
                'destination_description' => \App\Services\Payments\ReceiptPresentation::destination($row),
                // ######## FIN MONEDA DEL MEDIO DE COBRO ########
                'reference' => $row->reference,
                'filename' => ($row->payment_file) ? $row->payment_file->filename:null,
                'payment' => $row->reversed_at ? 0 : $row->payment,
                'currency_type_id' => $row->currency_type_id,
                'original_amount' => $row->original_amount,
                ...\App\Services\Payments\ReceiptPresentation::tender($row),
                // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
                'exchange_rate' => $row->exchange_rate,
                'receipt_reused' => !empty($row->payment_method_snapshot['receipt_origin']),
                // ######## FIN MONEDA DEL MEDIO DE COBRO ########
                'tax_amount' => $row->tax_amount,
                // ######## INICIO CALCULOS EXACTOS PRO9 ########
                'receipt_parent_id' => $row->receipt_parent_id,
                // ######## FIN CALCULOS EXACTOS PRO9 ########
                'payment_adjustment' => \App\Support\Exact\Decimal::represent($row->calculation_snapshot['values']['payment_adjustment'] ?? 0),
                'calculation_snapshot' => $row->calculation_snapshot,
                'reversed_at' => $row->reversed_at,
                'operation_key' => $row->operation_key,
                'igtf_status' => $row->igtf_status,
                'fiscal_context' => $row->fiscal_context,
                'payment_received' => $row->payment_received,
                'payment_received_description' => $row->getPaymentReceivedDescription(),
            ];
        });
    }
}
