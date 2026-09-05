<?php

// ######## INICIO MIGRACIÓN MONEDA VENEZUELA ########

namespace Modules\WhatsAppBot\Services\Validators;

class PolicyValidator implements DocumentValidator
{
    // ########## INICIO CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
    private const ALLOWED_TYPES = ['01'];
    // ######### FIN CAMBIO SOLO FACTURAS Y NOTAS DE VENTA

    public function validate(array $draft): ValidationResult
    {
        $type = $draft['document_type_id'] ?? null;
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            return ValidationResult::fail(
                // ########## INICIO CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
                "Tipo de comprobante no permitido. Sólo se permite factura (01).",
                // ######### FIN CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
                'invalid_document_type'
            );
        }

        $items = $draft['items'] ?? [];
        if (empty($items)) {
            return ValidationResult::fail('El comprobante no tiene items.', 'no_items');
        }

        if ($type === '01') {
            $docType = $draft['customer_identity_document_type_id'] ?? null;
            if ($docType !== '6') {
                return ValidationResult::fail('Para factura el cliente debe tener RIF.', 'factura_requires_ruc');
            }
        }

        return ValidationResult::ok();
    }
}

// ######## FIN MIGRACIÓN MONEDA VENEZUELA ########
