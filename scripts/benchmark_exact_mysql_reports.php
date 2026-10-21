<?php
// ######## INICIO RENDIMIENTO CALCULOS EXACTOS PRO9 ########
/** Backend/MySQL comparison. It deliberately does not certify HTTP, POS or cash flows. */
require dirname(__DIR__).'/vendor/autoload.php';

use App\Support\Exact\{Decimal as D, Payables};

if (getenv('PRO9_FISCAL_MYSQL_TESTS') !== '1') throw new RuntimeException('Requiere PRO9_FISCAL_MYSQL_TESTS=1; sólo crea bases pro9_fiscal_test_* temporales.');
$root = dirname(__DIR__);
$baseline = 'cab49e553230e48d3cf962261e5f6c961f6ffb13';
$samples = 30;
foreach ($argv as $arg) {
    if (str_starts_with($arg,'--baseline-sha=')) $baseline = substr($arg,15);
    if (str_starts_with($arg,'--samples=')) $samples = (int)substr($arg,10);
}
if (!preg_match('/^[a-f0-9]{40}$/D',$baseline) || $samples < 3 || $samples > 100) throw new InvalidArgumentException('SHA completo y entre 3 y 100 muestras.');
$sourceHashes = [];
foreach (['Decimal','Payables'] as $class) {
    $path = 'app/Support/Exact/'.$class.'.php';
    $pipes = [];
    $process = proc_open(['git','show',$baseline.':'.$path],[1=>['pipe','w'],2=>['pipe','w']],$pipes,$root);
    if (!is_resource($process)) throw new RuntimeException('No se pudo leer la referencia Git.');
    $source = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);
    if (proc_close($process) !== 0) throw new RuntimeException($error);
    $sourceHashes[$path] = ['baseline'=>hash('sha256',$source),'current'=>hash_file('sha256',$root.'/'.$path)];
    $source = str_replace('namespace App\\Support\\Exact;',
        'namespace Pro9ExactBenchmark; use App\\Support\\Exact\\ReportDataset;', $source);
    eval(substr($source,5)); // Immutable local Git code, isolated namespace; never a tenant input.
}
$dependencies = [];
foreach (['app/Support/Exact/OutstandingSummary.php','app/Support/Exact/ReadSnapshot.php','app/Support/Exact/ReportDataset.php',
    'app/Services/ExchangeRates/ExchangeRateMath.php','tests/Unit/FiscalEmissionSchemaTest.php','composer.lock',
    'scripts/benchmark_exact_mysql_reports.php'] as $path) $dependencies[$path] = hash_file('sha256',$root.'/'.$path);

