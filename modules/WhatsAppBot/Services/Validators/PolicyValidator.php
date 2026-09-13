<?php

// ######## INICIO MIGRACIÓN MONEDA VENEZUELA ########

namespace Modules\WhatsAppBot\Services\Validators;

use App\Services\SalesCustomerIdentityPolicy;
use Illuminate\Validation\ValidationException;

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

        // ######## INICIO POLITICA IDENTIDAD ACTIVA EN VENTAS ########
        try {
            SalesCustomerIdentityPolicy::assertIdentityTypeAllowed(
                $draft['customer_identity_document_type_id'] ?? null,
                'customer_identity_document_type_id'
            );
        } catch (ValidationException $exception) {
            return ValidationResult::fail(
                'El tipo de identidad del cliente no está activo para ventas.',
                'inactive_customer_identity_type'
            );
        }
        // ######## FIN POLITICA IDENTIDAD ACTIVA EN VENTAS ########

        return ValidationResult::ok();
    }
}

// ######## FIN MIGRACIÓN MONEDA VENEZUELA ########
