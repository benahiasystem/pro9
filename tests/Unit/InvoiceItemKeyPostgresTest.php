<?php

namespace Tests\Unit;

use App\CoreFacturalo\Requests\Inputs\DocumentInput;
use Illuminate\Validation\ValidationException;

/** @group postgres-integration */
final class InvoiceItemKeyPostgresTest extends \PHPUnit\Framework\TestCase
{
    public function test_web_invoice_validates_item_key_before_lookup(): void
    {
        $fixture = new FiscalEmissionSchemaTest();
        (new \ReflectionMethod($fixture, 'setUp'))->invoke($fixture);
        try {
            [$db, , $input] = (new \ReflectionMethod($fixture, 'bootHkaFixture'))->invoke($fixture);
            \Tests\Support\PostgresTestEnvironment::assertServer($db);
            $id = (string) $input['items'][0]['item_id'];
            foreach (['9223372036854775808', '0'.$id, $id.'.0', [$id], null] as $invalid) {
                $input['items'][0]['item_id'] = $invalid;
                try {
                    DocumentInput::items($input);
                    self::fail('La factura debe rechazar una clave de partida no canónica.');
                } catch (ValidationException $error) {
                    self::assertArrayHasKey('items', $error->errors());
                }
            }
            $input['items'][0]['item_id'] = $id;
            self::assertSame($id, (string) DocumentInput::items($input)[0]['item_id']);
            self::assertSame(0, $db->table('documents')->count());
        } finally {
            (new \ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }
}
