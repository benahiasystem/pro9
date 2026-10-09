<?php
namespace Tests\Unit;

use App\Models\Tenant\Traits\HasFiscalIdentity;
use App\Services\Fiscal\FiscalIdentity;
use Illuminate\Database\Eloquent\Model;
use Tests\Support\SeriesDatabaseTestCase;

class FiscalIdentityTest extends SeriesDatabaseTestCase
{
    /** @dataProvider fullNumbersWithHyphens */
    public function test_full_number_keeps_hyphens_inside_the_series(string $reference, ?array $expected): void
    {
        self::assertSame($expected, FiscalIdentity::parseNumberFull($reference));
    }

    public static function fullNumbersWithHyphens(): array
    {
        return [
            ['ff-ab-01-000457', ['FF-AB-01', '000457']],
            ['-A--1--2', ['-A--1-', '2']],
            ['---1', ['--', '1']],
            ['FF01-1', ['FF01', '1']],
            ['457', ['', '457']],
            ['AB-CD123456789012345-1', ['AB-CD123456789012345', '1']],
            ['AB_CD-1', null], ['AB CD-1', null], ['AB.01-1', null],
            [str_repeat('A', 21).'-1', null], ['AB-1.5', null],
        ];
    }
    public function test_document_control_and_series_are_independent_direct_identifiers(): void
    {
        $this->db->table('documents')->insert([
            ['id' => 1, 'series' => 'FC01', 'number' => 16, 'control_number' => '00-00000021', 'fiscal_environment' => 'demo'],
            ['id' => 2, 'series' => 'FD01', 'number' => 21, 'control_number' => '00-00000016', 'fiscal_environment' => 'demo'],
        ]);
        $rows = SeriesIdentitySubject::all();
        $this->db->enableQueryLog();
        FiscalIdentity::preload($rows);
        self::assertSame('FC01-00000016', $rows[0]->fiscal_identity['number_full']);
        self::assertSame('00-00000021', $rows[0]->fiscal_identity['control_number']);
        self::assertSame([1], SeriesIdentitySubject::whereFiscalIdentifiers('FC01', 16, '00-21')->pluck('id')->all());
        self::assertSame([1], SeriesIdentitySubject::whereFiscalIdentifiers('FC01', '00000016')->pluck('id')->all());
        self::assertSame([], SeriesIdentitySubject::whereFiscalIdentifiers('FC01', 21, '00-21')->pluck('id')->all());
        self::assertNull((new \App\Models\Tenant\Document(['series' => 'FF01', 'number' => 100]))->fiscal_identity['control_number']);
        self::assertSame('FF01-00000100', (new \App\Models\Tenant\Document(['series' => 'FF01', 'number' => 100]))->number_full);
    }

    public function test_blank_series_keeps_number_control_and_branch_identity_independent(): void
    {
        foreach ([\App\Models\Tenant\Document::class, \App\Models\Tenant\Dispatch::class, \App\Models\Tenant\SaleNote::class, \Modules\Inventory\Models\Guide::class, \Modules\Inventory\Models\InventoryTransfer::class] as $class) {
            $document = new $class();
            $document->setRawAttributes(['series' => '', 'number' => 1, 'establishment_id' => 2, 'control_number' => '00-00000021']);
            self::assertSame('00000001', $document->number_full);
        }
        $company = (object) ['number' => 'J123456789'];
        $first = \App\CoreFacturalo\Requests\Inputs\Functions::filename($company, '01', '', 1, 1);
        $second = \App\CoreFacturalo\Requests\Inputs\Functions::filename($company, '01', null, 1, 2);
        self::assertNotSame($first, $second);
        self::assertSame('J123456789-01-FF01-1', \App\CoreFacturalo\Requests\Inputs\Functions::filename($company, '01', 'FF01', 1, 1));
        $this->db->table('documents')->insert(['series' => '', 'number' => 1, 'document_type_id' => '01', 'establishment_id' => 1, 'fiscal_environment' => 'demo']);
        \App\CoreFacturalo\Requests\Inputs\Functions::validateUniqueDocument('demo', '01', null, 1, SeriesIdentitySubject::class, 2);
        self::assertSame('00000001', \App\Services\Fiscal\FiscalIdentity::forDocument($document)['number_full']);
    }

