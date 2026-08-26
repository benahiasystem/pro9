<?php

namespace Modules\MobileApp\Http\Resources\Api;

use Illuminate\Http\Resources\Json\ResourceCollection;

class DispatchCollection extends ResourceCollection
{
    /**
     *
     * Transformar el listado de guias de remision (09 y 31) para scroll infinito en la app
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return $this->collection->map(function ($dispatch) {

            // snapshot JSON del cliente guardado en el documento (accessor getCustomerAttribute)
            $customer = $dispatch->customer;
            $is_carrier = $dispatch->document_type_id === '31';

            // 09: el "contraparte" es el cliente. 31: remitente y destinatario.
            $receiver_name = $is_carrier
                ? ($dispatch->receiver_data['name'] ?? null)
                : (optional($customer)->name);
            $receiver_number = $is_carrier
                ? ($dispatch->receiver_data['number'] ?? null)
                : (optional($customer)->number);

            return [
                'id'                          => $dispatch->id,
                'external_id'                 => $dispatch->external_id,
                'document_type_id'            => $dispatch->document_type_id,
                'series'                      => $dispatch->series,
                'number'                      => $dispatch->number,
                'number_full'                 => $dispatch->number_full,
                'filename'                    => $dispatch->filename,
                'date_of_issue'               => $dispatch->date_of_issue?->format('Y-m-d'),
                'date_of_shipping'            => $dispatch->date_of_shipping?->format('Y-m-d'),
                'transport_mode_type_id'      => $dispatch->transport_mode_type_id,
                'transfer_reason_type_id'     => $dispatch->transfer_reason_type_id,
                'transfer_reason_description' => $dispatch->transfer_reason_type?->description,
                'total_weight'                => (float) $dispatch->total_weight,
                'unit_type_id'                => $dispatch->unit_type_id,
                'packages_number'             => $dispatch->packages_number,
                'customer_id'                 => $dispatch->customer_id,
                'customer_name'               => $receiver_name,
                'customer_number'             => $receiver_number,
                'sender_name'                 => $dispatch->sender_data['name'] ?? null,
                'sender_number'               => $dispatch->sender_data['number'] ?? null,
                'state_type_id'               => $dispatch->state_type_id,
                'state_type_description'      => $dispatch->state_type?->description,
                'has_xml'                     => (bool) $dispatch->has_xml,
                'has_pdf'                     => (bool) $dispatch->has_pdf,
                'has_cdr'                     => (bool) $dispatch->has_cdr,
                'download_external_pdf'       => $dispatch->download_external_pdf,
                'download_external_xml'       => $dispatch->download_external_xml,
                'download_external_cdr'       => $dispatch->download_external_cdr,
                'sunat_error_response'        => $dispatch->sunat_error_response,
                'created_at'                  => $dispatch->created_at?->format('Y-m-d H:i:s'),
            ];
        });
    }
}
