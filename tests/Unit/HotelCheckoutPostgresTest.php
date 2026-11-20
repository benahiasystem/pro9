<?php

namespace Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Hotel\Http\Controllers\HotelRentController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/** @group postgres-integration */
final class HotelCheckoutPostgresTest extends TestCase
{
    public function test_invoice_checkout_rejects_invalid_rent_keys_before_querying_postgresql(): void
    {
        $fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);

        try {
            [$db, $app, $input] = (new ReflectionMethod($fixture, 'bootHkaFixture'))->invoke($fixture);
            $branch = $input['establishment_id'];
            $category = $db->table('hotel_categories')->insertGetId(['description' => 'Cierre', 'establishment_id' => $branch]);
            $floor = $db->table('hotel_floors')->insertGetId(['description' => 'Cierre', 'establishment_id' => $branch]);
            $room = $db->table('hotel_rooms')->insertGetId([
                'name' => 'Cierre', 'hotel_category_id' => $category, 'hotel_floor_id' => $floor,
                'item_id' => $input['items'][0]['item_id'], 'status' => 'OCUPADO', 'establishment_id' => $branch,
            ]);
            $rent = $db->table('hotel_rents')->insertGetId([
                'customer_id' => $input['customer_id'], 'customer' => json_encode($input['customer'], JSON_THROW_ON_ERROR),
                'hotel_room_id' => $room, 'payment_status' => 'DEBT', 'establishment_id' => $branch,
                'output_date' => '2026-10-27', 'output_time' => '12:00',
            ]);
            $key = (string) Str::uuid();
            $controller = new HotelRentController();
            $request = Request::create('/', 'POST', ['operation_key' => $key, 'document' => $input]);
            $entryPoints = [
                'crear' => static fn ($id) => $controller->atomicCheckout($request, $id),
                'consultar' => static fn ($id) => $controller->checkoutOperationStatus($id, $key),
            ];
            foreach ($entryPoints as $operation => $execute) {
                foreach (['9223372036854775808', $rent.'x', '0'.$rent, $rent.'.0', '0'] as $invalid) {
                    try {
                        $execute($invalid);
                        self::fail($operation.' debe rechazar la clave inválida de estancia.');
                    } catch (ValidationException $error) {
                        self::assertArrayHasKey('rent_id', $error->errors(), $operation);
                    }
                }
            }
            $responses = $this->createMock(ResponseFactory::class);
            $responses->method('json')->willReturnCallback(static fn ($body, $status = 200) => new JsonResponse($body, $status));
            $app->instance(ResponseFactory::class, $responses);
            self::assertFalse($controller->checkoutOperationStatus((string) $rent, $key)->getData()->found);
            self::assertSame(0, $db->table('documents')->count());
            self::assertSame(0, $db->table('document_payments')->count());
            self::assertSame('OCUPADO', $db->table('hotel_rooms')->where('id', $room)->value('status'));
            self::assertSame(0, $db->transactionLevel());
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }
}
