<?php

namespace Tests\Unit;

use App\Exceptions\Handler;
use App\Services\Fiscal\IvaRetentionService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IvaRetentionHttpContractTest extends TestCase
{
    public function test_operation_key_conflicts_are_public_http_409_and_ordinary_validation_stays_422(): void
    {
        $request = Request::create('/api/documents/retention','POST');
        $request->headers->set('Accept','application/json');
        try { IvaRetentionService::operationConflict('La clave pertenece a otra solicitud.'); self::fail('Expected conflict'); }
        catch (ValidationException $exception) {
            $response = app(Handler::class)->render($request,$exception);
            self::assertSame(409,$response->getStatusCode());
            self::assertStringContainsString('operation_key',$response->getContent());
        }
        $response = app(Handler::class)->render($request,ValidationException::withMessages(['voucher_number'=>'Requerido']));
        self::assertSame(422,$response->getStatusCode());
    }
}
