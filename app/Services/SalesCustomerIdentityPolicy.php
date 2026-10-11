<?php

namespace App\Services;

use App\Models\Tenant\Catalogs\IdentityDocumentType;
use App\Models\Tenant\Person;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

// ######## INICIO POLITICA IDENTIDAD ACTIVA EN VENTAS ########
final class SalesCustomerIdentityPolicy
{
    public static function activeIdentityTypeIds(bool $exportation = false): array
    {
        return IdentityDocumentType::query()
            ->whereSalesEmissionActive()
            ->where('id', $exportation ? '=' : '<>', 'ND')
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->values()
            ->all();
    }

    public static function filterEligibleCustomers(Builder $query): Builder
    {
        return $query->whereSalesIdentityActive();
    }

    public static function assertIdentityTypeAllowed($identityDocumentTypeId, string $field = 'identity_document_type_id', bool $exportation = false): string
    {
        $identityDocumentTypeId = (string) $identityDocumentTypeId;
        $isActive = IdentityDocumentType::query()
            ->whereSalesEmissionActive()
            ->where('id', $identityDocumentTypeId)
            ->exists();

        if (! $isActive || ($exportation ? $identityDocumentTypeId !== 'ND' : $identityDocumentTypeId === 'ND')) {
            throw ValidationException::withMessages([
                $field => 'El tipo de documento de identidad no está activo para emitir comprobantes de venta.',
            ]);
        }

        return $identityDocumentTypeId;
    }

    public static function assertCustomerAllowed($customerId, bool $exportation = false): Person
    {
        $customer = Person::query()
            ->whereType('customers')
            ->whereKey($customerId)
            ->first();

        if (! $customer) {
            throw ValidationException::withMessages([
                'customer_id' => 'El cliente seleccionado no existe.',
            ]);
        }

        self::assertIdentityTypeAllowed($customer->identity_document_type_id, 'customer_id', $exportation);
        if ($exportation && ($customer->country_id === 'VE' || !preg_match('/^[A-Z]{2,4}$/D', (string) $customer->external_identity_type) || $customer->external_identity_type === 'ND' || !preg_match('/^[A-Za-z0-9][A-Za-z0-9.-]{0,44}$/D', (string) $customer->number) || !trim((string) $customer->address))) {
            throw ValidationException::withMessages(['customer_id' => 'Complete país, identificación externa y dirección del comprador no domiciliado.']);
        }

        return $customer;
    }
}
// ######## FIN POLITICA IDENTIDAD ACTIVA EN VENTAS ########
