<?php

namespace App\CoreFacturalo\Requests\Inputs;

use App\CoreFacturalo\Requests\Inputs\Common\ActionInput;
use App\CoreFacturalo\Requests\Inputs\Common\EstablishmentInput;
use App\CoreFacturalo\Requests\Inputs\Common\LegendInput;
use App\CoreFacturalo\Requests\Inputs\Common\PersonInput;
use App\CoreFacturalo\Requests\Inputs\Transform\DocumentWebTransform;
use App\Models\Tenant\Catalogs\DocumentType;
use App\Models\Tenant\Company;
use App\Models\Tenant\Document;
use App\Models\Tenant\Item;
use Illuminate\Support\Str;
use App\Services\SalesDocumentTypePolicy;
use App\Services\SalesCustomerIdentityPolicy;
use Modules\Offline\Models\OfflineConfiguration;
use App\Models\Tenant\Configuration;


class DocumentInput
{
    public static function set($inputs)
    {
        $inputs = \App\Support\Venezuela\RetiredDetractionFields::discard($inputs);
        $document_type_id = $inputs['document_type_id'];
        SalesDocumentTypePolicy::assertNewFiscalDocumentAllowed($document_type_id);
        // ######## INICIO POLITICA IDENTIDAD ACTIVA EN VENTAS ########
        SalesCustomerIdentityPolicy::assertCustomerAllowed($inputs['customer_id'] ?? null);
        // ######## FIN POLITICA IDENTIDAD ACTIVA EN VENTAS ########
        $series = $inputs['series'];
        $number = $inputs['number'];

        $company = Company::active();
        $fiscal_environment = $company->fiscal_environment;

        $offline_configuration = OfflineConfiguration::firstOrFail();
        // $number = Functions::newNumber($fiscal_environment, $document_type_id, $series, $number, Document::class);
        $configuration = Configuration::getColumnsForDocuments();

        if ($number !== '#') {
            Functions::validateUniqueDocument($fiscal_environment, $document_type_id, $series, $number, Document::class);
        }

        // $filename = Functions::filename($company, $document_type_id, $series, $number);
        $validate_itinerant = false;
        if(isset($inputs['itinerant']) ){
            $validate_itinerant = $inputs['itinerant']['id'] == 1 ? false : true;
        }

        $establishment = EstablishmentInput::set($inputs['establishment_id']);
        $customer = PersonInput::set($inputs['customer_id'], isset($inputs['customer_address_id']) ? $inputs['customer_address_id'] : null,
            $validate_itinerant ? $inputs['itinerant']['address'] : null);


        if (in_array($document_type_id, ['01'])) {
            $array_partial = self::invoice($inputs);
            $invoice = $array_partial['invoice'];
            $note = null;
        } else {
            $array_partial = self::note($inputs);
            $note = $array_partial['note'];
            $invoice = null;
        }

        $inputs['type'] = $array_partial['type'];
        $inputs['group_id'] = $array_partial['group_id'];

        //set o convert json

        if ($offline_configuration->is_client) {
            $exist_data_json = Functions::valueKeyInArray($inputs, 'data_json');
            $data_json = ($exist_data_json) ? $exist_data_json : DocumentWebTransform::transform($inputs);
        } else {
            $data_json = Functions::valueKeyInArray($inputs, 'data_json');
        }

        $items = self::items($inputs, $configuration);

        // se registran datos para identificar si el documento fue utilizado para sistema por puntos
        $point_system_data = self::getPointSystemData($inputs, $configuration);

        return [
            // ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
            'operation_key' => $inputs['operation_key'] ?? null,
            'fiscal_profile_id' => $inputs['fiscal_profile_id'] ?? null,
            'fiscal_channel' => $inputs['fiscal_channel'] ?? null,
            'fiscal_group_id' => $inputs['fiscal_group_id'] ?? null,
            'fiscal_fingerprint' => $inputs['fiscal_fingerprint'] ?? null,
            // ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
            'type' => $inputs['type'],
            'group_id' => $inputs['group_id'],
            'user_id' => auth()->id(),
            'external_id' => Str::uuid()->toString(),
            'establishment_id' => $inputs['establishment_id'],
            'establishment' => $establishment,
            'fiscal_environment' => $fiscal_environment,
            'state_type_id' => '01',
            'ubl_version' => '2.1',
            'filename' => '',//$filename,
            'document_type_id' => $document_type_id,
            'series' => $series,
            'number' => $number,
            'date_of_issue' => $inputs['date_of_issue'],
            'time_of_issue' => $inputs['time_of_issue'],
            'customer_id' => $inputs['customer_id'],
            'seller_id' => Functions::valueKeyInArray($inputs, 'seller_id'),
            'customer' => $customer,
            'currency_type_id' => $inputs['currency_type_id'],
            'purchase_order' => Functions::valueKeyInArray($inputs, 'purchase_order'),
            'folio' => Functions::valueKeyInArray($inputs, 'folio'),
            'quotation_id' => Functions::valueKeyInArray($inputs, 'quotation_id'),
            'sale_note_id' => Functions::valueKeyInArray($inputs, 'sale_note_id'),
            'order_note_id' => Functions::valueKeyInArray($inputs, 'order_note_id'),
            'technical_service_id' => Functions::valueKeyInArray($inputs, 'technical_service_id'),
            'dispatch_id' => Functions::valueKeyInArray($inputs, 'dispatch_id'),
            'exchange_rate_sale' => $inputs['exchange_rate_sale'],
            'total_prepayment' => Functions::valueKeyInArray($inputs, 'total_prepayment', 0),
            'total_discount' => Functions::valueKeyInArray($inputs, 'total_discount', 0),
            'total_charge' => Functions::valueKeyInArray($inputs, 'total_charge', 0),
            'total_exportation' => Functions::valueKeyInArray($inputs, 'total_exportation', 0),
            'total_free' => Functions::valueKeyInArray($inputs, 'total_free', 0),
            'total_taxed' => $inputs['total_taxed'],
            'total_unaffected' => Functions::valueKeyInArray($inputs, 'total_unaffected', 0),
            'total_exonerated' => Functions::valueKeyInArray($inputs, 'total_exonerated', 0),
            'total_igv' => $inputs['total_igv'],
            'total_igv_free' => Functions::valueKeyInArray($inputs, 'total_igv_free', 0),
            'total_base_other_taxes' => Functions::valueKeyInArray($inputs, 'total_base_other_taxes', 0),
            'total_other_taxes' => Functions::valueKeyInArray($inputs, 'total_other_taxes', 0),
            'total_taxes' => $inputs['total_taxes'],
            'total_value' => $inputs['total_value'],
            'subtotal' => (Functions::valueKeyInArray($inputs, 'subtotal')) ? $inputs['subtotal'] : $inputs['total'],
            'total' => $inputs['total'],
            'has_prepayment' => Functions::valueKeyInArray($inputs, 'has_prepayment', 0),
            'affectation_type_prepayment' => Functions::valueKeyInArray($inputs, 'affectation_type_prepayment'),
            'was_deducted_prepayment' => Functions::valueKeyInArray($inputs, 'was_deducted_prepayment', 0),
            'pending_amount_prepayment' => Functions::valueKeyInArray($inputs, 'pending_amount_prepayment', 0),
            'items' => $items,
            'charges' => self::charges($inputs),
            'discounts' => self::discounts($inputs),
            'prepayments' => self::prepayments($inputs),
            'guides' => self::guides($inputs),
            'related' => self::related($inputs),
            'perception' => self::perception($inputs),
            'retention' => self::retention($inputs),
            'invoice' => $invoice,
            'note' => $note,
            'hotel' => self::hotel($inputs),
            'transport' => self::transport($inputs),
            'additional_information' => Functions::valueKeyInArray($inputs, 'additional_information'),
            'additional_data' => Functions::valueKeyInArray($inputs, 'additional_data'),
            'plate_number' => Functions::valueKeyInArray($inputs, 'plate_number'),
            'legends' => LegendInput::set($inputs),
            'actions' => ActionInput::set($inputs),
            'data_json' => $data_json,
            'payments' => Functions::valueKeyInArray($inputs, 'payments', []),
            'payment_method_type_id' => Functions::valueKeyInArray($inputs, 'payment_method_type_id'),
            'reference_data' => Functions::valueKeyInArray($inputs, 'reference_data'),
            'terms_condition' => self::termsCondition($inputs),
            'dispatches_relateds' => $inputs['dispatches_relateds'] ?? null,
            'sale_notes_relateds' => $inputs['sale_notes_relateds'] ?? null,
            'payment_condition_id' => key_exists('payment_condition_id', $inputs) ? $inputs['payment_condition_id'] : '01',
            'fee' => Functions::valueKeyInArray($inputs, 'fee', []),
            'is_editable' => true,
            'total_pending_payment' => Functions::valueKeyInArray($inputs, 'total_pending_payment', 0),
            'tip' => self::tip($inputs, $fiscal_environment),
            'point_system' => $point_system_data['point_system'],
            'point_system_data' => $point_system_data['point_system_data'],
            'agent_id' => Functions::valueKeyInArray($inputs, 'agent_id'),
            'dispatch_ticket_pdf' => Functions::valueKeyInArray($inputs, 'dispatch_ticket_pdf', false),
            'hotel_data_persons' => Functions::valueKeyInArray($inputs, 'hotel_data_persons'),
            'source_module' => Functions::valueKeyInArray($inputs, 'source_module'),
            'hotel_rent_id' => Functions::valueKeyInArray($inputs, 'hotel_rent_id'),
            'itinerant' => Functions::valueKeyInArray($inputs, 'itinerant'),
            'is_itinerant' =>Functions::valueKeyInArray($inputs, 'is_itinerant') ,
            'consigned_id' => Functions::valueKeyInArray($inputs, 'consigned_id'),
            'consigned_address' => Functions::valueKeyInArray($inputs, 'consigned_address'),
            'consigned_ubigeo' => Functions::valueKeyInArray($inputs, 'consigned_ubigeo'),
            'custom_fields_data' => Functions::valueKeyInArray($inputs, 'custom_fields_data'),
        ];
    }

