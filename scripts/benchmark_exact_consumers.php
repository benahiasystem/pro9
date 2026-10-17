<?php
// ######## INICIO RENDIMIENTO CALCULOS EXACTOS PRO9 ########
require dirname(__DIR__).'/vendor/autoload.php';

use App\Support\Exact\Decimal;
use Illuminate\Support\Facades\{DB, Schema};

// Local comparison of backend consumers, not an HTTP/POS or MySQL latency certificate.
$root = dirname(__DIR__);
foreach (['CACHE_DRIVER'=>'array','SESSION_DRIVER'=>'array','QUEUE_CONNECTION'=>'sync'] as $key=>$value) {
    putenv($key.'='.$value); $_ENV[$key]=$value; $_SERVER[$key]=$value;
}
try {
$app = require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
foreach (['system','tenant'] as $name) {
    DB::purge($name);
    config(['database.connections.'.$name=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'']]);
}
config(['database.default'=>'tenant']);
auth()->setUser((new \App\Models\Tenant\User())->forceFill(['id'=>1,'type'=>'admin']));
$db = DB::connection('tenant');
$db->getPdo()->sqliteCreateFunction('DATE_FORMAT', static fn($value,$format)=>$value === null ? null : (new DateTime($value))->format(strtr($format,['%Y'=>'Y','%m'=>'m','%d'=>'d'])));
$db->getPdo()->sqliteCreateFunction('CONCAT', static fn(...$parts)=>implode('', $parts));
Schema::connection('tenant')->create('persons', function ($t) { $t->increments('id'); $t->string('name'); });
$db->table('persons')->insert(['id'=>1,'name'=>'Benchmark']);
foreach (['purchases','expenses'] as $table) Schema::connection('tenant')->create($table, function ($t) {
    $t->increments('id'); $t->integer('supplier_id')->default(1); $t->integer('user_id')->default(1); $t->integer('establishment_id')->default(1);
    $t->string('state_type_id')->default('01'); $t->string('document_type_id')->default('01'); $t->date('date_of_issue')->default('2026-10-17');
    $t->date('date_of_due')->nullable(); $t->string('series')->default('F'); $t->integer('number')->default(1);
    $t->text('total'); $t->text('total_payable')->nullable(); $t->json('calculation_snapshot')->nullable();
    $t->string('currency_type_id')->default('VES'); $t->text('exchange_rate_sale')->default('3.00000000');
});
foreach (['purchase_payments'=>'purchase_id','expense_payments'=>'expense_id'] as $table=>$foreign) Schema::connection('tenant')->create($table, function ($t) use ($foreign) {
    $t->increments('id'); $t->integer($foreign); $t->text('payment'); $t->date('date_of_payment'); $t->json('calculation_snapshot')->nullable();
});
$baselineRef = 'HEAD';
foreach ($argv as $arg) if (str_starts_with($arg, '--baseline-sha=')) {
    $baselineRef = substr($arg, 15);
    if (!preg_match('/^[a-f0-9]{40}$/D', $baselineRef)) throw new InvalidArgumentException('La referencia debe ser un SHA Git completo.');
}
$baselineCommit = trim(shell_exec('git -C '.escapeshellarg($root).' rev-parse '.escapeshellarg($baselineRef)));
$legacy = shell_exec('git -C '.escapeshellarg($root).' show '.escapeshellarg($baselineRef.':modules/Finance/Helpers/ToPay.php'));
if (!$legacy || !str_contains($legacy, 'SUM(payment)')) throw new RuntimeException('La referencia anterior de cuentas por pagar no está disponible.');
$legacy = str_replace(['<?php','namespace Modules\\Finance\\Helpers;','class ToPay'], ['', 'namespace ExactConsumerBenchmark;', 'class LegacyToPay'], $legacy);
eval($legacy);
$filters = ['period'=>'between_dates','date_start'=>'2026-10-01','date_end'=>'2026-10-31','month_start'=>'2026-10','month_end'=>'2026-10','establishment_id'=>1,'user'=>0];
$samples = 7;
foreach ($argv as $arg) if (str_starts_with($arg, '--samples=')) $samples = (int)substr($arg,10);
if ($samples < 3 || $samples > 30) throw new InvalidArgumentException('Utilice de 3 a 30 muestras.');
$report = ['runtime'=>PHP_VERSION,'database'=>'SQLite temporal en memoria','baseline_git_commit'=>$baselineCommit,'scope'=>'Consumidor backend; referencia anterior reproducida con el código Git indicado y modelos actuales. No mide transporte HTTP, MySQL ni POS.',
    'baseline_source_sha256'=>hash('sha256',$legacy), 'current_source_sha256'=>hash_file('sha256',$root.'/app/Support/Exact/Payables.php'), 'samples'=>$samples,'cases'=>[]];
