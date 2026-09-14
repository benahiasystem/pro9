<?php

namespace Tests\Unit;

use App\Http\Controllers\Tenant\FiscalDocumentEmissionController;
use App\Models\Tenant\User;
use App\Services\FiscalProfileService;
use Illuminate\Http\Request;
use Tests\Support\FiscalDatabaseTestCase;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
class FiscalDocumentEmissionControllerTest extends FiscalDatabaseTestCase
{
    private function controller(): FiscalDocumentEmissionController
    {
        return new class($this->db) extends FiscalDocumentEmissionController {
            private $db;
            public function __construct($db) { $this->db = $db; }
            protected function connection() { return $this->db; }
        };
    }

    private function request(string $type = 'admin', int $id = 1, int $establishment = 1, array $payload = []): Request
    {
        $user = new User();
        $user->setRawAttributes(['id' => $id, 'type' => $type, 'establishment_id' => $establishment]);
        $request = Request::create('/documents/1/fiscal', 'POST', $payload);
        $request->setUserResolver(fn () => $user);
        return $request;
    }

    private function subject(string $mode = 'digital'): object
    {
        $this->db->statement('CREATE TABLE documents (id INTEGER PRIMARY KEY, establishment_id INTEGER, user_id INTEGER, seller_id INTEGER)');
        $this->db->table('documents')->insert(['id' => 1, 'establishment_id' => 1, 'user_id' => 1, 'seller_id' => 2]);
        $lot = $mode === 'free_form' ? $this->repository->createLot([
            'establishment_id' => 1, 'start' => '00-1', 'end' => '00-10', 'printer_name' => 'Demo', 'printer_rif' => 'J-00000000-0',
            'authorization' => 'DEMO', 'authorization_date' => '2026-01-01', 'prepared_at' => '2026-01-01',
        ]) : null;
        $profile = (new FiscalProfileService($this->db))->save(1, [
            'name' => 'Demo', 'channel' => 'presential', 'document_type_id' => '01', 'mode' => $mode,
            'sequence_id' => $this->repository->createSequence('01', '', 1, 1),
            'control_lot_id' => $lot, 'provider' => $lot ? 'none' : 'simulator', 'configuration' => $lot ? ['page_capacity' => 10] : [], 'active' => true,
        ], 1);
        $reservation = $this->repository->reserveForProfile($profile['id'], 'sale', hash('sha256', 'sale'), 1, 'presential');
        $this->db->table('fiscal_number_reservations')->where('id', $reservation->id)->update(['document_id' => 1]);
        return $reservation;
    }

    public function test_seller_can_process_assigned_document_without_repeating_emission(): void
    {
        $this->subject();
        $controller = $this->controller();
        $request = $this->request('seller', 2);
        $result = $controller->process($request, 1);
        $this->assertSame('issued', $result['data']['status']);
        $this->assertTrue($result['data']['simulated']);
        $controller->process($request, 1);
        $this->assertSame(1, $this->db->table('fiscal_emission_attempts')->count());
        $this->assertArrayNotHasKey('fiscal_snapshot', $result['data']);
        $this->assertArrayNotHasKey('payload_fingerprint', $result['data']);
        $this->assertArrayNotHasKey('credentials', $result['data']);
    }

    /** @dataProvider deniedScopes */
    public function test_actions_cannot_escape_document_and_establishment_scope(string $action, string $type, int $user, int $establishment): void
    {
        $this->subject();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $this->controller()->$action($this->request($type, $user, $establishment), 1);
    }

    public static function deniedScopes(): array
    {
        $cases = [];
        foreach (['record', 'process', 'confirmPrint'] as $action) {
            $cases[] = [$action, 'seller', 3, 1];
            $cases[] = [$action, 'seller', 2, 2];
            $cases[] = [$action, 'admin', 1, 2];
            $cases[] = [$action, 'integrator', 3, 1];
            $cases[] = [$action, 'integrator', 1, 2];
        }
        return $cases;
    }

    public function test_seller_cannot_invalidate_even_own_control(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class);
        $this->controller()->invalidatePrint($this->request('seller'), 1);
    }

    public function test_seller_cannot_start_contingency_even_for_own_document(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class);
        $this->controller()->contingency($this->request('seller'), 1);
    }

    public function test_administrator_cannot_start_contingency_for_another_establishment(): void
    {
        $this->subject();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $this->controller()->contingency($this->request('admin', 1, 2), 1);
    }

    public function test_unknown_user_role_is_rejected_before_database_lookup(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class);
        $this->controller()->process($this->request('unknown'), 1);
    }

    public function test_integrator_can_process_own_document(): void
    {
        $this->subject();
        $this->assertSame('issued', $this->controller()->process($this->request('integrator'), 1)['data']['status']);
    }

    public function test_integrator_cannot_invalidate_a_control(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class);
        $this->controller()->invalidatePrint($this->request('integrator'), 1);
    }

    public function test_print_confirmation_uses_authenticated_actor(): void
    {
        $this->subject('free_form');
        $controller = $this->controller();
        $request = $this->request('seller', 2, 1, ['confirmed_by' => 99]);
        $controller->process($request, 1);
        $result = $controller->confirmPrint($request, 1);
        $this->assertSame('issued', $result['data']['status']);
        $this->assertSame(2, (int) $this->db->table('fiscal_number_reservations')->value('confirmed_by'));
    }

    public function test_admin_invalidation_is_audited_and_control_is_retained(): void
    {
        $this->subject('free_form');
        $controller = $this->controller();
        $request = $this->request('admin', 1, 1, ['reason' => 'Formato dañado']);
        $controller->process($request, 1);
        $result = $controller->invalidatePrint($request, 1);
        $this->assertSame('inutilized', $result['data']['status']);
        $this->assertSame('00-00000001', $result['data']['control_number']);
        $this->assertSame(1, $this->db->table('fiscal_numbering_audits')->where('action', 'invalidate_print')->count());
    }

    public function test_reserved_document_cannot_be_rewritten_by_commercial_update(): void
    {
        $this->subject();
        $facturalo = new class($this->db) extends \App\CoreFacturalo\Facturalo {
            public function __construct($db) {
                $this->company = new class($db) {
                    private $db;
                    public function __construct($db) { $this->db = $db; }
                    public function getConnection() { return $this->db; }
                };
            }
        };
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $facturalo->update(['type' => 'invoice', 'total' => 999], 1);
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