    private static function termsCondition($inputs)
    {
        $terms = $inputs['terms_condition'] ?? '';

        if (trim(strip_tags(html_entity_decode($terms))) !== '') {
            return $terms;
        }

        if (array_key_exists('show_terms_condition', $inputs)
            && !filter_var($inputs['show_terms_condition'], FILTER_VALIDATE_BOOLEAN)) {
            return '';
        }

        $configuration = Configuration::select('terms_condition_sale')->first();

        return $configuration->terms_condition_sale ?? '';
    }


    public static function items($inputs, $configuration = null)
    {
        $register_series_invoice_xml = $configuration->register_series_invoice_xml ?? false;

        if (array_key_exists('items', $inputs)) {
            $items = [];
            foreach ($inputs['items'] as $row) {
                $item = Item::query()->find($row['item_id']);
                /** @var Item $item */

                $items_attributes = self::attributes($row);

                if($register_series_invoice_xml && in_array($inputs['document_type_id'], ['01']))
                {
                    self::registerSeriesInvoiceXml($items_attributes, $row);
                }

                $arayItem = [
                    'item_id' => $item->id,
                    'item' => [
                        'description' => trim($item->description),
                        'description' => trim($item->description == 'REPLACE DESCRIPTION' || $item->barcode == 'VARIOUS_ITEM' ? $row['item']['description'] : trim($item->description)),
                        'item_type_id' => $item->item_type_id,
                        'internal_id' => $item->barcode == 'VARIOUS_ITEM' ? null : $item->internal_id,
                        'item_code' => trim($item->item_code),
                        'item_code_gs1' => $item->item_code_gs1,
                        'unit_type_id' => (key_exists('item', $row)) ? $row['item']['unit_type_id'] : $item->unit_type_id,
                        'presentation' => (key_exists('item', $row)) ? (isset($row['item']['presentation']) ? $row['item']['presentation'] : []) : [],
                        'is_set' => $item->is_set,
                        'lots' => self::lots($row),
                        'IdLoteSelected' => (isset($row['IdLoteSelected']) ? $row['IdLoteSelected'] : null),
                        'model' => $item->model,
                        'sanitary' => $item->sanitary,
                        'cod_digemid' => $item->cod_digemid,
                        'date_of_due' => (!empty($item->date_of_due)) ? $item->date_of_due->format('Y-m-d') : null,
                        'has_igv' => $row['item']['has_igv'] ?? true,
                        'unit_price' => $row['item']['unit_price'] ?? 0,
                        'purchase_unit_price' => $item->purchase_unit_price ?? 0,
                        'exchanged_for_points' => $row['item']['exchanged_for_points'] ?? false,
                        'used_points_for_exchange' => $row['item']['used_points_for_exchange'] ?? null,
                        'currency_type_id' => $row['item']['currency_type_id'] ?? null
                    ],
                    'quantity' => $row['quantity'],
                    'unit_value' => $row['unit_value'],
                    'price_type_id' => $row['price_type_id'],
                    'unit_price' => $row['unit_price'],
                    'affectation_igv_type_id' => $row['affectation_igv_type_id'],
                    'total_base_igv' => $row['total_base_igv'],
                    'percentage_igv' => $row['percentage_igv'],
                    'total_igv' => $row['total_igv'],
                    'total_base_other_taxes' => Functions::valueKeyInArray($row, 'total_base_other_taxes', 0),
                    'percentage_other_taxes' => Functions::valueKeyInArray($row, 'percentage_other_taxes', 0),
                    'total_other_taxes' => Functions::valueKeyInArray($row, 'total_other_taxes', 0),
                    'total_taxes' => $row['total_taxes'],
                    'total_value' => $row['total_value'],
                    'total_charge' => Functions::valueKeyInArray($row, 'total_charge', 0),
                    'total_discount' => Functions::valueKeyInArray($row, 'total_discount', 0),
                    'total' => $row['total'],
                    'attributes' => $items_attributes,
                    // 'attributes' => self::attributes($row),
                    'discounts' => self::discounts($row),
                    'charges' => self::charges($row),
                    'warehouse_id' => Functions::valueKeyInArray($row, 'warehouse_id'),
                    'additional_information' => Functions::valueKeyInArray($row, 'additional_information'),
                    'name_product_pdf' => Functions::valueKeyInArray($row, 'name_product_pdf'),
                    'update_description' => Functions::valueKeyInArray($row, 'update_description', false),
                    'additional_data' => Functions::valueKeyInArray($row, 'additional_data'),
//                    'additional_data' => key_exists('additional_data', $row)?$row['additional_data']:null,
                ];
//                dd($arayItem);
                Item::SaveExtraDataToRequest($arayItem,$row);
                $items[] = $arayItem;
            }
            return $items;
        }
        return null;
    }


