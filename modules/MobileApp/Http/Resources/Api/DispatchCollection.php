<?php

namespace Modules\MobileApp\Http\Resources\Api;

use Illuminate\Http\Resources\Json\ResourceCollection;

class DispatchCollection extends ResourceCollection
{
    /**
     *
     * Transformar el listado de órdenes de entrega (09) para scroll infinito en la app
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return $this->collection->map(function ($dispatch) {

            // snapshot JSON del cliente guardado en el documento (accessor getCustomerAttribute)
            $customer = $dispatch->customer;
            $receiver_name = optional($customer)->name;
            $receiver_number = optional($customer)->number;

            return [
                'id'                          => $dispatch->id,
                'external_id'                 => $dispatch->external_id,
                'document_type_id'            => $dispatch->document_type_id,
                'series'                      => $dispatch->series,
                'number'                      => $dispatch->number,
                'number_full'                 => $dispatch->number_full,
                'filename'                    => $dispatch->filename,
                'date_of_issue'               => optional($dispatch->date_of_issue)->format('Y-m-d'),
                'date_of_shipping'            => optional($dispatch->date_of_shipping)->format('Y-m-d'),
                'transport_mode_type_id'      => $dispatch->transport_mode_type_id,
                'transfer_reason_type_id'     => $dispatch->transfer_reason_type_id,
                'transfer_reason_description' => optional($dispatch->transfer_reason_type)->description,
                'total_weight'                => (float) $dispatch->total_weight,
                'unit_type_id'                => $dispatch->unit_type_id,
                'packages_number'             => $dispatch->packages_number,
                'customer_id'                 => $dispatch->customer_id,
                'customer_name'               => $receiver_name,
                'customer_number'             => $receiver_number,
                'sender_name'                 => $dispatch->sender_data['name'] ?? null,
                'sender_number'               => $dispatch->sender_data['number'] ?? null,
                'state_type_id'               => $dispatch->state_type_id,
                'state_type_description'      => optional($dispatch->state_type)->description,
                'has_xml'                     => (bool) $dispatch->has_xml,
                'has_pdf'                     => (bool) $dispatch->has_pdf,
                'has_cdr'                     => (bool) $dispatch->has_cdr,
                'download_external_pdf'       => $dispatch->download_external_pdf,
                'download_external_xml'       => $dispatch->download_external_xml,
                'download_external_cdr'       => $dispatch->download_external_cdr,
                'sunat_error_response'        => $dispatch->sunat_error_response,
                'created_at'                  => optional($dispatch->created_at)->format('Y-m-d H:i:s'),
            ];
        });
    }
}
