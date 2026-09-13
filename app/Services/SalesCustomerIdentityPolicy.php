<?php

namespace App\Services;

use App\Models\Tenant\Catalogs\IdentityDocumentType;
use App\Models\Tenant\Person;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

// ######## INICIO POLITICA IDENTIDAD ACTIVA EN VENTAS ########
final class SalesCustomerIdentityPolicy
{
    public static function activeIdentityTypeIds(): array
    {
        return IdentityDocumentType::query()
            ->whereSalesEmissionActive()
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->values()
            ->all();
    }

    public static function filterEligibleCustomers(Builder $query): Builder
    {
        return $query->whereSalesIdentityActive();
    }

    public static function assertIdentityTypeAllowed($identityDocumentTypeId, string $field = 'identity_document_type_id'): string
    {
        $identityDocumentTypeId = (string) $identityDocumentTypeId;
        $isActive = IdentityDocumentType::query()
            ->whereSalesEmissionActive()
            ->where('id', $identityDocumentTypeId)
            ->exists();

        if (! $isActive) {
            throw ValidationException::withMessages([
                $field => 'El tipo de documento de identidad no está activo para emitir comprobantes de venta.',
            ]);
        }

        return $identityDocumentTypeId;
    }

    public static function assertCustomerAllowed($customerId): Person
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

        self::assertIdentityTypeAllowed($customer->identity_document_type_id, 'customer_id');

        return $customer;
    }
}
// ######## FIN POLITICA IDENTIDAD ACTIVA EN VENTAS ########
