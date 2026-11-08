<?php

namespace App\CoreFacturalo\Requests\Api\Validation;

class PurchaseSettlementValidation
{
    public static function validation($inputs) { 
        \App\Services\Fiscal\PurchaseSettlementPolicy::assertAvailable($inputs['document_type_id'] ?? null);
        
        $inputs['establishment_id'] = auth()->user()->establishment_id;

        Functions::validateSeries($inputs);

        $inputs['supplier_id'] = Functions::person($inputs['supplier'], 'suppliers');
        unset($inputs['supplier']);
        
        $inputs['items'] = self::items($inputs['items'], $inputs['exchange_rate_sale'] ?? null);
        
        // Functions::DNI($inputs);
        
        return $inputs;
    }
    
    private static function items($inputs, $exchangeRate) {

        foreach ($inputs as &$row) {
            $row['item_id'] = Functions::item($row, $exchangeRate);
            unset($row['internal_id'], $row['description']);
            unset($row['item_type_id'], $row['item_code']);
            unset($row['item_code_gs1'], $row['unit_type_id']);
            unset($row['currency_type_id']);
        }
        
        return $inputs;
    }
}
