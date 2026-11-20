<?php

namespace Tests\Unit;

use App\Services\Payments\HotelOperation;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Hotel\Http\Controllers\HotelRentController;
use Modules\Hotel\Http\Requests\HotelRentItemRequest;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/** @group postgres-integration */
final class HotelOperationKeysPostgresTest extends TestCase
{
    /** @dataProvider keys */
    public function test_hotel_products_and_checkout_reject_aliased_keys(string $field): void
    {
        $fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);

        try {
            [$db, $app, $input] = (new ReflectionMethod($fixture, 'bootHkaFixture'))->invoke($fixture, false);
            $responses = $this->createMock(ResponseFactory::class);
            $responses->method('json')->willReturnCallback(static fn ($body, $status = 200) => new JsonResponse($body, $status));
            $app->instance(ResponseFactory::class, $responses);
            $branch = $input['establishment_id'];
            $category = $db->table('hotel_categories')->insertGetId(['description' => 'Prueba', 'establishment_id' => $branch]);
            $floor = $db->table('hotel_floors')->insertGetId(['description' => 'Prueba', 'establishment_id' => $branch]);
            $room = $db->table('hotel_rooms')->insertGetId([
                'name' => 'Temporal', 'status' => 'OCUPADO', 'hotel_category_id' => $category,
                'hotel_floor_id' => $floor, 'establishment_id' => $branch, 'item_id' => $input['items'][0]['item_id'],
            ]);
            $rent = $db->table('hotel_rents')->insertGetId([
                'customer_id' => $input['customer_id'], 'customer' => json_encode($input['customer'], JSON_THROW_ON_ERROR),
                'hotel_room_id' => $room, 'payment_status' => 'DEBT', 'establishment_id' => $branch,
                'output_date' => '2026-10-27', 'output_time' => '12:00',
            ]);
            $controller = new HotelRentController();
            $payload = ['operation_key' => (string) Str::uuid(), 'sale_note_id' => null, 'products' => [
                ['item_id' => $input['items'][0]['item_id'], 'total' => '1', 'payment_status' => 'DEBT'],
            ]];
            $document = ['document_type_id' => '01', 'customer_id' => $input['customer_id'], 'establishment_id' => $branch];
            $id = match ($field) {
                'rent_id' => $rent, 'sale_note_id' => 1,
                default => $document[$field],
            };
            $isCheckout = array_key_exists($field, $document);
            $expected = $isCheckout ? 'document' : $field;
            foreach ([$id.'.0', '0'.$id, '9223372036854775808', $id.'x', 0] as $invalid) {
                $db->enableQueryLog();
                $db->flushQueryLog();
                try {
                    if ($isCheckout) {
                        $request = Request::create('/', 'POST', ['operation_key' => $payload['operation_key'],
                            'document' => array_replace($document, [$field => $invalid])]);
                        $controller->atomicCheckout($request, $rent);
                    } else {
                        $product = $field === 'sale_note_id' ? array_replace($payload, ['sale_note_id' => $invalid]) : $payload;
                        $request = HotelRentItemRequest::create('/', 'POST', $product);
                        $controller->addProductsToRoom($request, $field === 'rent_id' ? $invalid : $rent);
                    }
                    self::fail("Debe rechazarse la clave {$field} {$invalid}.");
                } catch (ValidationException $error) {
                    self::assertArrayHasKey($expected, $error->errors());
                    $queries = array_column($db->getQueryLog(), 'query');
                    if ($field === 'rent_id') self::assertSame([], $queries);
                    self::assertDoesNotMatchRegularExpression('/"(sale_notes|documents)"/', implode("\n", $queries));
                } finally {
                    $db->disableQueryLog();
                }
                self::assertSame(0, $db->transactionLevel());
            }
            foreach (['hotel_rent_orders', 'hotel_rent_items', 'sale_notes', 'documents', 'global_payments'] as $table) {
                self::assertSame(0, $db->table($table)->count(), $table);
            }
            self::assertSame('OCUPADO', $db->table('hotel_rooms')->where('id', $room)->value('status'));

            if (!$isCheckout) {
                $freshRequest = static fn () => HotelRentItemRequest::create('/', 'POST', $payload);
                [$key, $hash] = HotelOperation::identify($freshRequest(), 'products', $rent);
                self::assertSame(200, $controller->addProductsToRoom($freshRequest(), (string) $rent)->getStatusCode());
                self::assertSame(200, $controller->addProductsToRoom($freshRequest(), $rent)->getStatusCode());
                self::assertSame(1, $db->table('hotel_rent_items')->count());
                self::assertSame(1, $db->table('hotel_rent_orders')->count());
                self::assertSame($hash, $db->table('hotel_rent_orders')->where('operation_key', $key)->value('operation_hash'));
            }
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }

    public static function keys(): array
    {
        return ['estancia' => ['rent_id'], 'nota' => ['sale_note_id'], 'cliente de cierre' => ['customer_id'],
            'sucursal de cierre' => ['establishment_id']];
    }
}
