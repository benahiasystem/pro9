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
    public function test_edit_contract_respects_branch_scope_and_remote_registration(): void
    {
        $document = new Document(['document_type_id' => '01', 'state_type_id' => '01', 'establishment_id' => 2]);
        $document->setRelation('emission', null);
        $this->actingAs(new User(['type' => 'seller', 'establishment_id' => 1]));
        $view = \App\Services\Fiscal\DocumentEditPolicy::view($document);
        self::assertFalse($view['can_edit']); self::assertNotEmpty($view['edit_block_reason']);
        $this->actingAs(new User(['type' => 'admin', 'establishment_id' => 1]));
        self::assertTrue(\App\Services\Fiscal\DocumentEditPolicy::view($document)['can_edit']);
        $document->control_number = '00-00000002';
        self::assertFalse(\App\Services\Fiscal\DocumentEditPolicy::view($document)['can_edit']);
    }
    public function test_an_unauthenticated_caller_cannot_operate_on_fiscal_documents(): void
    {
        $this->expectException(HttpException::class);
        DocumentFiscalController::authorizeDocument(new Document(['establishment_id'=>1]));
    }
}
