<?php
namespace Tests\Unit;

use App\Models\Tenant\Traits\HasFiscalIdentity;
use App\Services\Fiscal\FiscalIdentity;
use Illuminate\Database\Eloquent\Model;
use Tests\Support\SeriesDatabaseTestCase;

class FiscalIdentityTest extends SeriesDatabaseTestCase
{
    public function test_document_control_and_series_are_independent_direct_identifiers(): void
    {
        $this->db->table('documents')->insert([
            ['id' => 1, 'series' => 'FC01', 'number' => 16, 'control_number' => '00-00000021', 'fiscal_environment' => 'demo'],
            ['id' => 2, 'series' => 'FD01', 'number' => 21, 'control_number' => '00-00000016', 'fiscal_environment' => 'demo'],
        ]);
        $rows = SeriesIdentitySubject::all();
        $this->db->enableQueryLog();
        FiscalIdentity::preload($rows);
        self::assertSame('FC01-16', $rows[0]->fiscal_identity['number_full']);
        self::assertSame('00-00000021', $rows[0]->fiscal_identity['control_number']);
        self::assertSame([1], SeriesIdentitySubject::whereFiscalIdentifiers('FC01', 16, '00-21')->pluck('id')->all());
        self::assertSame([], SeriesIdentitySubject::whereFiscalIdentifiers('FC01', 21, '00-21')->pluck('id')->all());
        self::assertNull((new \App\Models\Tenant\Document(['series' => 'FF01', 'number' => 100]))->fiscal_identity['control_number']);
        self::assertSame('FF01-100', (new \App\Models\Tenant\Document(['series' => 'FF01', 'number' => 100]))->number_full);
    }

    public function test_invalid_control_filter_is_rejected(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        SeriesIdentitySubject::whereFiscalIdentifiers(null, null, 'invalid')->get();
    }
}
class SeriesIdentitySubject extends Model
{
    use HasFiscalIdentity;
    protected $connection = 'tenant';
    protected $table = 'documents';
}
