<?php
namespace Tests\Unit;

use App\Models\Tenant\{Document, DocumentEmission};
use App\Services\Fiscal\DocumentEditPolicy;
use PHPUnit\Framework\TestCase;

class DocumentEditPolicyTest extends TestCase
{
    private function document(string $status, array $response = [], ?string $control = null): Document
    {
        $document = new Document;
        $document->setRawAttributes(['document_type_id' => '01', 'is_editable' => 0, 'state_type_id' => '01', 'control_number' => $control]);
        $emission = new DocumentEmission;
        $emission->setRawAttributes(['status' => $status, 'response' => json_encode($response)]);
        $document->setRelation('emission', $emission);
        return $document;
    }
    public function test_editing_depends_on_remote_registration_and_conciliation(): void
    {
        foreach (['not_requested', 'prepared', 'rejected'] as $status) {
            $document = $this->document($status);
            self::assertNull(DocumentEditPolicy::reason($document));
            self::assertTrue($document->is_editable);
        }
        foreach (['pending', 'uncertain', 'confirmed', 'cancelled'] as $status) {
            self::assertNotNull(DocumentEditPolicy::reason($this->document($status)));
            self::assertFalse($this->document($status)->is_editable);
        }
        self::assertNull(DocumentEditPolicy::reason($this->document('uncertain', ['retry_allowed' => true])));
        self::assertNotNull(DocumentEditPolicy::reason($this->document('uncertain', ['retry_allowed' => false])));
        self::assertNotNull(DocumentEditPolicy::reason($this->document('not_requested', [], '00-00000001')));
        $document = $this->document('not_requested'); $document->state_type_id = '11';
        self::assertNotNull(DocumentEditPolicy::reason($document));
    }
}
