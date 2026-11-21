<?php
namespace Tests\Unit;

final class PostgresJunitTest extends \PHPUnit\Framework\TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        require_once dirname(__DIR__,2).'/scripts/postgresql_junit.php';
        $this->directory=sys_get_temp_dir().'/sab-junit-'.bin2hex(random_bytes(8));
        mkdir($this->directory,0700,true);
    }

    protected function tearDown(): void
    {
        (new \Illuminate\Filesystem\Filesystem())->deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_merging_preserves_every_failure_error_skip_and_assertion(): void
    {
        $one=$this->write('performance','<testsuite><testcase class="Performance" name="p95" assertions="17" time="1.25"><failure message="budget">Details</failure></testcase></testsuite>');
        $two=$this->write('functional','<testsuite><testsuite><testcase class="Unit" name="success" assertions="8" time="0.5"/><testcase class="Unit" name="error" assertions="0" time="0.25"><error message="failure">Trace</error></testcase><testcase class="Unit" name="skip" assertions="0" time="0"><skipped/></testcase></testsuite></testsuite>');
        $totals=\postgresqlMergeJunit(['performance'=>$one,'functional'=>$two],$this->directory.'/combined.xml');
        self::assertSame(['tests'=>4,'assertions'=>25,'errors'=>1,'warnings'=>0,'failures'=>1,'skipped'=>1,'time'=>2.0],$totals);
        $xml=new \DOMDocument();$xml->load($this->directory.'/combined.xml');
        self::assertSame(4,$xml->getElementsByTagName('testcase')->length);
        self::assertSame('Details',$xml->getElementsByTagName('failure')->item(0)->textContent);
        self::assertSame('Trace',$xml->getElementsByTagName('error')->item(0)->textContent);
        self::assertSame('performance',$xml->getElementsByTagName('testsuite')->item(1)->getAttribute('name'));
        self::assertSame('functional',$xml->getElementsByTagName('testsuite')->item(2)->getAttribute('name'));
    }

    /** @dataProvider invalidPhases */
    public function test_incomplete_malformed_or_duplicate_phases_cannot_pass(string $second): void
    {
        $one=$this->write('performance','<testsuite><testcase class="Test" name="same" assertions="1" time="1"/></testsuite>');
        $two=$second==='missing' ? $this->directory.'/missing.xml' : $this->write('functional',$second);
        $this->expectException(\RuntimeException::class);
        \postgresqlMergeJunit(['performance'=>$one,'functional'=>$two],$this->directory.'/combined.xml');
    }

    public static function invalidPhases(): array
    {
        return [
            'missing'=>['missing'],
            'empty'=>['<testsuite tests="1"/>'],
            'malformed'=>['<testsuite>'],
            'duplicate'=>['<testsuite><testcase class="Test" name="same" assertions="2" time="1"/></testsuite>'],
            'invalid metrics'=>['<testsuite><testcase class="Test" name="new" assertions="1" time="-1"/></testsuite>'],
            'doctype'=>['<!DOCTYPE testsuite [<!ENTITY value "bad">]><testsuite><testcase class="Test" name="new" assertions="1" time="1"/></testsuite>'],
        ];
    }

    public function test_discovery_preserves_dataset_names_and_only_excludes_declared_groups(): void
    {
        $path=$this->write('discovery','<tests><testCaseClass name="Unit"><testCaseMethod name="normal" groups="unit"/>' .
            '<testCaseMethod name="decimal" groups="exact" dataSet="&quot;tercio ñ&quot;"/>' .
            '<testCaseMethod name="browser" groups="browser-fixture,unit"/></testCaseClass>' .
            '<testCaseClass name="Performance"><testCaseMethod name="p95" groups="postgres-performance"/></testCaseClass>' .
            '<testCaseClass name="Accounting"><testCaseMethod name="posting" groups="postgres-integration"/>' .
            '<testCaseMethod name="upload" groups="postgres-integration,accounting-attachments"/></testCaseClass></tests>');
        $excluded = ['browser-fixture', 'accounting-attachments'];
        $inventory=\postgresqlTestInventory($path,$excluded);
        self::assertSame(['Accounting::posting','Performance::p95','Unit::decimal with data set "tercio ñ"','Unit::normal'],array_keys($inventory));
        self::assertSame(['postgres-performance'],$inventory['Performance::p95']['groups']);
        self::assertCount(3,\postgresqlTestInventory($path,[...$excluded,'postgres-performance']));
    }

    public function test_invoice_currency_selection_keeps_ves_datasets_and_shared_phase_filters(): void
    {
        $fixture=$this->write('invoice-currency','<tests><testCaseClass name="Fiscal">' .
            '<testCaseMethod name="invoice" dataSet="&quot;VES&quot;"/><testCaseMethod name="invoice" dataSet="&quot;USD&quot;"/>' .
            '</testCaseClass><testCaseClass name="General"><testCaseMethod name="oldInvoice" dataSet="#0"/>' .
            '<testCaseMethod name="oldInvoice" dataSet="#1"/><testCaseMethod name="oldInvoiceExtra"/><testCaseMethod name="mixedReceipt"/>' .
            '</testCaseClass><testCaseClass name="Legacy"><testCaseMethod name="fixture"/></testCaseClass></tests>');
        $inventory=\postgresqlTestInventory($fixture,[]);
        $selection=\postgresqlVesTestSelection($inventory,['Fiscal::invoice with data set "USD"','General::oldInvoice','Legacy::*']);
        self::assertSame(['Fiscal::invoice with data set "VES"','General::mixedReceipt','General::oldInvoiceExtra'],array_keys($selection['included']));
        self::assertCount(4,$selection['excluded']);
        $fiscal=\postgresqlExcludeCases('~^Fiscal::~',$selection['excluded']);
        $general=\postgresqlExcludeCases('~^(?!Fiscal::)~',$selection['excluded']);
        foreach ($inventory as $id=>$record) {
            $isFiscal=$record['class']==='Fiscal';
            self::assertSame(isset($selection['included'][$id]) && $isFiscal, preg_match($fiscal,$id)===1, $id);
            self::assertSame(isset($selection['included'][$id]) && !$isFiscal, preg_match($general,$id)===1, $id);
        }
    }

    public function test_many_dataset_exclusions_keep_the_phase_filter_within_pcre_limits(): void
    {
        $class = 'Tests\Unit\FiscalEmissionSchemaTest';
        $name = 'test_existing_documents_keep_their_original_taxable_base_and_currency';
        $excluded = [];
        for ($index = 0; $index < 400; $index++) {
            $dataset = $name.' with data set #'.$index;
            $excluded[$class.'::'.$dataset] = ['class' => $class, 'name' => $dataset];
        }

        $filter = \postgresqlExcludeCases(\postgresqlFunctionalFilters()['fiscal'], $excluded);
        foreach ($excluded as $id => $case) {
            self::assertSame(0, preg_match($filter, $id), $id);
        }
        self::assertSame(1, preg_match($filter, $class.'::'.$name.' with data set #400'));
        self::assertSame(0, preg_match($filter, 'Tests\Unit\OtherTest::'.$name));
    }

    public function test_missing_invoice_currency_selector_invalidates_the_inventory(): void
    {
        $inventory=['Fiscal::old'=>['class'=>'Fiscal','name'=>'old','groups'=>[]]];
        $this->expectException(\RuntimeException::class);
        \postgresqlVesTestSelection($inventory,['Fiscal::old with data set "USD"']);
    }

    public function test_coverage_requires_every_discovered_case_and_keeps_original_failures_and_assertions(): void
    {
        $one=$this->write('one','<testsuite><testcase class="Unit" name="one" assertions="2" time="0.5"/></testsuite>');
        $two=$this->write('two','<testsuite><testcase class="Unit" name="two with data set #0" assertions="3" time="0.25"><failure>Original failure</failure></testcase></testsuite>');
        $inventory=['Unit::one'=>['class'=>'Unit','name'=>'one','groups'=>[]],
            'Unit::two with data set #0'=>['class'=>'Unit','name'=>'two with data set #0','groups'=>[]]];
        $totals=\postgresqlMergeJunit(['one'=>$one,'two'=>$two],$this->directory.'/covered.xml',$inventory);
        self::assertSame(2,$totals['tests']);self::assertSame(5,$totals['assertions']);self::assertSame(1,$totals['failures']);
        self::assertStringContainsString('Original failure',file_get_contents($this->directory.'/covered.xml'));
    }

    /** @dataProvider coverageErrors */
    public function test_a_missing_unexpected_or_renamed_dataset_cannot_pass_coverage(array $expected): void
    {
        $one=$this->write('one','<testsuite><testcase class="Unit" name="one" assertions="2" time="0.5"/></testsuite>');
        $this->expectException(\RuntimeException::class);
        \postgresqlMergeJunit(['one'=>$one],$this->directory.'/invalid-coverage.xml',$expected);
    }

    public static function coverageErrors(): array
    {
        return ['missing discovered case'=>[['Unit::one'=>[],'Unit::missing'=>[]]],
            'unexpected result'=>[['Unit::different'=>[]]],'wrong dataset'=>[['Unit::one with data set #0'=>[]]],
            'empty inventory'=>[[]]];
    }

    /** @dataProvider invalidDiscovery */
    public function test_invalid_or_duplicate_discovery_inputs_fail_closed(string $body): void
    {
        $this->expectException(\RuntimeException::class);
        \postgresqlTestInventory($this->write('invalid-discovery',$body),[]);
    }

    public static function invalidDiscovery(): array
    {
        return ['empty'=>['<tests/>'],'malformed'=>['<tests>'],'wrong root'=>['<testsuite/>'],
            'missing class'=>['<tests><testCaseClass><testCaseMethod name="test" groups=""/></testCaseClass></tests>'],
            'missing method'=>['<tests><testCaseClass name="Unit"><testCaseMethod groups=""/></testCaseClass></tests>'],
            'empty dataset'=>['<tests><testCaseClass name="Unit"><testCaseMethod name="one" groups="" dataSet=""/></testCaseClass></tests>'],
            'duplicate'=>['<tests><testCaseClass name="Unit"><testCaseMethod name="one" groups=""/><testCaseMethod name="one" groups=""/></testCaseClass></tests>'],
            'unsupported'=>['<tests><phptFile path="unknown"/></tests>'],
            'doctype'=>['<!DOCTYPE tests [<!ENTITY value "bad">]><tests><testCaseClass name="Unit"><testCaseMethod name="one" groups=""/></testCaseClass></tests>']];
    }

    /** @dataProvider functionalFilterCases */
    public function test_functional_filters_partition_class_and_dataset_names_once(string $name,string $phase): void
    {
        $matches=[];foreach(\postgresqlFunctionalFilters() as $label=>$filter)if(preg_match($filter,$name))$matches[]=$label;
        self::assertSame([$phase],$matches);
    }

    public static function functionalFilterCases(): array
    {
        return [['Tests\\Unit\\FiscalEmissionSchemaTest::test_invoice','fiscal'],
            ['Tests\\Unit\\FiscalEmissionSchemaTest::test_invoice with data set "ñ"','fiscal'],
            ['Tests\\Unit\\FiscalEmissionSchemaTestExtra::test_invoice','general'],
            ['Tests\\Unit\\FiscalFixtureLifecycleTest::test_restore','general'],
            ['Tests\\Feature\\ExampleTest::test_case','general'],
            ['Modules\\Item\\Services\\ProductTest::test_case','general'],
            ['Modules\\Accounting\\Tests\\CoreIntegrationTest::test_entry','accounting'],
            ['Modules\\Accounting\\Tests\\TenantDBIsolationTest::test_snapshot with data set "á"','accounting'],
            ['Modules\\Accounting\\Services\\OtherTest::test_case','general']];
    }

    private function write(string $name,string $body): string
    {
        $path=$this->directory.'/'.$name.'.xml';
        file_put_contents($path,'<?xml version="1.0" encoding="UTF-8"?>'.$body);
        return $path;
    }
    public function test_explicit_pos_deferral_preserves_every_remaining_case_and_failure(): void
    {
        [$all,$functional]=self::posDeferralInventories();
        $policy=\postgresqlDeferPosPerformance($all,$functional);
        $performance=array_diff_key($all,$functional);
        $pos=array_filter($performance,static fn ($case) => in_array('postgres-performance-pos', $case['groups'], true));
        $required=array_diff_key($performance,$pos);
        self::assertCount(1,$pos);
        self::assertSame($functional+$required,$policy['mandatory']);
        self::assertSame($pos,$policy['deferred']);
        $xml=$this->write('remaining','<testsuite><testcase class="Unit" name="one" assertions="2" time="0.1"/>'.
            '<testcase class="Unit" name="two with data set &quot;tercio ñ&quot;" assertions="3" time="0.2"/></testsuite>');
        $benchmark=reset($required);
        $measured=$this->write('performance','<testsuite><testcase class="'.$benchmark['class'].'" name="'.$benchmark['name'].'"'.
            ' assertions="1" time="0.3"><failure>Still mandatory</failure></testcase></testsuite>');
        $counts=\postgresqlMergeJunit(['functional'=>$xml,'performance'=>$measured],$this->directory.'/deferred.xml',$policy['mandatory']);
        self::assertSame(3,$counts['tests']);self::assertSame(6,$counts['assertions']);
        self::assertSame(1,$counts['failures']);self::assertSame(0,$counts['skipped']);
        self::assertStringContainsString('Still mandatory',file_get_contents($this->directory.'/deferred.xml'));
    }

    /** @dataProvider invalidPosDeferrals */
    public function test_pos_deferral_cannot_hide_any_other_case_or_change_its_identity(array $all,array $functional): void
    {
        $this->expectException(\RuntimeException::class);
        \postgresqlDeferPosPerformance($all,$functional);
    }

    private static function posDeferralInventories(): array
    {
        $functional=['Unit::one'=>['class'=>'Unit','name'=>'one','groups'=>['unit']],
            'Unit::two with data set "tercio ñ"'=>['class'=>'Unit','name'=>'two with data set "tercio ñ"','groups'=>['exact']]];
        $class='Tests\\Unit\\PostgresPerformanceTest';
        $regular='test_http_and_sales_report_p95_matches_the_postgresql_tenancy2_reference';
        $pos='test_pos_catalog_p95_matches_the_postgresql_tenancy2_reference';
        return [$functional+[
            $class.'::'.$regular=>['class'=>$class,'name'=>$regular,'groups'=>['postgres-performance']],
            $class.'::'.$pos=>['class'=>$class,'name'=>$pos,'groups'=>['postgres-performance','postgres-performance-pos']],
        ],$functional];
    }

    public static function invalidPosDeferrals(): array
    {
        [$all,$functional]=self::posDeferralInventories();
        [$regular,$pos]=array_keys(array_diff_key($all,$functional));
        $withoutOne=$functional;unset($withoutOne['Unit::one']);
        $more=$all+['OtherPerformance::case'=>['class'=>'OtherPerformance','name'=>'case','groups'=>['postgres-performance']]];
        $wrongGroup=$all;$wrongGroup[$pos]['groups']=['postgres-performance'];
        $wrongRegularGroup=$all;$wrongRegularGroup[$regular]['groups'][]='postgres-performance-pos';
        $renamed=$all;unset($renamed[$pos]);$renamed[$pos.' changed']=$all[$pos];
        $unexpected=$functional+['Unit::new'=>['class'=>'Unit','name'=>'new','groups'=>[]]];
        $changed=$functional;$changed['Unit::one']['name']='other';
        return ['missing declared POS case'=>[array_diff_key($all,[$pos=>true]),$functional],
            'missing HTTP and report case'=>[array_diff_key($all,[$regular=>true]),$functional],
            'remaining functional case removed'=>[$all,$withoutOne],
            'additional performance case removed'=>[$more,$functional],
            'wrong declared group'=>[$wrongGroup,$functional],
            'HTTP and report cannot share the exclusion group'=>[$wrongRegularGroup,$functional],
            'renamed POS identity'=>[$renamed,$functional],
            'unexpected mandatory case'=>[$all,$unexpected],
            'changed mandatory metadata'=>[$all,$changed]];
    }

}
