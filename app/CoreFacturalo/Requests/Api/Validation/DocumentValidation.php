<?php

namespace App\CoreFacturalo\Requests\Api\Validation;

use App\Models\Tenant\Establishment;
use App\Models\Tenant\User;
use App\Services\SalesDocumentTypePolicy;
use App\Services\SalesCustomerIdentityPolicy;

class DocumentValidation
{
    public static function validation($inputs, bool $fiscalApi = false, bool $resolvedFiscal = false) {
        // ########## INICIO CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
        SalesDocumentTypePolicy::assertNewFiscalDocumentAllowed($inputs['document_type_id'] ?? null);
        // ######### FIN CAMBIO SOLO FACTURAS Y NOTAS DE VENTA

        // ######## INICIO POLITICA IDENTIDAD ACTIVA EN VENTAS ########
        SalesCustomerIdentityPolicy::assertIdentityTypeAllowed(
            data_get($inputs, 'customer.identity_document_type_id')
        );
        // ######## FIN POLITICA IDENTIDAD ACTIVA EN VENTAS ########

        // ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
        if ($resolvedFiscal) {
            if (empty($inputs['fiscal_profile_id']) || empty($inputs['operation_key']) || empty($inputs['fiscal_fingerprint'])) {
                throw new \DomainException('Falta el contexto fiscal resuelto para el pedido.');
            }
        } elseif ($fiscalApi) {
            $inputs = \App\Services\Fiscal\FiscalApiDocumentContext::prepareFor($inputs, auth()->user(), \App\Models\Tenant\Company::active()->getConnection(), app(\App\Services\SeriesResolver::class)->activeGroupId());
        } else {
            // Internal callers are migrated separately from the authenticated HTTP API.
            $authUser = auth()->user();
            if ($authUser && !empty($authUser->establishment_id)) {
                $inputs['establishment_id'] = $authUser->establishment_id;
            } elseif (empty($inputs['establishment_id'])) {
                $inputs['establishment_id'] = optional(
                    User::query()->whereNotNull('establishment_id')->orderBy('id')->first()
                )->establishment_id ?? optional(Establishment::query()->orderBy('id')->first())->id;
            }
            Functions::validateSeries($inputs);
        }
        // ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
        
        if (in_array($inputs['document_type_id'], ['07', '08'])) {

            if($inputs['affected_document_external_id']){
                $document = Functions::findAffectedDocumentByExternalId($inputs['affected_document_external_id']);
                $inputs['affected_document_id'] = $document->id;
                $inputs['data_affected_document'] = null;

            }else{
                //validar campos json doc afectado
                $inputs['affected_document_id'] = null;

            }
            
            unset($inputs['affected_document_external_id']);
        }
        
        $inputs['customer_id'] = Functions::person($inputs['customer'], 'customers');
        unset($inputs['customer']);
        
        $inputs['items'] = self::items($inputs['items']);
        
        Functions::DNI($inputs);
        Functions::identityDocumentTypeInvoice($inputs);


        Functions::validateDateOfIssue($inputs);
        
        return $inputs;
    }
    
    private static function items($inputs) {
        foreach ($inputs as &$row) {
            $row['item_id'] = Functions::item($row);
            unset($row['internal_id'], $row['description']);
            unset($row['item_type_id'], $row['item_code']);
            unset($row['item_code_gs1'], $row['unit_type_id']);
            unset($row['currency_type_id']);
        }
        
        return $inputs;
    }
}
