<?php

namespace Tests\Unit;

use App\CoreFacturalo\Requests\Api\Validation\Functions;
use App\CoreFacturalo\Requests\Inputs\DocumentInput;
use App\CoreFacturalo\Requests\Inputs\DocumentUpdateInput;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CurrentLocalDocumentReferenceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.connections.tenant', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);
        Schema::connection('tenant')->create('documents', function ($table): void {
            $table->increments('id');
            $table->string('external_id');
            $table->string('document_type_id');
            $table->string('group_id');
            $table->date('date_of_issue');
        });
        foreach (['users', 'fiscal_environments', 'state_types', 'cat_document_types', 'cat_currency_types', 'groups', 'document_items', 'invoices', 'notes', 'document_payments', 'document_fee'] as $name) {
            Schema::connection('tenant')->create($name, function ($table): void {
                $table->increments('id');
                $table->unsignedInteger('document_id')->nullable();
            });
        }
        DB::connection('tenant')->table('documents')->insert([
            ['id' => 1, 'external_id' => 'invoice', 'document_type_id' => '01', 'group_id' => '01', 'date_of_issue' => '2026-09-10'],
            ['id' => 2, 'external_id' => 'invalid', 'document_type_id' => '03', 'group_id' => '02', 'date_of_issue' => '2026-09-10'],
        ]);
    }

    public function test_notes_keep_the_local_invoice_reference(): void
    {
        foreach ([DocumentInput::class, DocumentUpdateInput::class] as $input) {
            foreach (['07', '08'] as $type) {
                $result = $this->note($input, 1, $type);
                self::assertSame(1, $result['note']['affected_document_id']);
                self::assertSame('01', $result['group_id']);
            }
        }
    }

    public function test_notes_reject_local_non_invoice_documents_and_missing_references(): void
    {
        foreach ([DocumentInput::class, DocumentUpdateInput::class] as $input) {
            foreach ([2 => ValidationException::class, 999 => ModelNotFoundException::class] as $id => $exceptionClass) {
                try {
                    $this->note($input, $id, '07');
                    self::fail('La referencia inválida debe rechazarse.');
                } catch (\Exception $exception) {
                    self::assertInstanceOf($exceptionClass, $exception);
                }
            }
        }
    }

    public function test_voiding_resolves_only_the_invoice_group_and_matching_date(): void
    {
        $payload = ['date_of_reference' => '2026-09-10', 'documents' => [['external_id' => 'invoice', 'description' => 'Anulación local']]];
        self::assertSame([['document_id' => 1, 'description' => 'Anulación local']], Functions::voidedDocuments($payload));
        foreach ([['external_id' => 'invalid', 'date' => '2026-09-10'], ['external_id' => 'invoice', 'date' => '2026-09-09']] as $invalid) {
            $payload['documents'][0]['external_id'] = $invalid['external_id'];
            $payload['date_of_reference'] = $invalid['date'];
            try {
                Functions::voidedDocuments($payload);
                self::fail('No debe resolver un documento de otro grupo o fecha.');
            } catch (\Exception $exception) {
                self::assertStringContainsString('no fue encontrado', $exception->getMessage());
            }
        }
    }

    private function note(string $input, int $id, string $type): array
    {
        $method = new \ReflectionMethod($input, 'note');
        $method->setAccessible(true);
        return $method->invoke(null, [
            'document_type_id' => $type, 'note_credit_or_debit_type_id' => '01',
            'note_description' => 'Ajuste', 'affected_document_id' => $id,
        ]);
    }
}
