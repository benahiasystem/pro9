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
        (new \Illuminate\Database\Eloquent\Collection($this->collection->map(fn ($row) => $row instanceof \Illuminate\Http\Resources\Json\JsonResource ? $row->resource : $row)->all()))
            ->loadMissing(['global_payment', 'payment_file', 'sourceSaleNotePayment.global_payment', 'sourceSaleNotePayment.payment_file']);
        return $this->collection->transform(function($row, $key) {
            $origin = $row->sourceSaleNotePayment;
            $financial = $origin ?: $row;
            return [
                'id' => $row->id,
                'source_sale_note_payment_id' => $row->source_sale_note_payment_id,
                'is_source_allocation' => (bool) $row->source_sale_note_payment_id,
                'source_sale_note_id' => $origin ? $origin->sale_note_id : null,
                'file_type' => $origin ? 'sale_notes' : 'documents',
                'date_of_payment' => $row->date_of_payment->format('d/m/Y'),
                'payment_method_type_description' => $row->payment_method_type->description,
                'destination_description' => ($financial->global_payment) ? $financial->global_payment->destination_description:null,
                'reference' => $row->reference,
                'filename' => ($financial->payment_file) ? $financial->payment_file->filename:null,
                'payment' => $row->payment,
                'payment_received' => $row->payment_received,
                'payment_received_description' => $row->getPaymentReceivedDescription(),
            ];
        });
    }
}