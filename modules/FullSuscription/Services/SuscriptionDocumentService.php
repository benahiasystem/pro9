<?php

// ######## INICIO MIGRACIÓN MONEDA VENEZUELA ########

namespace Modules\FullSuscription\Services;

use App\CoreFacturalo\Requests\Inputs\Common\ActionInput;
use App\CoreFacturalo\Requests\Inputs\Common\EstablishmentInput;
use App\CoreFacturalo\Requests\Inputs\Common\PersonInput;
use App\Http\Controllers\Tenant\DocumentController;
use App\Http\Requests\Tenant\DocumentRequest;
use App\Models\Tenant\Company;
use App\Models\Tenant\Item;
use App\Models\Tenant\Series;
use App\Services\SeriesResolver;
use Illuminate\Support\Str;
use App\Services\ExchangeRates\TenantExchangeRateService;
use Modules\FullSuscription\Models\Tenant\UserRelSuscriptionPlan;

trait SuscriptionDocumentService
{
    public function document(UserRelSuscriptionPlan $suscription): array
    {
        // ######## INICIO PARTIDAS DE SUSCRIPCION ########
        // Las altas actuales conservan importes y cantidades; algunos registros antiguos no los tienen.
        $items = (array) $suscription->items;
        $rows = count($items)
            ? collect($items)->map(function ($item) {
                $row = (object) $item;
                if (isset($row->item)) $row->item = (object) $row->item;
                return $row;
            })
            : $suscription->suscription_plan->items;
        $_data = $this->transform($suscription->toArray(), $rows);
        // ######## FIN PARTIDAS DE SUSCRIPCION ########
        $request = new DocumentRequest($_data);
        $response = app(DocumentController::class)->storeFromSubscription($request);

        if (!isset($response['data'])) {
            return [
                'success' => false,
                'message' => $response['message'],
            ];
        }

        // ######## INICIO PERSISTENCIA FISCAL VENEZUELA ########
        // El contrato HKA de crédito sin abono todavía no cubre suscripciones.
        $document = \App\Models\Tenant\Document::findOrFail($response['data']['id']);
        if ($emission = $document->emission()->first()) {
            $emission->update(['response'=>array_replace($emission->response ?? [], ['auto_send_requested'=>false])]);
        }
        return [
            'success' => $response['success'],
            'number'      => $response['data']['number_full'],
            'response'    => 'Factura guardada. La emisión fiscal de suscripciones a crédito requiere validación.',
            'document_id' => $response['data']['id'],
        ];
        // ######## FIN PERSISTENCIA FISCAL VENEZUELA ########
    }