    private static function lots($row)
    {
        if (isset($row['item']['lots'])) {
            return $row['item']['lots'];
        } else if (isset($row['lots'])) {
            return $row['lots'];
        } else {
            return [];
        }
    }

    private static function attributes($inputs)
    {
        if (array_key_exists('attributes', $inputs)) {
            if ($inputs['attributes']) {
                $attributes = [];
                foreach ($inputs['attributes'] as $row) {
                    $attributes[] = [
                        // 'attribute_type_id' => $row['attribute_type_id'] ?? null,
                        // 'description' => $row['description'] ?? null,
                        // 'value' => $row['value'] ?? null,
                        // 'start_date' =>  $row['start_date'] ?? null,
                        // 'end_date' => $row['end_date'] ?? null,
                        // 'duration' =>  $row['duration'] ?? null,
                        'attribute_type_id' => $row['attribute_type_id'],
                        'description' => $row['description'],
                        'value' => $row['value'],
                        'start_date' =>  $row['start_date'],
                        'end_date' => $row['end_date'],
                        'duration' =>  $row['duration'],
                    ];
                }
                return $attributes;
            }
        }
        return null;
    }


    /**
     *
     * Registrar series como atributos (5019) para vehiculos
     *
     * @param  array $items_attributes
     * @param  array $row
     * @return void
     */
    public static function registerSeriesInvoiceXml(&$items_attributes, $row)
    {
        $series = self::lots($row);

        if(!empty($series))
        {
            $series_to_attributes = self::getVehicleSeriesToAttributes($series);

            if(is_null($items_attributes))
            {
                $items_attributes = $series_to_attributes;
            }
            else if(is_array($items_attributes))
            {
                $items_attributes = array_merge($items_attributes, $series_to_attributes);
            }
        }
    }


