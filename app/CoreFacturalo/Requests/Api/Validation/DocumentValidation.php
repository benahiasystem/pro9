<?php

namespace App\CoreFacturalo\Requests\Api\Validation;

use App\Models\Tenant\Establishment;
use App\Models\Tenant\User;
use App\Services\SalesDocumentTypePolicy;
use App\CoreFacturalo\Requests\Inputs\Common\EstablishmentInput;
use App\Services\SalesCustomerIdentityPolicy;

class DocumentValidation
{
    public static function validation($inputs) {
        // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
        $currency = $inputs['currency_type_id'] ?? null;
        if (!in_array($currency, ['VES','USD'], true)
            || !\Illuminate\Support\Facades\DB::connection('tenant')->table('cat_currency_types')->where('id', $currency)->where('active', 1)->exists()) {
            \App\Services\Payments\Classification::error('currency_type_id', 'Seleccione una moneda documental activa y admitida.');
        }
        // ######## FIN MONEDA DEL MEDIO DE COBRO ########
        \App\Services\ExportInvoicePolicy::assertInput($inputs, false);
        \App\Services\ThirdPartyInvoicePolicy::assertInput($inputs, false);
        // ########## INICIO CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
        SalesDocumentTypePolicy::assertNewFiscalDocumentAllowed($inputs['document_type_id'] ?? null);
        // ######### FIN CAMBIO SOLO FACTURAS Y NOTAS DE VENTA

        // ######## INICIO POLITICA IDENTIDAD ACTIVA EN VENTAS ########
        SalesCustomerIdentityPolicy::assertIdentityTypeAllowed(
            data_get($inputs, 'customer.identity_document_type_id')
        );
        // ######## FIN POLITICA IDENTIDAD ACTIVA EN VENTAS ########

        // Tienda / invitado: auth() puede ser null (pago ecommerce sin sesión admin).
        $authUser = auth()->user();
        if ($authUser && ! empty($authUser->establishment_id)) {
            $inputs['establishment_id'] = $authUser->establishment_id;
        } elseif (empty($inputs['establishment_id'])) {
            $inputs['establishment_id'] = optional(
                User::query()->whereNotNull('establishment_id')->orderBy('id')->first()
            )->establishment_id
                ?? optional(Establishment::query()->orderBy('id')->first())->id;
        }
        //unset($inputs['establishment']);

        if (in_array($inputs['document_type_id'], ['01', '07', '08'], true)) {
            EstablishmentInput::validatedId($inputs['establishment_id'] ?? null);
        }
        $inputs['series'] = Functions::validateSeries($inputs)->number;

        if (in_array($inputs['document_type_id'], ['07', '08'])) {
            validator($inputs, ['note_creation_key' => 'required|uuid'])->validate();

            if($inputs['affected_document_external_id']){
                $document = Functions::findAffectedDocumentByExternalId($inputs['affected_document_external_id']);
                $inputs['affected_document_id'] = $document->id;
                $inputs['data_affected_document'] = null;

            }else{
                //validar campos json doc afectado
                $inputs['affected_document_id'] = null;

            }

            unset($inputs['affected_document_external_id']);
            \App\Services\ExportInvoicePolicy::assertInput($inputs, false);
            \App\Services\ThirdPartyInvoicePolicy::assertInput($inputs, false);
        }

        $inputs['customer_id'] = Functions::person($inputs['customer'], 'customers');
        unset($inputs['customer']);

        // Full annulments derive their lines from the locked invoice, never from request products.
        if ($inputs['document_type_id'] === '07' && ($inputs['note_credit_or_debit_type_id'] ?? null) === '01') {
            $inputs['items'] = [];
        } else {
            $inputs['items'] = self::items($inputs['items'], $inputs['exchange_rate_sale'] ?? null, $inputs['document_type_id'], $inputs['note_credit_or_debit_type_id'] ?? null);
        }

        Functions::DNI($inputs);
        Functions::identityDocumentTypeInvoice($inputs);


        Functions::validateDateOfIssue($inputs);

        return $inputs;
    }

    private static function items($inputs, $exchangeRate, $documentType, $noteReason) {
        foreach ($inputs as &$row) {
            if ($documentType === '07' && empty($row['affected_document_item_id'])) {
                \App\Services\Fiscal\FiscalAmounts::error('items.affected_document_item_id', 'Identifique la partida de la factura original.');
            }
            if (in_array($documentType, ['07', '08'], true) && array_key_exists('affected_document_item_id', $row) && $row['affected_document_item_id'] !== null) {
                $id = \App\Database\IntegerTransport::positiveKey($row['affected_document_item_id']);
                if ($id === null) \App\Services\Fiscal\FiscalAmounts::error('items.affected_document_item_id', 'Identifique una partida válida de la factura original.');
                $row['affected_document_item_id'] = $id;
            } elseif ($documentType === '08') {
                if ($noteReason === '02') \App\Services\Fiscal\FiscalAmounts::error('items.affected_document_item_id', 'Identifique la partida de la factura original.');
                $code = $row['internal_id'] ?? null;
                $items = is_string($code) && $code !== '' ? \App\Models\Tenant\Item::where('internal_id', $code)->limit(2)->get() : collect();
                if ($items->count() !== 1 || $items->first()->unit_type_id !== 'SERV') {
                    \App\Services\Fiscal\FiscalAmounts::error('items', 'Seleccione un servicio existente y sin código duplicado para el cargo de la nota de débito.');
                }
                $row['item_id'] = $items->first()->id;
            } else {
                $row['item_id'] = Functions::item($row, $exchangeRate);
            }
            unset($row['internal_id'], $row['description']);
            unset($row['item_type_id'], $row['item_code']);
            unset($row['item_code_gs1'], $row['unit_type_id']);
            unset($row['currency_type_id']);
        }

        return $inputs;
    }
}
