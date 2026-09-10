<?php
// ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\Company;
use App\Models\Tenant\Configuration;
use App\Models\Tenant\Document;
use App\Models\Tenant\DocumentItem;
use App\Models\Tenant\Person;
use App\Models\Tenant\SaleNote;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request
     * @return array
     */
    public function toArray($request)
    {

        $response_message = null;
        $response_type = null;

        /** @var Document $document */
        $document = $this->resource;

        $nvs = $document->getNvCollection();

        $customer = $document->customer;
        $customer_email = $customer->email;

        /** @var Person $person */
        $person = $document->person;
        $mails = $person->getCollectionData();
        $customer_email=  $mails['optional_email_send'];


        $total_payment = $document->payments->sum('payment');
        if ($document->retention) {
            $balance = number_format($document->total - $document->retention->amount - $total_payment, 2, '.', '');
        } else {
            $balance = number_format($document->total - $total_payment, 2, '.', '');
        }

        $btn_voided = false;

        if ($document->group_id === '01') {
            if ($document->state_type_id === '05') {
                $btn_voided = true;
            }
        }

        if ($document->group_id === '02') {
            if ($document->state_type_id === '05') {
                $btn_voided = true;
            }
        }

        $identityDocumentType = optional($person)->identity_document_type;

        $data = [
            'id' => $document->id,
            'fiscal_environment' => $document->fiscal_environment,
            'fiscal_emission_mode' => $document->fiscal_emission_mode,
            'external_id' => $document->external_id,
            'group_id' => $document->group_id,
            'number' => $document->number_full,
            'date_of_issue' => $document->date_of_issue->format('Y-m-d'),
            'customer_email' => $customer_email,
            'download_pdf' => $document->download_external_pdf,
            'print_ticket' => url('')."/print/document/{$document->external_id}/ticket",
            'print_ticket_58' => url('')."/print/document/{$document->external_id}/ticket_58",
            'print_a4' => url('')."/print/document/{$document->external_id}/a4",
            'print_a5' => url('')."/print/document/{$document->external_id}/a5",
            'pdf_a4_filename' => url('')."/print/document/{$document->external_id}/a4/{$document->filename}.pdf",
            'pdf_a4_data' => [
                "filename_only" => $document->filename,
                "extension_only" => "pdf"
            ],
            'response_message' => $response_message,
            'response_type' => $response_type,
            'customer_telephone' => optional($document->person)->telephone,
            'message_text' => "Su Factura {$this->number_full} ha sido generada correctamente, puede revisarla en el siguiente enlace: ".url('')."/print/document/{$this->external_id}/".(optional(Configuration::first())->qr_api_pdf_format === 'a4' ? 'a4' : 'ticket')."",
            'sales_note' => $nvs,


            'document_type_id' => $document->document_type_id,
            'document_type_description' => optional($document->document_type)->description,
            'state_type_id' => $document->state_type_id,
            'state_type_description' => optional($document->state_type)->description,
            'customer_name' => optional($customer)->name,
            'customer_number' => format_person_identity_document($customer),
            'customer_identity_document_type_description' => optional($identityDocumentType)->description,
            'customer_address' => optional($customer)->address ?: optional($person)->address,
            'user_name' => optional($document->user)->name,
            'user_email' => optional($document->user)->email,
            'seller_name' => optional($document->seller)->name ?: optional($document->user)->name,
            'establishment' => $document->establishment,
            'currency_type_id' => $document->currency_type_id,
            'exchange_rate_sale' => $document->exchange_rate_sale,
            'total_taxed' => $document->total_taxed,
            'total_igv' => $document->total_igv,
            'total' => $document->total,
            'total_paid' => $total_payment,
            'balance' => $balance,
            'has_pdf' => true,
            'btn_voided' => $btn_voided,
            'items' => self::mapDocumentItems($document),
            'payments' => $document->payments->map(function ($row) {
                return [
                    'id' => $row->id,
                    'date_of_payment' => $row->date_of_payment->format('d/m/Y'),
                    'payment_method_type_description' => optional($row->payment_method_type)->description,
                    'destination_description' => ($row->global_payment) ? $row->global_payment->destination_description : null,
                    'reference' => $row->reference,
                    'payment' => $row->payment,
                ];
            })->values(),
        ];
        return $data;
    }

    /**
     * Resuelve las filas de ítems del comprobante (relación, consulta directa o tabla document_items).
     */
    protected static function resolveDocumentItemRows(Document $document)
    {
        if ($document->relationLoaded('items') && $document->items->isNotEmpty()) {
            return $document->items;
        }

        $items = $document->items()->get();
        if ($items->isNotEmpty()) {
            return $items;
        }

        return DocumentItem::query()
            ->where('document_id', $document->id)
            ->get();
    }

    public static function mapDocumentItems(Document $document): array
    {
        $rows = self::resolveDocumentItemRows($document);

        if ($rows->isEmpty()) {
            return [];
        }

        return QuotationResource::getTransformItems($rows)
            ->map(function ($row) {
                $item = $row['item'] ?? null;
                if (is_object($item)) {
                    $item = json_decode(json_encode($item), true);
                }

                $description = data_get($item, 'description')
                    ?: data_get($item, 'name')
                    ?: data_get($item, 'full_description')
                    ?: ($row['name_product_pdf'] ?? null);

                if (is_array($description)) {
                    $description = implode(' | ', array_filter($description));
                }

                $row['item'] = $item;
                $row['description'] = $description;
                $row['quantity'] = (float) ($row['quantity'] ?? 0);
                $row['unit_price'] = (float) ($row['unit_price'] ?? 0);
                $row['total'] = (float) ($row['total'] ?? 0);

                return $row;
            })
            ->values()
            ->all();
    }
}
// ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