    /**
     *
     * Generar arreglo de atributos en base a las series - Vehiculos
     *
     * @param  array $series
     * @return array
     */
    private static function getVehicleSeriesToAttributes($series)
    {
        $attributes = [];
        $attribute_type_id = '5019';
        $description = 'Serie/Chasis';

        foreach ($series as $serie)
        {
            $attributes [] = [
                'attribute_type_id' => $attribute_type_id,
                'description' => $description,
                'value' => $serie['series'],
                'start_date' =>  null,
                'end_date' => null,
                'duration' =>  null,
            ];
        }

        return $attributes;
    }


    private static function charges($inputs)
    {
        if (array_key_exists('charges', $inputs)) {
            if ($inputs['charges']) {
                $charges = [];
                foreach ($inputs['charges'] as $row) {
                    $charge_type_id = $row['charge_type_id'];
                    $description = $row['description'];
                    $factor = $row['factor'];
                    $amount = $row['amount'];
                    $base = $row['base'];

                    $charges[] = [
                        'charge_type_id' => $charge_type_id,
                        'description' => $description,
                        'factor' => $factor,
                        'amount' => $amount,
                        'base' => $base,
                    ];
                }
                return $charges;
            }
        }
        return null;
    }

    private static function discounts($inputs)
    {
        if (array_key_exists('discounts', $inputs)) {
            if ($inputs['discounts']) {
                $discounts = [];
                foreach ($inputs['discounts'] as $row) {
                    $discount_type_id = $row['discount_type_id'];
                    $description = $row['description'];
                    $factor = $row['factor'];
                    $amount = $row['amount'];
                    $base = $row['base'];
                    $is_amount = $row['is_amount'] ?? null; //registra si el descuento fue por monto o porcentaje
                    // ########## INICIO CAMBIO IGV A IVA
                    $amount_without_rounded = $row['amount_without_rounded'] ?? null; // monto sin redondear para cálculos posteriores, principalmente para descuentos que afectan base imponible del IVA
                    // ######### FIN CAMBIO IGV A IVA

                    $discounts[] = [
                        'discount_type_id' => $discount_type_id,
                        'amount_without_rounded'  => $amount_without_rounded,
                        'from_global_distribution' => $row['from_global_distribution'] ?? null, // para identificar si el descuento viene de una distribución global o es un descuento directo del item
                        'description' => $description,
                        'factor' => $factor,
                        'amount' => $amount,
                        'base' => $base,
                        'is_amount' => $is_amount,
                    ];
                }
                return $discounts;
            }
        }
        return null;
    }