$fixture = new class extends \Tests\Unit\FiscalEmissionSchemaTest {
    public bool $ready = false;
    public function start(): array { parent::setUp();$this->ready=true;return (new ReflectionMethod(parent::class,'bootHkaFixture'))->invoke($this); }
    public function stop(): void { parent::tearDown(); }
};
try {
    [$db,$app,$input] = $fixture->start();
    $db->disableQueryLog();
    $counter = 0;
    $db->listen(static function () use (&$counter) { $counter++; });
    $report = ['baseline_git_commit'=>$baseline,'runtime'=>PHP_VERSION,'mysql_version'=>$db->selectOne('SELECT VERSION() AS version')->version,
        'scope'=>'Backend de cuentas por pagar con MySQL temporal y esquema/semillas completos; sin transporte HTTP, middleware, POS ni caja.',
        'samples'=>$samples,'minimum_30_samples_met'=>$samples >= 30,'warmups'=>3,
        'source_sha256'=>$sourceHashes,'current_dependencies_sha256'=>$dependencies,'cases'=>[]];
    foreach ([1000,10000] as $count) {
        $db->table('purchase_payments')->delete();$db->table('purchases')->delete();
        // A fixture identity may act as supplier; no external entity is created or contacted.
        $supplier = $db->table('persons')->where('id',$input['customer_id'])->first();
        if (!$supplier) throw new RuntimeException('Falta identidad de prueba.');
        for ($offset=0;$offset<$count;$offset+=500) {
            $purchases = $payments = [];
            for ($i=$offset;$i<min($count,$offset+500);$i++) {
                $id = $i+1;
                $purchases[] = ['id'=>$id,'user_id'=>$input['user_id'],'establishment_id'=>$input['establishment_id'],
                    'external_id'=>(string)\Illuminate\Support\Str::uuid(),'fiscal_environment'=>'demo','state_type_id'=>'01',
                    'group_id'=>'01','document_type_id'=>'01','series'=>'BC01','number'=>$id,'date_of_issue'=>'2026-10-19',
                    'time_of_issue'=>'12:00:00','supplier_id'=>$supplier->id,'supplier'=>json_encode((array)$supplier,JSON_THROW_ON_ERROR),
                    'currency_type_id'=>'VES','exchange_rate_sale'=>'1.00000000','total'=>'500','total_payable'=>'500',
                    'calculation_snapshot'=>json_encode(D::snapshot(['total'=>'500','total_payable'=>'500']),JSON_THROW_ON_ERROR)];
                $payments[] = ['purchase_id'=>$id,'date_of_payment'=>'2026-10-19','payment_method_type_id'=>'01',
                    'payment'=>'0.333333333333','calculation_snapshot'=>json_encode(D::snapshot(['payment'=>'1/3']),JSON_THROW_ON_ERROR)];
            }
            $db->table('purchases')->insert($purchases);$db->table('purchase_payments')->insert($payments);
        }
        $runs = [
            'baseline'=>static function () { $rows=\Pro9ExactBenchmark\Payables::dataset([])->filter(fn($row)=>D::readRequired($row,'total_to_pay')->isPositive());
                return ['summary'=>\Pro9ExactBenchmark\Payables::summary($rows),'records'=>$rows->paginate(50,1)->items(),'total'=>count($rows)]; },
            'current'=>static function () { $rows=Payables::dataset([])->filter(fn($row)=>D::readRequired($row,'total_to_pay')->isPositive());
                return ['summary'=>Payables::summary($rows),'records'=>$rows->paginate(50,1)->items(),'total'=>count($rows)]; },
        ];
        foreach ($runs as $run) for ($warm=0;$warm<3;$warm++) $run();
        $measurements = $queries = $memory = [];
        for ($sample=0;$sample<$samples;$sample++) {
            // Alternate order to avoid assigning cache/temperature effects to one implementation.
            foreach (($sample % 2 ? array_reverse($runs,true) : $runs) as $name=>$run) {
                $counter=0;
                if (function_exists('memory_reset_peak_usage')) memory_reset_peak_usage();
                $start=hrtime(true);$result=$run();json_encode($result,JSON_THROW_ON_ERROR);
                $measurements[$name][]=(hrtime(true)-$start)/1000000;
                $queries[$name][]=$counter;$memory[$name][]=memory_get_peak_usage(true);
                if ($result['total'] !== $count || !D::readRequired($result['summary'],'VES_balance')->isEqualTo(D::of('1499/3')->multipliedBy($count))) throw new LogicException('La medición no conservó el saldo exacto.');
                unset($result);
            }
        }
        $case = [];
        foreach ($measurements as $name=>$times) {
            sort($times);$case[$name]=['p50_ms'=>$times[(int)ceil(count($times)*0.5)-1],
                'p95_ms'=>$times[(int)ceil(count($times)*0.95)-1],'queries_max'=>max($queries[$name]),
                'memory_peak_bytes'=>max($memory[$name]),'raw_ms'=>$measurements[$name]];
        }
        $case['allowed_p95_ms']=$case['baseline']['p95_ms']+max($case['baseline']['p95_ms']*0.2,100);
        $case['passed']=$case['current']['p95_ms'] <= $case['allowed_p95_ms'];
        $report['cases'][(string)$count]=$case;
        fwrite(STDERR,'Medidas '.$count.' operaciones con '.$samples.' muestras.'.PHP_EOL);
    }
    foreach ($sourceHashes as $path=>$hashes) if (hash_file('sha256',$root.'/'.$path) !== $hashes['current']) throw new RuntimeException('Cambió una fuente medida: '.$path);
    foreach ($dependencies as $path=>$hash) if (hash_file('sha256',$root.'/'.$path) !== $hash) throw new RuntimeException('Cambió una dependencia medida: '.$path);
    $report['sources_stable_during_measurement']=true;
    echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
    $exit = array_filter($report['cases'],static fn($case)=>!$case['passed']) ? 1 : 0;
} catch (Throwable $exception) {
    fwrite(STDERR,get_class($exception).': '.$exception->getMessage().PHP_EOL);$exit=2;
} finally { if ($fixture->ready) $fixture->stop(); }
exit($exit);
// ######## FIN RENDIMIENTO CALCULOS EXACTOS PRO9 ########
