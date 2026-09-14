<?php

namespace Tests\Unit;

use App\Exceptions\Handler;
use App\Http\Controllers\Tenant\FiscalNumberingController;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
class FiscalHttpExceptionResponseTest extends TestCase
{
    private $previousContainer;
    private $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        $app = new Container();
        Container::setInstance($app);
        $app->instance('config', new Repository(['app' => ['debug' => false]]));
        $factory = $this->createMock(ResponseFactory::class);
        $factory->method('json')->willReturnCallback(fn ($data = [], $status = 200, $headers = [], $options = 0) => new JsonResponse($data, $status, $headers, $options));
        $app->instance(ResponseFactory::class, $factory);
        $this->handler = new Handler($app);
    }

    protected function tearDown(): void
    {
        Container::setInstance($this->previousContainer);
        parent::tearDown();
    }

    private function request(): Request
    {
        $request = Request::create('/establishments/1/fiscal-numbering');
        $request->headers->set('Accept', 'application/json');
        return $request;
    }

    public function test_actual_numbering_guard_is_rendered_as_forbidden_instead_of_server_error(): void
    {
        $request = $this->request();
        $request->setUserResolver(fn () => null);
        try {
            (new FiscalNumberingController())->records($request, 1);
            $this->fail('Unauthorized request accepted');
        } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $exception) {
            $response = $this->handler->render($request, $exception);
        }
        $this->assertSame(403, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertFalse($payload['success']);
        $this->assertStringContainsString('administrador tenant', $payload['message']);
        $this->assertArrayNotHasKey('file', $payload);
    }

    public function test_http_retry_headers_and_status_survive_json_rendering(): void
    {
        $exception = new \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException(30, 'Retry later');
        $response = $this->handler->render($this->request(), $exception);
        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame('30', $response->headers->get('Retry-After'));
    }

    public function test_missing_route_keeps_existing_not_found_contract(): void
    {
        $response = $this->handler->render($this->request(), new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException());
        $this->assertSame(404, $response->getStatusCode());
        $this->assertFalse($response->getData(true)['success']);
    }

    public function test_json_validation_response_preserves_field_errors_inside_message(): void
    {
        $factory = new \Illuminate\Validation\Factory(new \Illuminate\Translation\Translator(new \Illuminate\Translation\ArrayLoader(), 'es'));
        $validator = $factory->make(['initial_number' => 0], ['initial_number' => 'required|integer|min:1']);
        $response = $this->handler->render($this->request(), new \Illuminate\Validation\ValidationException($validator));
        $this->assertSame(422, $response->getStatusCode());
        $this->assertArrayHasKey('initial_number', $response->getData(true)['message']);
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