    private static function prepayments($inputs)
    {
        if (array_key_exists('prepayments', $inputs)) {
            if ($inputs['prepayments']) {
                $prepayments = [];
                foreach ($inputs['prepayments'] as $row) {
                    $number = $row['number'];
                    $document_type_id = $row['document_type_id'];
                    $amount = $row['amount'];
                    $total = $row['total'];

                    $prepayments[] = [
                        'number' => $number,
                        'document_type_id' => $document_type_id,
                        'amount' => $amount,
                        'total' => $total
                    ];
                }
                return $prepayments;
            }
        }
        return null;
    }

    private static function guides($inputs)
    {
        if (array_key_exists('guides', $inputs)) {
            if ($inputs['guides']) {
                $guides = [];
                foreach ($inputs['guides'] as $row) {
                    $number = $row['number'];
                    $document_type_id = $row['document_type_id'];
                    $guides[] = [
                        'number' => $number,
                        'document_type_id' => $document_type_id,
                        'document_type_description' => ucfirst(mb_strtolower(DocumentType::find($document_type_id)->description)),
                    ];
                }
                return $guides;
            }
        }
        return null;
    }

    private static function related($inputs)
    {
        if (array_key_exists('related', $inputs)) {
            if ($inputs['related']) {
                $related = [];
                foreach ($inputs['related'] as $row) {
                    $number = $row['number'];
                    $document_type_id = $row['document_type_id'];
                    $amount = $row['amount'];

                    $related[] = [
                        'number' => $number,
                        'document_type_id' => $document_type_id,
                        'amount' => $amount
                    ];
                }
                return $related;
            }
        }
        return null;
    }

    private static function perception($inputs)
    {
        if (array_key_exists('perception', $inputs)) {
            if ($inputs['perception']) {
                $perception = $inputs['perception'];
                $code = $perception['code'];
                $percentage = $perception['percentage'];
                $amount = $perception['amount'];
                $base = $perception['base'];

                return [
                    'code' => $code,
                    'percentage' => $percentage,
                    'amount' => $amount,
                    'base' => $base,
                ];
            }
        }
        return null;
    }

    private static function retention($inputs)
    {

        if (array_key_exists('retention', $inputs)) {

            if ($inputs['retention']) {

                $retention = $inputs['retention'];
                $code = $retention['code'];
                $percentage = $retention['percentage'];
                $amount = $retention['amount'];
                $base = $retention['base'];
                $currency_type_id = $retention['currency_type_id'];
                $exchange_rate = $retention['exchange_rate'];
                $amount_pen = $retention['amount_pen'];
                $amount_usd = $retention['amount_usd'];
                $guarantee_fund = isset($retention['guarantee_fund']) ? $retention['guarantee_fund'] : 0;

                return [
                    'code' => $code,
                    'percentage' => $percentage,
                    'amount' => $amount,
                    'base' => $base,
                    'currency_type_id' => $currency_type_id,
                    'exchange_rate' => $exchange_rate,
                    'amount_pen' => $amount_pen,
                    'amount_usd' => $amount_usd,
                    'voucher_date_of_issue' => null,
                    'voucher_number' => null,
                    'voucher_amount' => null,
                    'voucher_filename' => null,
                    'guarantee_fund' => $guarantee_fund
                ];
            }
        }

        return null;
    }


