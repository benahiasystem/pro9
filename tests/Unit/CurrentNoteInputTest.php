<?php

namespace Tests\Unit;

use App\CoreFacturalo\Requests\Inputs\DocumentInput;
use App\CoreFacturalo\Requests\Inputs\DocumentUpdateInput;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CurrentNoteInputTest extends TestCase
{
    public function test_creation_and_update_keep_external_invoice_notes_in_the_invoice_group(): void
    {
        foreach ([DocumentInput::class, DocumentUpdateInput::class] as $input) {
            foreach (['07' => 'credit', '08' => 'debit'] as $id => $type) {
                $result = $this->note($input, (string) $id, '01');
                self::assertSame('01', $result['group_id']);
                self::assertSame($type, $result['note']['note_type']);
                self::assertNull($result['note']['affected_document_id']);
                self::assertSame('01', $result['note']['data_affected_document']['document_type_id']);
            }
        }
    }

    public function test_external_notes_reject_non_invoice_references(): void
    {
        foreach ([DocumentInput::class, DocumentUpdateInput::class] as $input) {
            foreach (['03', '80', '07', '99', null] as $affectedType) {
                try {
                    $this->note($input, '07', $affectedType);
                    self::fail('Una nota sólo puede afectar una Factura.');
                } catch (ValidationException $exception) {
                    self::assertArrayHasKey('document_type_id', $exception->errors());
                }
            }
        }
    }

    public function test_unsupported_document_types_fail_before_tenant_queries(): void
    {
        foreach ([DocumentInput::class, DocumentUpdateInput::class] as $input) {
            try {
                $input::set(['document_type_id' => '03']);
                self::fail('El procesamiento debe rechazar Boletas antes de consultar el tenant.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('document_type_id', $exception->errors());
            }
        }
    }

    private function note(string $input, string $type, ?string $affectedType): array
    {
        $method = new \ReflectionMethod($input, 'note');
        $method->setAccessible(true);

        return $method->invoke(null, [
            'document_type_id' => $type,
            'note_credit_or_debit_type_id' => '01',
            'note_description' => 'Ajuste de Factura',
            'affected_document_id' => null,
            'data_affected_document' => ['document_type_id' => $affectedType, 'series' => 'FF01', 'number' => '1'],
        ]);
    }
}
