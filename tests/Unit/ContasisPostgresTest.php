<?php
namespace Tests\Unit;

use App\CoreFacturalo\Facturalo;
use App\Support\Exact\Decimal;
use Modules\Account\Http\Controllers\AccountController;

/** @group postgres-integration */
final class ContasisPostgresTest extends \PHPUnit\Framework\TestCase
{
    private $fixture;

    protected function setUp(): void
    {
        $this->fixture=new class extends FiscalEmissionSchemaTest {
            public function start(): void {parent::setUp();}
            public function stop(): void {parent::tearDown();}
        };
        $this->fixture->start();
    }

    protected function tearDown(): void
    {
        try {if ($this->fixture) $this->fixture->stop();}
        finally {parent::tearDown();}
    }

    public function test_contasis_formats_persisted_exact_values_and_real_other_taxes(): void
    {
        [$db,,$input]=(new \ReflectionMethod($this->fixture,'bootHkaFixture'))->invoke($this->fixture,false);
        $db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $db->table('company_accounts')->updateOrInsert(['id'=>1],['subtotal_pen'=>'701','total_pen'=>'101','subtotal_usd'=>'702','total_usd'=>'102']);
        $input['payment_condition_id']='02';$input['payments']=[];
        $document=(new Facturalo())->save($input)->getDocument()->fresh();
        $values=[
            'total_exportation'=>'0.1',
            'total_taxed'=>Decimal::of(1)->dividedBy(3),
            'total_exonerated'=>'0.000000000001',
            // Its 12-place projection is 1.005, but the authoritative fraction rounds to 1.00.
            'total_igv'=>['n'=>'10049999999999999','d'=>'10000000000000000'],
            'total_other_taxes'=>Decimal::of(2)->dividedBy(7),
            'total'=>'123456789012345678.123456789012',
        ];
        $snapshot=$document->calculation_snapshot;
        $columns=[];
        foreach ($values as $field=>$value) {
            $snapshot['values'][$field]=Decimal::fraction($value);
            $columns[$field]=Decimal::represent($value,12,true);
        }
        $columns['calculation_snapshot']=json_encode($snapshot,JSON_THROW_ON_ERROR);
        $db->table('documents')->where('id',$document->id)->update($columns);
        $project=new \ReflectionMethod(AccountController::class,'getStructureContasis');
        foreach (['VES'=>['S','701','101'],'USD'=>['D','702','102']] as $currency=>[$label,$taxed,$total]) {
            $db->table('documents')->where('id',$document->id)->update(['currency_type_id'=>$currency]);
            $persisted=$document->fresh();
            $row=$project->invoke(new AccountController(),collect([$persisted]))->first();
            foreach (['total_exportation'=>'0.10','total_taxed'=>'0.33','total_exonerated'=>'0.00',
                'total_unaffected'=>'0.00','total_igv'=>'1.00','total_other_taxes'=>'0.29',
                'total'=>'123456789012345678.12'] as $field=>$expected) {
                self::assertSame($expected,$row[$field],$field);
            }
            self::assertSame($label,$row['currency']);
            self::assertSame($taxed,$row['account_taxed']);
            self::assertSame($total,$row['account_total']);
            self::assertSame('CRE',$row['payment_condition']);
            self::assertSame('005',$row['payment_method']);
            self::assertSame($columns['calculation_snapshot'],$db->table('documents')->where('id',$document->id)->value('calculation_snapshot'));
        }
        $attributes=$document->fresh()->getAttributes();
        unset($attributes['total_other_taxes']);
        $missing=json_decode($attributes['calculation_snapshot'],true,512,JSON_THROW_ON_ERROR);
        unset($missing['values']['total_other_taxes']);
        $attributes['calculation_snapshot']=json_encode($missing,JSON_THROW_ON_ERROR);
        $incomplete=$document->fresh()->setRawAttributes($attributes,true);
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Falta el operando exacto total_other_taxes');
        $project->invoke(new AccountController(),collect([$incomplete]));
    }
}
