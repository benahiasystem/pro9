<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Resources\Json\ResourceCollection;

class SaleNotePaymentCollection extends ResourceCollection
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
                'currency_type_id' => $row->currency_type_id,
                'original_amount' => $row->original_amount,
                ...\App\Services\Payments\ReceiptPresentation::tender($row),
                'exchange_rate' => $row->exchange_rate,
                'filename' => ($row->payment_file) ? $row->payment_file->filename:null,
                // ######## INICIO CALCULOS EXACTOS PRO9 ########
                ...\App\Support\Exact\SaleNoteBalance::paymentTransport($row),
                // ######## FIN CALCULOS EXACTOS PRO9 ########
            ];
        });
    }
}
