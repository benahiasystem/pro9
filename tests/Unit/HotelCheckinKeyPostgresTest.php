<?php

namespace Tests\Unit;

use App\Services\Payments\HotelOperation;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Modules\Hotel\Http\Controllers\HotelRentController;
use Modules\Hotel\Http\Requests\HotelRentRequest;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/** @group postgres-integration */
final class HotelCheckinKeyPostgresTest extends TestCase
{
    public function test_hotel_checkin_rejects_aliased_keys_before_lookup_and_preserves_operation_hashes(): void
    {
        $fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);

        try {
            [$db, $app, $input] = (new ReflectionMethod($fixture, 'bootHkaFixture'))->invoke($fixture, false);
            $responses = $this->createMock(ResponseFactory::class);
            $responses->method('json')->willReturnCallback(static fn ($body, $status = 200) => new JsonResponse($body, $status));
            $app->instance(ResponseFactory::class, $responses);
            $branch = $input['establishment_id'];
            $category = $db->table('hotel_categories')->insertGetId(['description' => 'Temporal', 'establishment_id' => $branch]);
            $floor = $db->table('hotel_floors')->insertGetId(['description' => 'Temporal', 'establishment_id' => $branch]);
            $rate = $db->table('hotel_rates')->insertGetId(['description' => 'Temporal', 'establishment_id' => $branch]);
            $room = $db->table('hotel_rooms')->insertGetId([
                'name' => 'Temporal', 'status' => 'DISPONIBLE', 'hotel_category_id' => $category,
                'hotel_floor_id' => $floor, 'establishment_id' => $branch, 'item_id' => $input['items'][0]['item_id'],
            ]);
            $controller = new HotelRentController();
            $data = ['operation_key' => (string) Str::uuid(), 'customer_id' => (string) $input['customer_id'],
                'customer' => $input['customer'], 'hotel_rate_id' => (string) $rate, 'payment_status' => 'DEBT',
                'towels' => 1, 'duration' => 1, 'quantity_persons' => 1, 'input_time' => '09:00',
                'output_date' => '2026-10-28', 'output_time' => '12:00', 'data_persons' => [], 'product' => $input['items'][0]];
            foreach (['customer_id' => $input['customer_id'], 'hotel_rate_id' => $rate, 'room_id' => $room] as $field => $id) {
                foreach ([$id.'.0', '0'.$id, '9223372036854775808', $id.'x', 0] as $invalid) {
                    $payload = $field === 'room_id' ? $data : array_replace($data, [$field => $invalid]);
                    $request = HotelRentRequest::create('/', 'POST', $payload);
                    $db->enableQueryLog();
                    $db->flushQueryLog();
                    $result = $controller->store($request, $field === 'room_id' ? $invalid : $room);
                    $queries = $db->getQueryLog();
                    $db->disableQueryLog();
                    self::assertSame(422, $result->getStatusCode(), $field.' '.$invalid.' '.$result->getContent());
                    self::assertArrayHasKey($field, $result->getData(true)['errors']);
                    self::assertSame([], $queries);
                    self::assertSame(0, $db->transactionLevel());
                }
            }
            foreach (['hotel_rents', 'hotel_rent_orders', 'sale_notes', 'global_payments'] as $table) {
                self::assertSame(0, $db->table($table)->count(), $table);
            }
            self::assertSame('DISPONIBLE', $db->table('hotel_rooms')->where('id', $room)->value('status'));

            $request = HotelRentRequest::create('/', 'POST', $data);
            [$operationKey, $hash] = HotelOperation::identify($request, 'rent', $room);
            $created = $controller->store($request, (string) $room);
            self::assertSame(200, $created->getStatusCode(), $created->getContent());
            $rent = $db->table('hotel_rents')->sole();
            self::assertSame($input['customer_id'], $rent->customer_id);
            self::assertSame($rate, $rent->hotel_rate_id);
            self::assertSame($room, $rent->hotel_room_id);
            self::assertSame($hash, $db->table('hotel_rent_orders')->where('operation_key', $operationKey)->value('operation_hash'));
            self::assertSame(200, $controller->store(HotelRentRequest::create('/', 'POST', $data), $room)->getStatusCode());
            self::assertSame(1, $db->table('hotel_rents')->count());
            $conflicting = HotelRentRequest::create('/', 'POST', array_replace($data, ['customer_id' => $input['customer_id']]));
            self::assertArrayHasKey('operation_key', $controller->store($conflicting, $room)->getData(true)['errors']);
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }
}
