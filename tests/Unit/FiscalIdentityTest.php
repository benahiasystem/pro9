<?php

namespace Tests\Unit;

use App\Models\Tenant\Traits\HasFiscalIdentity;
use App\Services\Fiscal\FiscalIdentity;
use Illuminate\Database\Eloquent\Model;
use Tests\Support\FiscalDatabaseTestCase;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
class FiscalIdentityTest extends FiscalDatabaseTestCase
{
    private $previousResolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousResolver = Model::getConnectionResolver();
        Model::setConnectionResolver(app('db'));
        $tenancy = $this->getMockBuilder(\Hyn\Tenancy\Database\Connection::class)->disableOriginalConstructor()->onlyMethods(['tenantName'])->getMock();
        $tenancy->method('tenantName')->willReturn('default');
        app()->instance(\Hyn\Tenancy\Database\Connection::class, $tenancy);
        $this->db->statement('CREATE TABLE documents (id INTEGER PRIMARY KEY, series TEXT, number INTEGER, establishment_id INTEGER)');
        $this->db->table('documents')->insert([
            ['id' => 1, 'series' => 'D', 'number' => 4, 'establishment_id' => 1],
            ['id' => 2, 'series' => '', 'number' => 8, 'establishment_id' => 1],
            ['id' => 3, 'series' => 'C', 'number' => 50, 'establishment_id' => 2],
        ]);
        $originalSequence = $this->repository->createSequence('01', 'D', 4, 1);
        $replacementSequence = $this->repository->createSequence('01', 'C', 50, 1);
        $original = $this->repository->reserve($originalSequence, 'sale', hash('sha256', 'sale'), 1);
        $child = $this->repository->reserve($replacementSequence, 'contingency-1', hash('sha256', 'physical'), 1);
        $this->db->table('fiscal_number_reservations')->where('id', $original->id)->update(['document_id' => 1, 'status' => 'contingency']);
        $this->db->table('fiscal_number_reservations')->where('id', $child->id)->update(['parent_reservation_id' => $original->id, 'status' => 'issued', 'control_number' => '00-00000002']);
    }

    protected function tearDown(): void
    {
        if ($this->previousResolver) Model::setConnectionResolver($this->previousResolver);
        else Model::unsetConnectionResolver();
        parent::tearDown();
    }

    public function test_effective_identity_preserves_commercial_columns_and_hides_internal_data(): void
    {
        $document = FiscalIdentitySubject::findOrFail(1);
        $identity = FiscalIdentity::forDocument($document);
        self::assertSame('C-50', $identity['number_full']);
        self::assertSame('00-00000002', $identity['control_number']);
        self::assertSame('D-4', $identity['original_number_full']);
        self::assertTrue($identity['contingency']);
        self::assertSame('D', $document->series);
        self::assertSame(4, $document->number);
        self::assertArrayNotHasKey('payload_fingerprint', $identity);
        self::assertArrayNotHasKey('operation_key', $identity);
    }

    public function test_batch_loading_does_not_issue_queries_per_row(): void
    {
        $documents = FiscalIdentitySubject::with('fiscalReservation')->get();
        $this->db->enableQueryLog();
        $this->db->flushQueryLog();
        $numbers = $documents->map(fn ($doc) => $doc->fiscal_identity['number_full'])->all();
        self::assertSame(['C-50', '8', 'C-50'], $numbers);
        self::assertCount(0, $this->db->getQueryLog());
        $this->db->disableQueryLog();
    }

    public function test_resource_wrappers_preload_without_per_row_queries(): void
    {
        $wrapped = FiscalIdentitySubject::all()->map(fn ($model) => new \Illuminate\Http\Resources\Json\JsonResource($model));
        FiscalIdentity::preload($wrapped);
        $this->db->enableQueryLog();
        $this->db->flushQueryLog();
        self::assertSame('C-50', $wrapped[0]->fiscal_identity['number_full']);
        self::assertSame('8', $wrapped[1]->fiscal_identity['number_full']);
        self::assertCount(0, $this->db->getQueryLog());
        $this->db->disableQueryLog();
    }

    public function test_persisted_document_accessor_displays_replacement_without_mutating_attributes(): void
    {
        $document = new \App\Models\Tenant\Document();
        $document->setRawAttributes(['id' => 1, 'series' => 'D', 'number' => 4]);
        $document->exists = true;
        self::assertSame('C-50', $document->number_full);
        self::assertSame('D', $document->series);
        self::assertSame(4, $document->number);
    }

    public function test_identity_and_filters_follow_an_inutilized_replacement_chain(): void
    {
        $contingency = $this->db->table('fiscal_number_reservations')->where('parent_reservation_id', 1)->first();
        $replacement = $this->repository->reserve(2, 'print-replacement-' . $contingency->id, hash('sha256', 'replacement'), 1);
        $this->db->table('fiscal_number_reservations')->where('id', $contingency->id)->update(['status' => 'inutilized']);
        $this->db->table('fiscal_number_reservations')->where('id', $replacement->id)->update([
            'parent_reservation_id' => $contingency->id, 'status' => 'issued', 'control_number' => '00-00000003',
        ]);

        $document = FiscalIdentitySubject::findOrFail(1);
        FiscalIdentity::preload([$document]);
        self::assertSame('C-51', $document->fiscal_identity['number_full']);
        self::assertSame('00-00000003', $document->fiscal_identity['control_number']);
        self::assertSame('D-4', $document->fiscal_identity['original_number_full']);
        self::assertSame([1], FiscalIdentitySubject::whereFiscalIdentifiers('C', 51, '00-3')->pluck('id')->all());
        self::assertSame([], FiscalIdentitySubject::whereFiscalIdentifiers(null, null, '00-2')->pluck('id')->all());
    }

    public function test_filter_uses_physical_identity_and_preserves_establishment_scope(): void
    {
        self::assertSame([1], FiscalIdentitySubject::where('establishment_id', 1)->whereFiscalIdentifiers('C', 50)->pluck('id')->all());
        self::assertSame([], FiscalIdentitySubject::whereFiscalIdentifiers('D', 4)->pluck('id')->all());
        self::assertSame([1], FiscalIdentitySubject::whereFiscalIdentifiers(null, null, '00-2')->pluck('id')->all());
        self::assertSame([], FiscalIdentitySubject::whereFiscalIdentifiers('D', 50, '00-2')->pluck('id')->all());
        self::assertSame([], FiscalIdentitySubject::where('establishment_id', 2)->whereFiscalIdentifiers(null, null, '00-2')->pluck('id')->all());
    }

    public function test_report_groups_include_empty_and_effective_series_without_legacy_catalog(): void
    {
        $records = FiscalIdentitySubject::with('fiscalReservation')->get();
        self::assertSame([['number' => 'C'], ['number' => '']], FiscalIdentity::seriesForType($records, '01')->all());
        self::assertSame([], FiscalIdentity::seriesForType($records, '07')->all());
    }

    public function test_invalid_control_is_a_validation_error_instead_of_a_server_error(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        FiscalIdentitySubject::whereFiscalIdentifiers(null, null, 'invalid')->get();
    }

    public function test_unreserved_commercial_number_remains_searchable_without_inventing_control(): void
    {
        $identity = FiscalIdentitySubject::findOrFail(2)->fiscal_identity;
        self::assertSame('8', $identity['number_full']);
        self::assertNull($identity['control_number']);
        self::assertSame([2], FiscalIdentitySubject::whereFiscalIdentifiers(null, 8)->pluck('id')->all());
        self::assertSame([], FiscalIdentitySubject::whereFiscalIdentifiers(null, 8, '00-2')->pluck('id')->all());
    }

    public function test_real_models_share_presentation_contract_without_changing_unsaved_values(): void
    {
        foreach ([new \App\Models\Tenant\Document(), new \App\Models\Tenant\Dispatch()] as $document) {
            $document->setRawAttributes(['series' => '', 'number' => 25]);
            self::assertSame('25', $document->number_full);
        }
    }
}

class FiscalIdentitySubject extends Model
{
    use HasFiscalIdentity;
    public function getDocumentType() { return (object) ['id' => '01']; }
    protected $table = 'documents';
    public $timestamps = false;
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
