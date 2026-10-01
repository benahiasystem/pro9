<?php

namespace App\CoreFacturalo\Requests\Web\Validation;

use Exception;

class DispatchValidation
{
    public static function validation($inputs)
    {
        // ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
        if (($inputs['document_type_id'] ?? null) !== '09') {
            throw new \DomainException('Tipo de orden de entrega no admitido.');
        }
        // ########## INICIO RETIRO DATOS VEHICULARES SECUNDARIOS ##########
        if (array_key_exists('secondary_license_plates', $inputs)) {
            throw new \DomainException('La placa de semirremolque fue retirada de las Órdenes de entrega.');
        }
        // ######### FIN RETIRO DATOS VEHICULARES SECUNDARIOS #########
        // ########## INICIO RETIRO TRASLADO M1/L1 ##########
        foreach (['is_transport_m1l', 'license_plate_m1l'] as $retiredField) {
            if (array_key_exists($retiredField, $inputs)) {
                throw new \DomainException('El traslado de vehículos M1/L1 fue retirado de las Órdenes de entrega.');
            }
        }
        // ######### FIN RETIRO TRASLADO M1/L1 #########
        $series = Functions::findSeries($inputs);
        if (!$series) throw new Exception("La serie no fue encontrada.");
        $inputs['series'] = $series->number;
        unset($inputs['series_id']);
        return $inputs;
        // ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
    }
}