    public function test_invalid_control_filter_is_rejected(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        SeriesIdentitySubject::whereFiscalIdentifiers(null, null, 'invalid')->get();
    }

    public function test_empty_series_filter_is_distinct_from_clearing_the_filter(): void
    {
        $this->db->table('documents')->insert([
            ['id' => 1, 'series' => '', 'number' => 1, 'establishment_id' => 1],
            ['id' => 2, 'series' => '', 'number' => 1, 'establishment_id' => 2],
            ['id' => 3, 'series' => 'FF01', 'number' => 1, 'establishment_id' => 1],
        ]);
        self::assertSame([1, 2], SeriesIdentitySubject::whereFiscalIdentifiers(FiscalIdentity::EMPTY_SERIES_FILTER)->pluck('id')->all());
        self::assertSame([1, 2, 3], SeriesIdentitySubject::whereFiscalIdentifiers(null)->orderBy('id')->pluck('id')->all());
        self::assertSame([1], SeriesIdentitySubject::whereFiscalIdentifiers(FiscalIdentity::EMPTY_SERIES_FILTER)->where('establishment_id', 1)->pluck('id')->all());
    }

    /** @dataProvider prepaymentSeries */
    public function test_prepayment_is_deducted_and_restored_only_in_its_branch(string $series): void
    {
        $this->db->statement('ALTER TABLE documents ADD COLUMN pending_amount_prepayment REAL');
        $this->db->statement('ALTER TABLE documents ADD COLUMN was_deducted_prepayment INTEGER DEFAULT 0');
        $this->db->table('documents')->insert([
            ['id' => 1, 'series' => $series, 'number' => 1, 'document_type_id' => '01', 'establishment_id' => 1, 'fiscal_environment' => 'demo', 'pending_amount_prepayment' => 20],
            ['id' => 2, 'series' => $series, 'number' => 1, 'document_type_id' => '01', 'establishment_id' => 2, 'fiscal_environment' => 'demo', 'pending_amount_prepayment' => 20],
        ]);
        \App\Models\Tenant\Document::addGlobalScope('identity_fixture_without_relations', fn ($query) => $query->setEagerLoads([]));
        $source = new \App\Models\Tenant\Document(['establishment_id' => 2, 'fiscal_environment' => 'demo']);
        $reference = ['number' => FiscalIdentity::numberFull($series, 1), 'document_type_id' => '02', 'total' => 20];
        $reflection = new \ReflectionClass(\App\CoreFacturalo\Facturalo::class);
        $facturalo = $reflection->newInstanceWithoutConstructor();
        $facturalo->setDocument($source);
        $deduct = $reflection->getMethod('updatePrepaymentDocuments');
        $deduct->setAccessible(true);
        $deduct->invoke($facturalo, ['prepayments' => [$reference]]);
        self::assertSame(20.0, (float) $this->db->table('documents')->where('id', 1)->value('pending_amount_prepayment'));
        self::assertSame(0.0, (float) $this->db->table('documents')->where('id', 2)->value('pending_amount_prepayment'));
        self::assertSame(1, (int) $this->db->table('documents')->where('id', 2)->value('was_deducted_prepayment'));
        $source->prepayments = [$reference];
        $provider = new \ReflectionClass(\Modules\Inventory\Providers\InventoryVoidedServiceProvider::class);
        $restore = $provider->getMethod('voidedWasDeductedPrepayment');
        $restore->setAccessible(true);
        $restore->invoke($provider->newInstanceWithoutConstructor(), $source);
        self::assertSame(20.0, (float) $this->db->table('documents')->where('id', 2)->value('pending_amount_prepayment'));
        self::assertSame(0, (int) $this->db->table('documents')->where('id', 2)->value('was_deducted_prepayment'));
    }

    public static function prepaymentSeries(): array
    {
        return [[''], ['FF-AB-01']];
    }
}
class SeriesIdentitySubject extends Model
{
    use HasFiscalIdentity;
    protected $connection = 'tenant';
    protected $table = 'documents';
}