foreach ([1000,10000] as $count) {
    $db->table('purchases')->delete();
    for ($offset=0; $offset<$count; $offset+=500) {
        $batch = [];
        for ($i=$offset; $i<min($count,$offset+500); $i++) $batch[] = ['id'=>$i+1,'total'=>'500','total_payable'=>'500',
            'calculation_snapshot'=>json_encode(Decimal::snapshot(['total'=>'500','total_payable'=>'500']))];
        $db->table('purchases')->insert($batch);
    }
    $controller = new \Modules\Finance\Http\Controllers\ToPayController();
    $legacyRun = static fn()=>\ExactConsumerBenchmark\LegacyToPay::getToPay($filters);
    $currentRun = static fn()=>$controller->records(new \Illuminate\Http\Request($filters));
    $legacyRun(); $currentRun();
    $measurements = ['legacy'=>[], 'current'=>[]]; $queries = []; $memory = [];
    for ($sample=0; $sample<$samples; $sample++) foreach (['legacy'=>$legacyRun,'current'=>$currentRun] as $name=>$run) {
        $queryCount=0; $db->setEventDispatcher(new \Illuminate\Events\Dispatcher($app));
        $db->listen(static function () use (&$queryCount) { $queryCount++; });
        if (function_exists('memory_reset_peak_usage')) memory_reset_peak_usage();
        $start = hrtime(true); $result = $run();
        // Include JSON serialization; the current endpoint returns a page plus global totals.
        json_encode($result, JSON_THROW_ON_ERROR);
        $measurements[$name][] = (hrtime(true)-$start)/1000000;
        $queries[$name] = $queryCount; $memory[$name] = memory_get_peak_usage(true);
        if ($name === 'current' && ($result['summary']['VES_balance'] !== Decimal::represent(Decimal::of(500)->multipliedBy($count)) || $result['meta']['total'] !== $count)) throw new LogicException('Totales incorrectos durante la medición.');
        unset($result);
    }
    $stats = [];
    foreach ($measurements as $name=>$times) {
        sort($times);
        $stats[$name] = ['p50_ms'=>$times[(int)floor(count($times)*0.5)], 'p95_ms'=>$times[(int)ceil(count($times)*0.95)-1], 'query_count'=>$queries[$name], 'peak_memory_bytes'=>$memory[$name], 'raw_ms'=>$times];
    }
    $allowed = $stats['legacy']['p95_ms'] + max($stats['legacy']['p95_ms']*0.2,100);
    $report['cases'][(string)$count] = $stats + ['allowed_p95_ms'=>$allowed,'passed'=>$stats['current']['p95_ms'] <= $allowed];
    fwrite(STDERR, 'Medidas '.$count.' operaciones.'.PHP_EOL);
}
echo json_encode($report, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
if (array_filter($report['cases'], static fn($case)=>!$case['passed'])) exit(1);
} catch (\Throwable $exception) {
    fwrite(STDERR, get_class($exception).': '.$exception->getMessage().PHP_EOL);
    exit(2);
}
// ######## FIN RENDIMIENTO CALCULOS EXACTOS PRO9 ########
