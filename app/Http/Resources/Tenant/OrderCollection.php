<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\Catalogs\IdentityDocumentType;
use Illuminate\Http\Resources\Json\ResourceCollection;

class OrderCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function toArray($request)
    {
        $documentTypeDescriptions = IdentityDocumentType::query()->pluck('description', 'id');

        return $this->collection->transform(function($row, $key) use ($documentTypeDescriptions) {
            $customer = $row->customer;
            $purchaseCustomer = optional($row->purchase)->datos_del_cliente_o_receptor;

            $documentNumber = data_get($customer, 'numero_documento')
                ?? data_get($customer, 'number')
                ?? data_get($purchaseCustomer, 'numero_documento');
            $documentNumber = preg_replace('/\D/', '', (string) $documentNumber);
            if ($documentNumber === '0') {
                $documentNumber = '';
            }

            $documentTypeId = (string) (
                data_get($customer, 'codigo_tipo_documento_identidad')
                ?? data_get($customer, 'identity_document_type_id')
                ?? data_get($purchaseCustomer, 'codigo_tipo_documento_identidad')
                ?? data_get($purchaseCustomer, 'identity_document_type_id')
                ?? ''
            );
            $documentType = $documentTypeId !== ''
                ? ($documentTypeDescriptions->get($documentTypeId) ?? null)
                : null;

            return [
                'id' => $row->id,
                'external_id' => $row->external_id,
                'number_document' => $row->number_document,
                'order_id' => $row->publicNumber(),
                'order_code' => $row->order_code,
                'customer' => $row->customer->apellidos_y_nombres_o_razon_social,
                'customer_email' => $row->customer->correo_electronico,
                'customer_telefono' => $row->customer->telefono,
                'customer_direccion' => $row->customer->direccion,
                'customer_document_number' => $documentNumber !== '' ? $documentNumber : null,
                'customer_document_type' => $documentType,
                'is_guest' => $row->isGuestCheckout(),
                'items' => $row->items,
                'total' => $row->total,
                'total_discount' => $row->total_discount,
                'discount_coupont' => $row->discount_coupon ,
                'reference_payment' => strtoupper($row->reference_payment),
                'document_external_id' => $row->document_external_id,
                'created_at' => $row->created_at->format('Y-m-d H:i:s'),
                'status_order_id' => $row->status_order_id,
                'payment_status_order_id' => $row->payment_status_order_id,
                'shipping_status_order_id' => $row->shipping_status_order_id,
                'tracking_code' => $row->tracking_code,
                'purchase' => $row->purchase,
                'document_type_id' => optional($row->purchase)->codigo_tipo_documento,
                'has_sale_note' => !is_null($row->sale_note),
                'sale_note_number_full' => optional($row->sale_note)->number_full,
                'sale_note_id' => optional($row->sale_note)->id,
            ];
        });

    }
}
