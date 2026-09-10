<?php

namespace Modules\MobileApp\Http\Resources\Api;

use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Models\Tenant\SaleNote;

class DocumentCentralizedCollection extends ResourceCollection
{
    public function toArray($request)
    {
        return $this->collection->map(function ($record) {
            $is_sale_note = $record instanceof SaleNote;

            return [
                'id'                        => $record->id,
                'external_id'               => $record->external_id,
                'document_type_id'          => $is_sale_note ? '80' : $record->document_type_id,
                'document_type_description' => $is_sale_note ? 'Nota de Venta' : optional($record->document_type)->description,
                'number_full'               => $record->number_full,
                'series'                    => $record->series,
                'number'                    => $record->number,
                'filename'                  => $record->filename,
                'date_of_issue'             => optional($record->date_of_issue)->format('Y-m-d'),
                'total'                     => (float) $record->total,
                'currency_type_id'          => $record->currency_type_id,
                'state_type_id'             => $record->state_type_id,
                'state_type_description'    => optional($record->state_type)->description,
                'customer' => [
                    'id'     => optional($record->person)->id,
                    'name'   => optional($record->person)->name,
                    'number' => optional($record->person)->number,
                    'email' => optional($record->person)->email,
                    'phone' => optional($record->person)->telephone,
                ],
                'user' => [
                    'id'   => optional($record->user)->id,
                    'name' => optional($record->user)->name,
                ],
                'print_a4'     => $is_sale_note
                    ? $record->getUrlPrintPdf('a4')
                    : $record->getUrlPrintByFormat('a4'),
                'print_ticket' => $is_sale_note
                    ? $record->getUrlPrintPdf('ticket')
                    : $record->getUrlPrintByFormat('ticket'),
                'created_at'   => optional($record->created_at)->format('Y-m-d H:i:s'),
            ];
        });
    }
}
