<?php

namespace Tests\Unit;

use App\CoreFacturalo\Requests\Api\Transform\DocumentTransform;
use App\Support\Venezuela\RetiredDetractionFields;
use Tests\TestCase;

class DetractionRemovalTest extends TestCase
{
    public function test_legacy_fields_are_ignored_without_changing_payments_or_retention(): void
    {
        $payments = [['payment_method_type_id' => '11', 'payment' => 100]];
        $retention = ['amount' => 3, 'guarantee_fund' => 2];
        $payload = ['detraction' => 'invalid', 'detraccion' => ['monto' => -1],
            'payments' => $payments, 'retention' => $retention, 'total_pending_payment' => 95,
            'items' => [['subject_to_detraction' => true, 'description' => 'Servicio']],
            'data_json' => ['detraccion' => ['monto' => 20], 'total' => 100]];

        self::assertSame([
            'payments' => $payments, 'retention' => $retention, 'total_pending_payment' => 95,
            'items' => [['description' => 'Servicio']], 'data_json' => ['total' => 100],
        ], RetiredDetractionFields::discard($payload));
    }

    public function test_api_transform_does_not_store_legacy_fields_in_data_json(): void
    {
        $payload = [
            'codigo_tipo_documento' => '01', 'codigo_tipo_operacion' => '0101',
            'codigo_tipo_moneda' => 'VES', 'totales' => ['total_venta' => 116],
            'datos_del_cliente_o_receptor' => [
                'codigo_tipo_documento_identidad' => '1', 'numero_documento' => '12345678',
                'apellidos_y_nombres_o_razon_social' => 'Cliente',
            ],
            'items' => [],
        ];
        $expected = DocumentTransform::transform($payload);
        $payload['detraccion'] = 'invalid legacy payload';
        $payload['detraction'] = ['amount' => -1];
        $payload['totales']['total_pendiente_detraccion'] = 500;

        self::assertSame($expected, DocumentTransform::transform($payload));
        self::assertArrayNotHasKey('detraction', $expected);
        self::assertSame(116, $expected['total']);
    }

    public function test_encoded_and_object_payload_copies_are_also_cleaned(): void
    {
        $cleaned = RetiredDetractionFields::discard([
            'data_json' => '{"detraction":{"amount":10},"total":116}',
            'customer' => (object) ['name' => 'Cliente', 'detraction_account' => 'old'],
        ]);
        self::assertSame(['total' => 116], json_decode($cleaned['data_json'], true));
        self::assertSame(['name' => 'Cliente'], (array) $cleaned['customer']);
    }

    public function test_item_company_and_configuration_do_not_accept_retired_flags(): void
    {
        foreach ([
            \App\Models\Tenant\Item::class => ['subject_to_detraction'],
            \App\Models\Tenant\Company::class => ['detraction_account'],
            \Modules\Company\Models\Company::class => ['detraction_account'],
            \App\Models\Tenant\Configuration::class => ['detraction_amount_rounded_int', 'available_detraction_for_amount_minor'],
            \App\Models\System\MassiveInvoice::class => ['incluye_detraccion', 'porcentaje_detraccion', 'servicio_detraccion'],
        ] as $class => $fields) {
            $model = new $class;
            foreach ($fields as $field) {
                self::assertNotContains($field, $model->getFillable(), $class);
                self::assertArrayNotHasKey($field, $model->getCasts(), $class);
            }
        }
    }

    public function test_models_no_longer_accept_or_serialize_detraction_attributes(): void
    {
        foreach ([
            \App\Models\Tenant\Document::class, \App\Models\Tenant\SaleNote::class,
            \App\Models\Tenant\Purchase::class, \App\Models\Tenant\Quotation::class,
            \Modules\Order\Models\OrderNote::class, \Modules\Sale\Models\Contract::class,
            \Modules\Purchase\Models\FixedAssetPurchase::class,
            \Modules\Suscription\Models\Tenant\SuscriptionPlan::class,
            \Modules\FullSuscription\Models\Tenant\SuscriptionPlan::class,
            \Modules\Suscription\Models\Tenant\UserRelSuscriptionPlan::class,
            \Modules\FullSuscription\Models\Tenant\UserRelSuscriptionPlan::class,
        ] as $class) {
            $model = new $class;
            self::assertNotContains('detraction', $model->getFillable(), $class);
            $model->fill(['detraction' => ['amount' => 10]]);
            self::assertArrayNotHasKey('detraction', $model->getAttributes(), $class);
            self::assertFalse(method_exists($model, 'getDetractionAttribute'), $class);
        }
    }
}