    private static function hotel($inputs)
    {
        // dd($inputs);
        return key_exists('hotel', $inputs)?$inputs['hotel']:null;
    }

    private static function transport($inputs)
    {
        // dd($inputs);
        if (array_key_exists('transport', $inputs)) {

            return $inputs['transport'];
        }

        return [];
    }

    private static function invoice($inputs)
    {
        $operation_type_id = $inputs['operation_type_id'];
        $date_of_due = $inputs['date_of_due'];

        return [
            'type' => 'invoice',
            'group_id' => '01',
            'invoice' => [
                'operation_type_id' => $operation_type_id,
                'date_of_due' => $date_of_due,
            ]
        ];
    }

    private static function note($inputs)
    {
        $document_type_id = $inputs['document_type_id'];
        $note_credit_or_debit_type_id = $inputs['note_credit_or_debit_type_id'];
        $note_description = $inputs['note_description'];
        $affected_document_id = $inputs['affected_document_id'];

        $data_affected_document = Functions::valueKeyInArray($inputs, 'data_affected_document');

        $type = ($document_type_id === '07') ? 'credit' : 'debit';

        if (!$data_affected_document) {

            $affected_document = Document::findOrFail($affected_document_id);
            SalesDocumentTypePolicy::assertAllowedForFlow($affected_document->document_type_id, ['01']);
            $affected_document_id = $affected_document->id;

        } else {

            $affected_document_id = null;
            SalesDocumentTypePolicy::assertAllowedForFlow($data_affected_document['document_type_id'] ?? null, ['01']);

        }


        return [
            'type' => $type,
            'group_id' => '01',
            'note' => [
                'note_type' => $type,
                'note_credit_type_id' => ($type === 'credit') ? $note_credit_or_debit_type_id : null,
                'note_debit_type_id' => ($type === 'debit') ? $note_credit_or_debit_type_id : null,
                'note_description' => $note_description,
                'affected_document_id' => $affected_document_id,
                'data_affected_document' => $data_affected_document
            ]
        ];
    }


    /**
     *
     * Retorna datos para registro de propina
     *
     * Usado en:
     * DocumentInput
     * TipTrait
     *
     * @param  array $inputs
     * @param  string $fiscal_environment
     * @return array
     */
    public static function tip($inputs, $fiscal_environment)
    {
        $worker_full_name_tips = Functions::valueKeyInArray($inputs, 'worker_full_name_tips');
        $total_tips = Functions::valueKeyInArray($inputs, 'total_tips', 0);

        if ($worker_full_name_tips && $total_tips > 0)
        {
            return [
                'date' => date('Y-m-d'),
                'worker_full_name' => $worker_full_name_tips,
                'total' => $total_tips,
                'fiscal_environment' => $fiscal_environment,
                'origin_date_of_issue' => $inputs['date_of_issue'],
            ];
        }

        return null;
    }


    /**
     *
     * Configuración de sistema por puntos
     *
     * @param  array $inputs
     * @param  Configuration $configuration
     * @return array
     */
    public static function getPointSystemData($inputs, $configuration)
    {
        $data = [
            'point_system' => false,
            'point_system_data' => null
        ];

        if(self::isDocumentInvoice($inputs['document_type_id']) && $configuration->enabled_point_system)
        {
            $data = [
                'point_system' => $configuration->enabled_point_system,
                'point_system_data' => [
                    'point_system_sale_amount' => $configuration->point_system_sale_amount,
                    'quantity_of_points' => $configuration->quantity_of_points,
                    'round_points_of_sale' => $configuration->round_points_of_sale,
                ]
            ];
        }

        return $data;
    }


    /**
     * Determina si es Factura
     *
     * @param  string $document_type_id
     * @return bool
     */
    public static function isDocumentInvoice($document_type_id)
    {
        return in_array($document_type_id, ['01'], true);
    }
}
