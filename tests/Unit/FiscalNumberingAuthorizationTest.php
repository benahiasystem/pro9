<?php

namespace Tests\Unit;

use App\Http\Controllers\Tenant\FiscalNumberingController;
use App\Models\Tenant\User;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
class FiscalNumberingAuthorizationTest extends TestCase
{
    public function test_tenant_administrator_passes_the_same_server_guard(): void
    {
        $user = $this->getMockBuilder(User::class)->disableOriginalConstructor()->onlyMethods(['getAttribute'])->getMock();
        $user->method('getAttribute')->willReturn('admin');
        $request = Request::create('/establishments/1/fiscal-numbering');
        $request->setUserResolver(fn () => $user);
        $guard = new \ReflectionMethod(FiscalNumberingController::class, 'authorizeAdministrator');
        $guard->setAccessible(true);
        $this->assertNull($guard->invoke(new FiscalNumberingController(), $request));
    }

    /** @dataProvider deniedRequests */
    public function test_every_configuration_action_rejects_non_administrators_before_database_access(string $method, string $identity, bool $ajax): void
    {
        $request = Request::create('/establishments/1/fiscal-numbering', 'POST');
        if ($ajax) {
            $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        }
        $user = null;
        if ($identity === 'seller') {
            $user = $this->getMockBuilder(User::class)->disableOriginalConstructor()->onlyMethods(['getAttribute'])->getMock();
            $user->method('getAttribute')->willReturn('seller');
        } elseif ($identity === 'external_admin') {
            $user = (object) ['type' => 'admin'];
        }
        $request->setUserResolver(fn () => $user);
        $this->expectException(AccessDeniedHttpException::class);
        (new FiscalNumberingController())->$method($request, 1);
    }

    public static function deniedRequests(): array
    {
        $cases = [];
        foreach (['records', 'sequence', 'lot', 'profile', 'archive'] as $action) {
            foreach (['visitor', 'seller', 'external_admin'] as $identity) {
                foreach ([false, true] as $ajax) {
                    $cases[] = [$action, $identity, $ajax];
                }
            }
        }
        return $cases;
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
