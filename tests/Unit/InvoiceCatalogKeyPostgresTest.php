<?php

namespace Tests\Unit;

use App\Http\Requests\Tenant\DocumentRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Document\Http\Controllers\DocumentController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** @group postgres-integration */
final class InvoiceCatalogKeyPostgresTest extends TestCase
{
    /** @dataProvider entryPoints */
    public function test_invoice_catalog_rejects_malformed_keys_before_querying(string $entryPoint): void
    {
        $fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);
        try {
            [$db, , $input] = (new ReflectionMethod($fixture, 'bootHkaFixture'))->invoke($fixture, false);
            $itemId = $input['items'][0]['item_id'];
            $controller = new DocumentController();
            $validate = static function (array $data) {
                $request = DocumentRequest::create('/documents', 'POST', $data);
                $validator = validator($data, $request->rules(), $request->messages());
                $request->withValidator($validator);
                return $validator->errors();
            };
            $input['payments'] = [];
            $cases = ['9223372036854775808', $itemId.'x', '0'.$itemId, $itemId.'.0', '0'];
            foreach ($cases as $invalid) {
                $db->enableQueryLog();
                $db->flushQueryLog();
                try {
                    if ($entryPoint === 'form') {
                        $data = $input;
                        $data['items'][0]['item_id'] = $invalid;
                        self::assertTrue($validate($data)->has('items.0.item_id'), $invalid);
                    } else {
                        try {
                            if ($entryPoint === 'single') {
                                $controller->searchItemById($invalid);
                            } else {
                                $controller->searchItems(Request::create('/documents/search-items', 'GET', [
                                    'items_id' => [(string) $itemId, $invalid],
                                ]));
                            }
                            self::fail('La clave inválida debe rechazarse: '.$invalid);
                        } catch (HttpException $error) {
                            self::assertSame('single', $entryPoint);
                            self::assertSame(404, $error->getStatusCode());
                        } catch (ValidationException $error) {
                            self::assertSame('list', $entryPoint);
                            self::assertArrayHasKey('items_id.1', $error->errors());
                        }
                    }
                    self::assertSame([], $db->getQueryLog(), $invalid);
                } finally {
                    $db->disableQueryLog();
                }
            }
            self::assertSame(0, $db->table('documents')->count());

            if ($entryPoint === 'form') {
                $db->table('items')->where('id', $itemId)->update(['lots_enabled' => true]);
                $line = $input['items'][0];
                $line['item_id'] = (string) $itemId;
                unset($line['description'], $line['item']['description'], $line['IdLoteSelected'], $line['item']['IdLoteSelected']);
                $errors = $validate(array_replace($input, ['items' => [$line, $line]]));
                self::assertStringContainsString('ítem #1', $errors->first('items.0.IdLoteSelected'));
                self::assertStringContainsString('ítem #2', $errors->first('items.1.IdLoteSelected'));
            } elseif ($entryPoint === 'single') {
                self::assertSame($itemId, collect($controller->searchItemById((string) $itemId)['items'])->sole()['id']);
            } else {
                try {
                    $controller->searchItems(Request::create('/', 'GET', ['items_id' => (string) $itemId]));
                    self::fail('Se requiere un arreglo de artículos.');
                } catch (ValidationException $error) {
                    self::assertArrayHasKey('items_id', $error->errors());
                }
                $request = Request::create('/', 'GET', ['items_id' => [(string) $itemId]]);
                self::assertSame($itemId, collect($controller->searchItems($request)['items'])->sole()['id']);
            }
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }

    public static function entryPoints(): array
    {
        return ['lotes' => ['form'], 'artículo' => ['single'], 'lista' => ['list']];
    }
}
