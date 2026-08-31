<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\Quotation;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Tenant\Item;
use App\Models\Tenant\Person;
use App\Models\Tenant\Configuration;
use Modules\Inventory\Models\Warehouse as ModuleWarehouse;
use Carbon\Carbon;

class QuotationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $quotation = Quotation::with(['items', 'payments', 'person'])->find($this->id);
        $quotation->payments = self::getTransformPayments($quotation->payments);
        $items = self::getTransformItems($quotation->items);
        $quotation->setRelation('items', $items);

        $person = Person::with('identity_document_type')->find($this->customer_id);
        $customer = null;
        if ($person) {
            try {
                $customer = $person->getCollectionData();
            } catch (\Throwable $e) {
                $customer = [
                    'id' => $person->id,
                    'description' => trim(($person->number ?: '') . ' - ' . ($person->name ?: '')),
                    'name' => $person->name,
                    'number' => $person->number,
                    'identity_document_type_id' => $person->identity_document_type_id,
                    'identity_document_type_code' => optional($person->identity_document_type)->id,
                    'telephone' => $person->telephone,
                    'email' => $person->email,
                    'address' => $person->address,
                    'addresses' => [],
                    'person_type' => '',
                ];
            }
        } else {
            $customer = [
                'id' => $this->customer_id,
                'description' => 'Cliente #'.$this->customer_id,
                'name' => '',
                'number' => '',
                'identity_document_type_id' => '0',
                'addresses' => [],
                'person_type' => '',
            ];
        }

        $quotationArray = $quotation->toArray();
        $quotationArray['date_of_issue'] = $this->formatDateValue($quotation->date_of_issue);
        $quotationArray['date_of_due'] = $this->formatDateValue($quotation->date_of_due);
        $quotationArray['delivery_date'] = $this->formatDateValue($quotation->delivery_date);
        $quotationArray['discounts'] = $this->normalizeList($quotation->discounts);
        $quotationArray['charges'] = $this->normalizeList($quotation->charges);
        $quotationArray['items'] = collect($items)->values()->all();
        // Snapshot de cliente siempre como array asociativo
        $quotationArray['customer'] = json_decode(json_encode($quotation->customer), true) ?: [];

        // Método de pago legacy "10" (eliminado del catálogo) → Contado
        if (($quotationArray['payment_method_type_id'] ?? null) === '10') {
            $quotationArray['payment_method_type_id'] = '01';
        }

        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'identifier' => $this->identifier,
            'date_of_issue' => $this->formatDateValue($this->date_of_issue),
            'pdf_a4_filename' => url(''). "/print/quotation/{$this->external_id}/a4/{$this->filename}.pdf",
            'print_ticket' => url('')."/print/quotation/{$this->external_id}/ticket",
            'pdf_a4_data' => [
                'filename_only' => $this->filename,
                'extension_only' => 'pdf',
            ],
            'quotation' => $quotationArray,
            'customer' => $customer,
            'message_text' => "Su cotización {$this->number_full} ha sido generado correctamente, " .
                "puede revisarlo en el siguiente enlace: " . url('') . "/print/quotation/{$this->external_id}/".(optional(Configuration::first())->qr_api_pdf_format === 'a4' ? 'a4' : 'ticket'),
            'number_full' => $this->number_full,
            'customer_email' => optional($quotation->person)->email,
            'customer_telephone' => optional($quotation->person)->telephone,
            'customer_id' => $this->customer_id,
        ];
    }

    private function formatDateValue($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            // delivery_date y date_of_due guardan texto libre ("4 dias habiles de
            // fabricacion", "30"), que Carbon no puede parsear. Antes se cortaba a
            // 10 bytes: eso partia un caracter multibyte a la mitad y la respuesta
            // entera reventaba con "Malformed UTF-8 characters".
            return is_string($value) ? $value : null;
        }
    }

    /**
     * @param  mixed  $value
     */
    private function normalizeList($value): array
    {
        if (empty($value)) {
            return [];
        }

        if (is_object($value)) {
            $value = (array) $value;
        }

        return array_values(is_array($value) ? $value : []);
    }

    public static function getTransformPayments($payments)
    {
        return $payments->transform(function ($row) {
            return [
                'id' => $row->id,
                'quotation_id' => $row->quotation_id,
                'date_of_payment' => $row->date_of_payment ? $row->date_of_payment->format('Y-m-d') : null,
                'payment_method_type_id' => $row->payment_method_type_id,
                'has_card' => $row->has_card,
                'card_brand_id' => $row->card_brand_id,
                'reference' => $row->reference,
                'payment' => $row->payment,
                'payment_method_type' => $row->payment_method_type,
                'payment_destination_id' => ($row->global_payment)
                    ? ($row->global_payment->type_record == 'cash' ? 'cash' : $row->global_payment->destination_id)
                    : null,
            ];
        });
    }

    public static function getTransformItems($items)
    {
        $establishment_id = optional(auth()->user())->establishment_id;
        $warehouse = $establishment_id
            ? ModuleWarehouse::where('establishment_id', $establishment_id)->first()
            : null;
        $warehouse_id = $warehouse ? $warehouse->id : null;

        return $items->map(function ($row) use ($warehouse_id) {
            $item = self::getTransformItem($row->item, $warehouse_id, $row->item_id, $row->unit_price);

            $rowArray = $row->toArray();
            $rowArray['item'] = $item;
            $rowArray['discounts'] = self::listFromMixed($row->discounts);
            $rowArray['charges'] = self::listFromMixed($row->charges);
            $rowArray['attributes'] = self::listFromMixed($row->attributes);
            $rowArray['quantity'] = (float) $row->quantity;
            $rowArray['unit_price'] = (float) $row->unit_price;
            $rowArray['unit_value'] = (float) $row->unit_value;
            $rowArray['total'] = (float) $row->total;
            // Valor ingresado en el form (sin IGV si has_igv=false); evita NaN al reeditar.
            $itemHasIgv = !isset($item->has_igv) || (bool) $item->has_igv;
            $rowArray['input_unit_price_value'] = $itemHasIgv
                ? (float) $row->unit_price
                : (float) $row->unit_value;
            $rowArray['warehouse_id'] = $row->warehouse_id ?: $warehouse_id;

            if (empty($rowArray['affectation_igv_type']) && ! empty($rowArray['affectation_igv_type_id'])) {
                $rowArray['affectation_igv_type'] = [
                    'id' => $rowArray['affectation_igv_type_id'],
                    'description' => $rowArray['affectation_igv_type_id'],
                    'free' => 0,
                    'exportation' => 0,
                    'active' => 1,
                ];
            }

            return $rowArray;
        })->values();
    }

    /**
     * @param  mixed  $value
     */
    private static function listFromMixed($value): array
    {
        if (empty($value)) {
            return [];
        }
        if (is_object($value)) {
            $value = (array) $value;
        }

        return array_values(is_array($value) ? $value : []);
    }

    public static function getTransformItem($item, $warehouse_id, $fallbackItemId = null, $fallbackUnitPrice = null)
    {
        if (is_array($item)) {
            $item = (object) $item;
        }
        if (! is_object($item)) {
            $item = (object) [];
        }

        $itemId = $item->id ?? $item->item_id ?? $fallbackItemId;
        $resource = $itemId ? Item::with(['unit_type', 'brand', 'category'])->find($itemId) : null;

        // Hidrata desde catálogo para que el form tenga la misma forma que cotizaciones admin
        if ($resource) {
            $catalog = [
                'id' => $resource->id,
                'item_id' => $resource->id,
                'description' => $resource->description,
                'name' => $resource->name,
                'internal_id' => $resource->internal_id,
                'unit_type_id' => $resource->unit_type_id,
                'unit_type' => $resource->unit_type ? [
                    'id' => $resource->unit_type->id,
                    'description' => $resource->unit_type->description,
                ] : ($item->unit_type ?? null),
                'currency_type_id' => $resource->currency_type_id ?: 'PEN',
                'sale_unit_price' => (float) $resource->sale_unit_price,
                'purchase_unit_price' => (float) ($resource->purchase_unit_price ?? 0),
                'purchase_unit_value' => (float) ($resource->purchase_unit_value ?? 0),
                'has_igv' => (bool) ($resource->has_igv ?? true),
                'purchase_has_igv' => (bool) ($resource->purchase_has_igv ?? true),
                'has_isc' => (bool) ($resource->has_isc ?? false),
                'is_set' => (int) ($resource->is_set ?? 0),
                'series_enabled' => (bool) ($resource->series_enabled ?? false),
                'lots_enabled' => (bool) ($resource->lots_enabled ?? false),
                'has_plastic_bag_taxes' => (bool) ($resource->has_plastic_bag_taxes ?? false),
                'amount_plastic_bag_taxes' => (float) ($resource->amount_plastic_bag_taxes ?? 0),
                'sale_affectation_igv_type_id' => $resource->sale_affectation_igv_type_id,
                'brand' => $resource->brand->name ?? ($item->brand ?? null),
                'model' => $resource->model,
                'lots' => [],
                'presentation' => null,
            ];

            foreach ($catalog as $key => $value) {
                if (! isset($item->{$key}) || $item->{$key} === null || $item->{$key} === '') {
                    $item->{$key} = $value;
                }
            }

            $item->series_enabled = (bool) $resource->series_enabled;
        } else {
            $item->series_enabled = (bool) ($item->series_enabled ?? false);
            $item->lots = is_array($item->lots ?? null) ? $item->lots : [];
        }

        $item->id = $itemId;
        if (! isset($item->item_id)) {
            $item->item_id = $itemId;
        }

        // Cotizaciones ecommerce: unit_price suele faltar; usar sale/suggested/columna
        $unitPrice = isset($item->unit_price) ? (float) $item->unit_price : 0.0;
        if ($unitPrice <= 0) {
            $unitPrice = (float) (
                $item->sale_unit_price
                ?? $item->suggested_unit_price
                ?? $fallbackUnitPrice
                ?? 0
            );
        }
        $item->unit_price = $unitPrice;
        if (! isset($item->sale_unit_price) || (float) $item->sale_unit_price <= 0) {
            $item->sale_unit_price = $unitPrice;
        }
        if (empty($item->currency_type_id)) {
            $item->currency_type_id = 'PEN';
        }
        if (! property_exists($item, 'presentation')) {
            $item->presentation = null;
        }
        if (! isset($item->has_igv)) {
            $item->has_igv = true;
        }
        if (! isset($item->purchase_unit_price)) {
            $item->purchase_unit_price = 0;
        }

        return $item;
    }
}
