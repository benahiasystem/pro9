<?php

namespace App\Traits;

use App\Models\Tenant\Catalogs\District;

/**
 * Piezas comunes de los endpoints "find" de la API (document, sale-note,
 * quotation, purchase, dispatch).
 *
 * Los tres helpers estaban duplicados en Document y SaleNote; se centralizan aqui
 * para que los nuevos recursos no vuelvan a copiarlos.
 */
trait ApiResourceFindTrait
{
    /**
     * Ubigeo de una persona, con respaldo en su direccion principal.
     *
     * Las columnas department_id / province_id / district_id de persons pueden quedar
     * vacias cuando el ubigeo solo llego a person_addresses (la fila main). Person::find()
     * no mira esa tabla, asi que aqui se consulta antes de devolver null.
     *
     * @param  \App\Models\Tenant\Person|null  $person
     * @return array
     */
    protected function resolvePersonUbigeo($person)
    {
        $empty = ['department_id' => null, 'province_id' => null, 'district_id' => null];

        if (!$person) {
            return $empty;
        }

        if ($person->department_id && $person->province_id && $person->district_id) {
            return [
                'department_id' => $person->department_id,
                'province_id'   => $person->province_id,
                'district_id'   => $person->district_id,
            ];
        }

        $address = $person->addresses()
            ->orderByDesc('main')
            ->orderBy('id')
            ->first();

        if (!$address || !$address->department_id || !$address->province_id || !$address->district_id) {
            return $empty;
        }

        return [
            'department_id' => $address->department_id,
            'province_id'   => $address->province_id,
            'district_id'   => $address->district_id,
        ];
    }

    /**
     * Arma la direccion de una persona igual que las plantillas PDF: la direccion
     * registrada mas distrito, provincia y departamento, sin partes vacias.
     *
     * Acepta tanto el snapshot json del comprobante como el modelo Person; en ambos
     * casos se leen las mismas propiedades. $ubigeo sirve de respaldo cuando el
     * origen no trae district_id propio.
     *
     * @param  object|null  $person
     * @param  array  $ubigeo
     * @return string|null
     */
    protected function buildApiResourcePersonAddress($person, array $ubigeo = [])
    {
        if (!$person) {
            return null;
        }

        $district_id = $person->district_id ?? null;
        if (!$district_id || $district_id === '-') {
            $district_id = $ubigeo['district_id'] ?? null;
        }

        $record = ($district_id && $district_id !== '-') ? District::find($district_id) : null;

        $parts = [
            $person->address ?? null,
            optional($person->district)->description ?: optional($record)->description,
            optional($person->province)->description ?: optional(optional($record)->province)->description,
            optional($person->department)->description
                ?: optional(optional(optional($record)->province)->department)->description,
        ];

        $parts = array_filter($parts, fn ($part) => !empty(trim((string) $part)));

        return $parts ? implode(', ', $parts) : null;
    }

    /**
     * Normaliza las lineas para la API.
     *
     * Los descuentos con from_global_distribution provienen de repartir el descuento
     * global entre los items, asi que se acumulan en $global_discount y no se mezclan
     * con el descuento propio de cada linea.
     *
     * @param  iterable  $items
     * @param  float  $global_discount  acumulador por referencia
     * @return array
     */
    protected function buildApiResourceItems($items, &$global_discount)
    {
        return collect($items)->map(function ($row) use (&$global_discount) {
            $item_discount = 0;

            foreach (($row->discounts ?: []) as $discount) {
                // discount_type_id "00" guarda el importe sin IGV.
                $amount = $discount->discount_type_id == '00'
                    ? $discount->amount_without_rounded * 1.18
                    : $discount->amount;

                if (!empty($discount->from_global_distribution)) {
                    $global_discount += $amount;
                } else {
                    $item_discount += $amount;
                }
            }

            return [
                'quantity'       => (float) $row->quantity,
                'unit_type_id'   => optional($row->item)->unit_type_id,
                'description'    => $row->name_product_pdf ?: optional($row->item)->description,
                'unit_price'     => round((float) $row->unit_price, 2),
                // Solo el descuento propio de la linea, sin la parte repartida del global.
                'total_discount' => round($item_discount, 2),
                'total'          => round((float) $row->total, 2),
            ];
        })->values()->all();
    }
}
