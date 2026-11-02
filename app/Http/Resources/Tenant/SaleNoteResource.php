<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Tenant\SaleNote;
use App\Models\Tenant\Configuration;

class SaleNoteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {

        $sale_note = SaleNote::find($this->id);
        // ######## INICIO DESTINOS HISTORICOS DE COBRO ########
        $data = $sale_note->getCollectionData();
        $payments = $sale_note->payments()->with(['global_payment', 'payment_method_type', 'payment_file'])->get();
        $data['recorded_payments'] = self::getTransformPayments($payments);
        return $data;
        // ######## FIN DESTINOS HISTORICOS DE COBRO ########
        /** Movido al modelo */
        $sale_note->seller_id = $sale_note->user_id;
        $sale_note->payments = self::getTransformPayments($sale_note->payments);

        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'number' => $this->number_full,
            'identifier' => $this->identifier,
            'date_of_issue' => $this->date_of_issue->format('Y-m-d'),
            'print_ticket' => url('')."/sale-notes/print/{$this->external_id}/ticket",
            'print_a4' => url('')."/sale-notes/print/{$this->external_id}/a4",
            'print_a5' => url('')."/sale-notes/print/{$this->external_id}/a5",
            'print_ticket_58' => url('')."/sale-notes/print/{$this->external_id}/ticket_58",
            'sale_note' => $sale_note,
            'serie' => $this->series,
            'number' => $this->number,
            'seller_id' => $this->seller_id,
            'message_text' => "Su comprobante de nota de venta {$this->number_full} ha sido generado correctamente, puede revisarlo en el siguiente enlace: ".url('')."/sale-notes/print/{$this->external_id}/".(optional(Configuration::first())->qr_api_pdf_format === 'a4' ? 'a4' : 'ticket')

        ];
    }


    public static function getTransformPayments($payments){

        return $payments->transform(function($row, $key){
            return [
                'id' => $row->id,
                'sale_note_id' => $row->sale_note_id,
                'date_of_payment' => $row->date_of_payment->format('Y-m-d'),
                'payment_method_type_id' => $row->payment_method_type_id,
                'reference' => $row->reference,
                'payment' => $row->payment,
                'payment_method_snapshot' => $row->payment_method_snapshot,
                'currency_type_id' => $row->currency_type_id,
                'original_amount' => $row->original_amount,
                ...\App\Services\Payments\ReceiptPresentation::tender($row),
                'exchange_rate' => $row->exchange_rate,
                'exchange_rate_source' => $row->exchange_rate_source,
                'exchange_rate_date' => $row->exchange_rate_date ? $row->exchange_rate_date->format('Y-m-d') : null,
                'payment_method_type' => $row->payment_method_type,
                'payment_destination_id' => ($row->global_payment) ? ($row->global_payment->type_record == 'cash' ? 'cash':$row->global_payment->destination_id):data_get($row->payment_method_snapshot, 'receipt_origin.destination_id'),
                'payment_destination_description' => \App\Services\Payments\ReceiptPresentation::destination($row),
                'payment_filename' => ($row->payment_file) ? $row->payment_file->filename:null,
            ];
        });

    }
}
