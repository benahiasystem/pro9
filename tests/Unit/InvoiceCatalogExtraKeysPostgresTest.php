<?php

namespace Tests\Unit;

use App\CoreFacturalo\Facturalo;
use App\CoreFacturalo\Requests\Inputs\DocumentInput;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/** @group postgres-integration */
final class InvoiceCatalogExtraKeysPostgresTest extends TestCase
{
    public function test_invoice_extra_identifiers_reject_aliases_and_preserve_bigint_and_empty_keys(): void
    {
        $fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);

        try {
            [$db, , $input] = (new ReflectionMethod($fixture, 'bootHkaFixture'))->invoke($fixture, false);
            $db->table('companies')->update(['fiscal_emission_mode' => 'free_form']);
            $db->table('configurations')->update(['auto_send_hka' => false]);
            $input['payments'] = [];
            $input['payment_condition_id'] = '02';
            $fields = ['colors', 'CatItemUnitsPerPackage', 'CatItemMoldProperty', 'CatItemProductFamily',
                'CatItemMoldCavity', 'CatItemPackageMeasurement', 'CatItemStatus', 'CatItemUnitBusiness', 'CatItemSize'];
            $large = '9007199254740993';
            foreach ([$large.'.9', '1x', '9223372036854775808', [1]] as $bad) {
                foreach ($fields as $field) {
                    $draft = $input;
                    $draft['items'][0]['item']['extra'] = [$field => $bad];
                    try {
                        DocumentInput::items($draft);
                        self::fail("La factura debe rechazar una clave no canónica en {$field}.");
                    } catch (ValidationException $error) {
                        self::assertArrayHasKey('items.item.extra.'.$field, $error->errors());
                    }
                }
            }
            self::assertSame(0, $db->table('documents')->count());
            self::assertSame(0, $db->table('item_movement_rel_extra')->count());

            $extras = array_combine($fields, [$large, null, 7, '7', '0', 0, null, null, null]);
            $input['items'][0]['item']['extra'] = $extras;
            $input['items'] = DocumentInput::items($input);
            $invoice = (new Facturalo())->forWebDocuments()->save($input)->getDocument()->fresh();
            $recorded = (array) $invoice->items()->sole()->item->extra;
            unset($recorded['string']);
            self::assertSame(array_map(static fn ($id) => $id === null ? null : (int) $id, $extras), $recorded);
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }
}