    public function transform(array $values, $get_ids): array
    {
        $company              = Company::active();
        $fiscal_environment         = $company->fiscal_environment;
        $customer             = (object) $values['customer'];
        $identity_document_type_id = $customer->identity_document_type_id;
        $establishment_id     = auth()->user()->establishment_id;
        $document_type_id     = $this->getDocumentType($identity_document_type_id);
        $items                = $this->items($get_ids);

        // ######## INICIO MONEDA DE SUSCRIPCION ########
        $currency = $values['currency_type_id'] ?? 'VES';
        if (!in_array($currency, ['VES', 'USD'], true)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['currency_type_id' => 'La moneda de la suscripción no es válida.']);
        }
        $connection = (new UserRelSuscriptionPlan())->getConnection();
        $currencyQuery = $connection->table('cat_currency_types')
            ->where('id', $currency)->where('active', 1);
        if ($connection->transactionLevel() > 0) $currencyQuery->lockForUpdate();
        if (!$currencyQuery->first()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['currency_type_id' => 'La moneda de la suscripción no está activa.']);
        }
        // ######## FIN MONEDA DE SUSCRIPCION ########

        // Emisión automática (suscripción): siempre se omiten series dedicadas; no hay lectura de grupo/dispositivo.
        $serie = app(SeriesResolver::class)->applyContext(Series::where([
            'establishment_id' => $establishment_id,
            'document_type_id' => $document_type_id,
        ]), null)->first();

        // ######## INICIO API BCV ########
        $service = app(TenantExchangeRateService::class)->exchange(now()->format('Y-m-d'));
        // ######## FIN API BCV ########

        return [
            'actions' => ActionInput::set([
                'type'     => 'invoice',
                'group_id' => ($document_type_id === '01') ? '01' : '02',
            ]),
            'series'                  => $serie->number,
            // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
            // La orden no identifica un cobro verificable ni su destino.
            'payment_condition_id'    => '02',
            // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
            'type'                    => 'invoice',
            'group_id'                => ($document_type_id === '01') ? '01' : '02',
            'establishment_id'        => $establishment_id,
            'establishment'           => EstablishmentInput::set($establishment_id),
            'user_id'                 => auth()->id(),
            'external_id'             => Str::uuid()->toString(),
            'document_type_id'        => $document_type_id,
            'number'                  => '#',
            'fiscal_environment'            => $fiscal_environment,
            'state_type_id'           => '01',
            'date_of_issue'           => now()->format('Y-m-d'),
            'time_of_issue'           => now()->format('H:i:s'),
            'customer_id'             => $customer->id,
            'customer'                => PersonInput::set($customer->id),
            'invoice'                 => [
                'operation_type_id' => '0101',
                'date_of_due'       => now()->format('Y-m-d'),
            ],
            'exchange_rate_sale'      => $service['sale'],
            'currency_type_id'        => $currency,
            'source_module'           => 'FULL_SUSCRIPTION',
            'items'                   => $items,
            'charges'                 => $values['charges'] ?? [],
            'discounts'               => $values['discounts'] ?? [],
            'is_editable'             => true,
            'total_discount'          => $values['total_discount'] ?? 0,
            'total_charge'            => $values['total_charge'] ?? 0,
            'total'                   => $values['total'],
            'total_taxed'             => $values['total_taxed'] ?? 0,
            'total_exonerated'        => $values['total_exonerated'] ?? 0,
            'total_igv'               => $values['total_igv'] ?? 0,
            'total_other_taxes'       => $values['total_other_taxes'] ?? 0,
            'total_base_other_taxes'  => $values['total_base_other_taxes'] ?? 0,
            'total_taxes'             => $values['total_taxes'] ?? 0,
            'total_prepayment'        => $values['total_prepayment'] ?? 0,
            'total_exportation'       => $values['total_exportation'] ?? 0,
            'total_value'             => $values['total_value'] ?? 0,
            'subtotal'                => $values['total'],
            'payments'                => [],
            'fee'                     => [],
            'hotel'                   => null,
            'transport'               => null,
            'legends'                 => [],
        ];
    }

    public function items($items): array
    {
        $_items = [];

        foreach ($items as $row) {
            $item = Item::find($row->item_id);
            $rowItem = $row->item ?? null;

            $_items[] = [
                'item_id'                  => $item->id,
                'item'                     => [
                    'description'              => trim($item->description),
                    'item_type_id'             => $item->item_type_id,
                    'internal_id'              => $item->barcode === 'VARIOUS_ITEM' ? null : $item->internal_id,
                    'item_code'                => trim($item->item_code),
                    'item_code_gs1'            => $item->item_code_gs1,
                    'unit_type_id'             => optional($rowItem)->unit_type_id ?? $item->unit_type_id,
                    'presentation'             => optional($rowItem)->presentation ?? [],
                    'is_set'                   => $item->is_set,
                    'lots'                     => [],
                    'IdLoteSelected'           => false,
                    'model'                    => $item->model,
                    'sanitary'                 => $item->sanitary,
                    'date_of_due'              => !empty($item->date_of_due) ? $item->date_of_due->format('Y-m-d') : null,
                    'has_igv'                  => optional($rowItem)->has_igv ?? true,
                    'unit_price'               => $row->unit_price ?? 0,
                    'purchase_unit_price'      => $item->purchase_unit_price ?? 0,
                    'exchanged_for_points'     => optional($rowItem)->exchanged_for_points ?? false,
                    'used_points_for_exchange' => optional($rowItem)->used_points_for_exchange ?? null,
                ],
                'quantity'                 => $row->quantity,
                'unit_value'               => $row->unit_value,
                'price_type_id'            => $row->price_type_id,
                'unit_price'               => $row->unit_price,
                'affectation_igv_type_id'  => $row->affectation_igv_type_id,
                'total_base_igv'           => $row->total_base_igv,
                // ########## INICIO CAMBIO AFECTACIÓN IVA
                'percentage_igv'           => \App\Support\Venezuela\Localization::taxPercentage(),
                // ######### FIN CAMBIO AFECTACIÓN IVA
                'total_igv'                => $row->total_igv,
                'total_base_other_taxes'   => $row->total_base_other_taxes ?? null,
                'percentage_other_taxes'   => $row->percentage_other_taxes ?? null,
                'total_other_taxes'        => $row->total_other_taxes ?? null,
                'total_taxes'              => $row->total_taxes,
                'total_value'              => $row->total_value,
                'total_charge'             => $row->total_charge ?? null,
                'total_discount'           => $row->total_discount ?? null,
                'total'                    => $row->total,
                'discounts'                => null,
                'charges'                  => null,
                'warehouse_id'             => $row->warehouse_id,
                'additional_information'   => null,
                'name_product_pdf'         => null,
                'update_description'       => null,
                'additional_data'          => null,
            ];
        }

        return $_items;
    }

    public function getDocumentType(string $identity_document_type): string
    {
        // ######## INICIO CAMBIO QUITAR BOLETA ########
        return '01';
        // ######## FIN CAMBIO QUITAR BOLETA ########
    }
}

// ######## FIN MIGRACIÓN MONEDA VENEZUELA ########
