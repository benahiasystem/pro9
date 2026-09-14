<?php

namespace Tests\Unit;

use App\Http\Controllers\Tenant\FiscalDispatchEmissionController;
use App\Models\Tenant\User;
use App\Services\FiscalProfileService;
use Illuminate\Http\Request;
use Tests\Support\FiscalDatabaseTestCase;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
class FiscalDispatchEmissionControllerTest extends FiscalDatabaseTestCase
{
    private function setupDispatch(): FiscalDispatchEmissionController
    {
        $this->db->statement('CREATE TABLE dispatches (id INTEGER PRIMARY KEY, establishment_id INTEGER, user_id INTEGER)');
        $this->db->table('dispatches')->insert(['id' => 1, 'establishment_id' => 1, 'user_id' => 1]);
        $profile = (new FiscalProfileService($this->db))->save(1, [
            'name' => 'Orden', 'channel' => 'presential', 'document_type_id' => '09', 'mode' => 'digital',
            'sequence_id' => $this->repository->createSequence('09', '', 1, 1), 'provider' => 'simulator', 'configuration' => [], 'active' => true,
        ], 1);
        $this->repository->reserveForProfile($profile['id'], 'delivery', hash('sha256', 'delivery'), 1, 'presential');
        $this->db->table('fiscal_number_reservations')->update(['dispatch_id' => 1]);
        return new class($this->db) extends FiscalDispatchEmissionController {
            private $db;
            public function __construct($db) { $this->db = $db; }
            protected function connection() { return $this->db; }
        };
    }

    private function request(int $userId = 1, int $establishment = 1): Request
    {
        $user = new User();
        $user->setRawAttributes(['id' => $userId, 'type' => 'seller', 'establishment_id' => $establishment]);
        $request = Request::create('/dispatches/1/fiscal');
        $request->setUserResolver(fn () => $user);
        return $request;
    }

    public function test_owner_can_process_and_query_order_without_invoice_table(): void
    {
        $controller = $this->setupDispatch();
        $result = $controller->process($this->request(), 1);
        $this->assertSame('issued', $result['data']['status']);
        $this->assertTrue($result['data']['simulated']);
        $this->assertSame('issued', $controller->record($this->request(), 1)['data']['status']);
        $this->assertNull($this->db->table('fiscal_number_reservations')->value('document_id'));
    }

    public function test_another_seller_cannot_process_order(): void
    {
        $controller = $this->setupDispatch();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $controller->process($this->request(2), 1);
    }

    public function test_owner_cannot_access_order_from_another_establishment(): void
    {
        $controller = $this->setupDispatch();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $controller->record($this->request(1, 2), 1);
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
