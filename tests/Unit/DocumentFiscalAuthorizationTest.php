<?php
namespace Tests\Unit;
use App\Http\Controllers\Tenant\DocumentFiscalController;
use App\Models\Tenant\{Document,User};
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;
class DocumentFiscalAuthorizationTest extends TestCase
{
    public function test_a_seller_can_only_operate_on_documents_of_their_establishment(): void
    {
        $this->actingAs(new User(['type'=>'seller','establishment_id'=>1]));
        DocumentFiscalController::authorizeDocument(new Document(['establishment_id'=>1]));
        $this->expectException(HttpException::class);
        DocumentFiscalController::authorizeDocument(new Document(['establishment_id'=>2]));
    }
    public function test_tenant_administrators_can_prepare_adjustments_across_establishments(): void
    {
        $this->actingAs(new User(['type'=>'admin','establishment_id'=>1]));
        DocumentFiscalController::authorizeDocument(new Document(['establishment_id'=>2]));
        self::assertTrue(true);
    }
    public function test_an_unauthenticated_caller_cannot_operate_on_fiscal_documents(): void
    {
        $this->expectException(HttpException::class);
        DocumentFiscalController::authorizeDocument(new Document(['establishment_id'=>1]));
    }
}
