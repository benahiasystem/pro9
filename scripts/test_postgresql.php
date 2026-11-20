<?php
// ########## INICIO CAMBIO POSTGRESQL 17
/** Host entrypoint: PHP needs neither PDO PostgreSQL nor access to any real .env database. */
declare(strict_types=1);
$root=dirname(__DIR__);chdir($root);
require __DIR__.'/postgresql_source_manifest.php';
require __DIR__.'/postgresql_junit.php';
$source=postgresqlSourceManifest($root);
$arguments=array_slice($argv,1);$suite='all';$phpunit=[];$skipBuild=false;$reuseReference=false;$deferPosPerformance=false;$vesInvoicesOnly=false;
while ($arguments) {
    $argument=array_shift($arguments);
    if ($argument==='--') {$phpunit=$arguments;break;}
    if ($argument==='--without-pos-performance' || $argument==='--without-performance') {$deferPosPerformance=true;continue;}
    if ($argument==='--reuse-reference') {$reuseReference=true;continue;}
    if ($argument==='--ves-invoices-only') {$vesInvoicesOnly=true;$deferPosPerformance=true;continue;}
    if ($argument==='--skip-build') {$skipBuild=true;continue;}
    if (str_starts_with($argument,'--suite=')) {$suite=substr($argument,8);continue;}
    fwrite(STDERR,'Argumento no válido: '.$argument.PHP_EOL);exit(2);
}
if (!in_array($suite,['install','concurrency','http','backup','performance','accounting','all'],true)) exit(2);
if ($deferPosPerformance && (!in_array($suite, ['all','performance'], true) || $phpunit)) {
    fwrite(STDERR, "El aplazamiento POS se admite en la batería completa o de rendimiento.\n");
    exit(2);
}
if ($vesInvoicesOnly && ($suite!=='all' || $phpunit)) {
    fwrite(STDERR, "El alcance de facturas VES se aplica al inventario de la batería completa.\n");
    exit(2);
}
$currencyPolicy=$vesInvoicesOnly
    ? json_decode(file_get_contents($root.'/tests/Infrastructure/postgresql_ves_invoice_scope.json'),true,512,JSON_THROW_ON_ERROR)
    : null;
$runId='sab-pgtest-'.bin2hex(random_bytes(6));
$cache=(getenv('XDG_CACHE_HOME') ?: (getenv('HOME').'/.cache')).'/sab-postgres-tests/'.$runId;
foreach (['data','storage/framework/cache/data','storage/framework/views','storage/framework/sessions','storage/logs','storage/app','bootstrap-cache'] as $directory) if (!mkdir($cache.'/'.$directory,0700,true) && !is_dir($cache.'/'.$directory)) throw new RuntimeException('No se pudo preparar la ejecución temporal.');
$environment=getenv();$environment['SAB_POSTGRES_RUN_ID']=$runId;$environment['SAB_POSTGRES_PASSWORD']=bin2hex(random_bytes(24));
$environment['SAB_POSTGRES_DATA']=$cache.'/data';$environment['SAB_TEST_STORAGE']=$cache.'/storage';$environment['SAB_TEST_BOOTSTRAP_CACHE']=$cache.'/bootstrap-cache';
$environment['SAB_POSTGRES_AV_DEFINITIONS']=$cache.'/scanner-signatures';
if ($vesInvoicesOnly) {
    $environment['SAB_HTTP_ACCEPTANCE_VES_ONLY']='1';
    $environment['SAB_TEST_INVOICES_VES_ONLY']='1';
}
$excludedGroups = ['browser-fixture', 'accounting-attachments'];
$compose=['docker','compose','--env-file','/dev/null','--project-name',$runId,'--file',$root.'/docker-compose.postgresql.test.yml'];
$report=$root.'/informes/evidencias/postgresql_17_implementacion/resultados';if (!is_dir($report)) mkdir($report,0755,true);
file_put_contents($report.'/'.$runId.'-fuentes.json',json_encode($source,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL);
function command(array $command,array $environment,?string $output=null): int {
    $process=proc_open($command,[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$environment);
    if (!is_resource($process)) throw new RuntimeException('No se pudo ejecutar el proceso de pruebas.');
    $target=$output ? fopen($output,'w') : STDOUT;
    stream_set_blocking($pipes[1],false);stream_set_blocking($pipes[2],false);
    while (!feof($pipes[1]) || !feof($pipes[2])) {
        $read=[];foreach ([1,2] as $index) if (!feof($pipes[$index])) $read[]=$pipes[$index];
        if (!$read) break;$write=$except=null;
        if (@stream_select($read,$write,$except,1)===false) {
            $message=error_get_last()['message'] ?? 'No se pudo leer la salida del proceso.';
            if (str_contains($message,'Interrupted system call')) continue;
            throw new RuntimeException($message);
        }
        foreach ($read as $stream) { $bytes=stream_get_contents($stream);if ($bytes!=='') fwrite($stream===$pipes[1] ? $target : STDERR,$bytes); }
    }
    fclose($pipes[1]);fclose($pipes[2]);if ($output) fclose($target);
    return proc_close($process);
}
$cleaned=false;$started=false;$cleanupOk=true;
$cleanup=static function () use (&$cleaned,&$started,&$cleanupOk,$compose,$environment,$cache): bool {
    if ($cleaned) return $cleanupOk;$cleaned=true;
    if ($started && command(array_merge($compose,['down','--volumes','--remove-orphans','--timeout','10']),$environment)!==0) $cleanupOk=false;
    // The database directory is owned by PostgreSQL inside the private test mount.
    if (command(['docker','run','--rm','--entrypoint','sh','-v',$cache.':/sab_test_cleanup','supabase/postgres:17.11.0.019@sha256:eca9f664ced0c453ae499e75bb503283831c94f2eef69fc2e69bf86f70278ddc','-c','find /sab_test_cleanup -mindepth 1 -delete'],$environment)!==0) $cleanupOk=false;
    if (!@rmdir($cache)) $cleanupOk=false;return $cleanupOk;
};
register_shutdown_function($cleanup);
if (function_exists('pcntl_async_signals')) {pcntl_async_signals(true);foreach ([SIGINT,SIGTERM] as $signal) pcntl_signal($signal,static function () use ($cleanup): void {$cleanup();exit(130);});}
$code=1;$checks=[];$failure=null;$referenceUpdate=null;$phases=[];
try {
    if (!$skipBuild && command(array_merge($compose,['build','php']),$environment)!==0) throw new RuntimeException('Falló la construcción PHP.');
    if (!$phpunit && in_array($suite,['all','performance'],true)) {
        $capture = static function (string $reference, array $arguments = []) use ($root,$environment,$report,$runId,&$source): array {
            if (command([PHP_BINARY,'tests/Infrastructure/capture_mysql_reference.php',$root.'/'.$reference,...$arguments],$environment)!==0) {
                throw new RuntimeException('Falló la referencia temporal MySQL para '.$reference.'.');
            }
            $after=postgresqlSourceAfterReference($source,postgresqlSourceManifest($root),$reference);
            $update=['path'=>$reference,'before'=>$source['files'][$reference] ?? null,'after'=>$after['files'][$reference] ?? null];
            $source=$after;
            file_put_contents($report.'/'.$runId.'-fuentes.json',json_encode($source,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL);
            return $update;
        };
        $reference='informes/evidencias/postgresql_17_implementacion/referencia_rendimiento_mysql.json';
        $multiple='informes/evidencias/postgresql_17_implementacion/referencia_rendimiento_multitenant_mysql.json';
        if (!$deferPosPerformance && !$reuseReference) $referenceUpdate=$capture($reference);
        if ((!$deferPosPerformance && !$reuseReference) || !is_file($root.'/'.$multiple)) {
            $referenceUpdate ??= [];
            $referenceUpdate['multitenant']=$capture($multiple,['--http-multitenant']);
        }
    }
    $started=true;
    if (command(array_merge($compose,['up','--detach','--wait','--wait-timeout','180','postgres']),$environment)!==0) throw new RuntimeException('PostgreSQL temporal no llegó a estar disponible.');
    $runtime=array_merge($compose,['run','--rm','--no-deps','php','php','-d','memory_limit=768M']);
    $filters=['install'=>'PostgresImplementationTest|FiscalEmissionSchemaTest::test_fresh|FiscalEmissionSchemaTest::test_central','concurrency'=>'Concurrency|concurrent|parallel','http'=>'ExactHttpAcceptanceTest','backup'=>'PostgresImplementationTest|fixture_snapshot','performance'=>'PostgresPerformanceTest','accounting'=>postgresqlFunctionalFilters()['accounting']];
    $tests=['vendor/bin/phpunit','--exclude-group',implode(',', $excludedGroups),'--printer','Tests\Support\FiscalProgressPrinter'];
    $junitDirectory='/var/www/sab/informes/evidencias/postgresql_17_implementacion/resultados/';
    if (in_array($suite, ['all','performance'], true) && !$phpunit) {
        $discovery=$runId.'-discovery.xml';
        if (command(array_merge($runtime,['vendor/bin/phpunit','--list-tests-xml',$junitDirectory.$discovery]),$environment)!==0) {
            throw new RuntimeException('No se pudo descubrir la suite completa de PHPUnit.');
        }
        // PHPUnit's listing ignores group filters; apply exactly the original exclusions here.
        $expectedAll=postgresqlTestInventory($report.'/'.$discovery,$excludedGroups);
        $functionalExcludedGroups = [...$excludedGroups, 'postgres-performance'];
        $expectedFunctional=postgresqlTestInventory($report.'/'.$discovery,$functionalExcludedGroups);
        // The MySQL reference starts in a fresh process and cluster. Measure PostgreSQL
        // under the same conditions before the functional suite creates and restores tenants.
        $deferred=[];
        if ($deferPosPerformance) {
            $policy=postgresqlDeferPosPerformance($expectedAll,$expectedFunctional);
            $expectedAll=$policy['mandatory'];
            $deferred=array_keys($policy['deferred']);
        }
        $currencyExcluded=[];
        if ($currencyPolicy) {
            $selection=postgresqlVesTestSelection($expectedFunctional,$currencyPolicy['php_excluded']);
            $expectedFunctional=$selection['included'];
            $mixedPayment='Tests\Unit\FiscalEmissionSchemaTest::test_ves_invoice_uses_bcv_rate_for_mixed_cash_and_keeps_fiscal_totals_in_ves';
            if (!isset($expectedFunctional[$mixedPayment])) throw new RuntimeException('La aceptación debe conservar la factura VES con cobro mixto a tasa BCV.');
            $currencyExcluded=$selection['excluded'];
            $expectedAll=array_diff_key($expectedAll,$currencyExcluded);
            $adapted=array_keys($currencyPolicy['php_adapted']);
            if (array_diff($adapted,array_keys($expectedFunctional))) throw new RuntimeException('Falta una prueba adaptada a facturas en VES.');
            $phases['invoice_currency_scope']=['currency'=>'VES','php_excluded'=>array_keys($currencyExcluded),
                'php_adapted'=>$currencyPolicy['php_adapted'],'javascript_excluded'=>$currencyPolicy['javascript_excluded'],
                'javascript_adapted'=>$currencyPolicy['javascript_adapted']];
        }
        $expectedPerformance=array_diff_key($expectedAll, $expectedFunctional);
        $performanceFile=$runId.'-performance.xml';
        $performanceExcludedGroups = $deferPosPerformance ? [...$excludedGroups, 'postgres-performance-pos'] : $excludedGroups;
        $performance=['vendor/bin/phpunit','--exclude-group', implode(',', $performanceExcludedGroups),
            '--printer','Tests\Support\FiscalProgressPrinter','--filter',$filters['performance'],'--log-junit',$junitDirectory.$performanceFile];
        $performanceCode=command(array_merge($runtime,$performance),$environment);
        $phases['performance']=['exit_code'=>$performanceCode,'junit'=>$performanceFile,'executed'=>true,
            'required_cases'=>array_keys($expectedPerformance),'deferred_cases'=>$deferred,
            'reference_mode'=>$deferPosPerformance || $reuseReference ? 'archived' : 'captured'];
        $acceptanceReports=['performance'=>$report.'/'.$performanceFile];
        $functionalCode=0;
        if ($suite==='all') {
            $functionalReports=[];$functionalPhases=[];
            $phases['functional']=['exit_code'=>1,'junit'=>$runId.'-functional.xml','state'=>'incomplete',
                'subphases'=>[],'discovered_tests'=>count($expectedFunctional)];
            foreach (postgresqlFunctionalFilters() as $label=>$filter) {
                $filename=$runId.'-functional-'.$label.'.xml';
                $functional=['vendor/bin/phpunit','--exclude-group',implode(',', $functionalExcludedGroups),'--printer','Tests\Support\FiscalProgressPrinter',
                    '--filter',postgresqlExcludeCases($filter,$currencyExcluded),'--log-junit',$junitDirectory.$filename];
                $phaseCode=command(array_merge($runtime,$functional),$environment);
                $functionalPhases[$label]=['exit_code'=>$phaseCode,'junit'=>$filename];
                $phases['functional']['subphases']=$functionalPhases;
                $functionalReports[$label]=$report.'/'.$filename;
                if ($phaseCode!==0) $functionalCode=1;
            }
            $functionalCounts=postgresqlMergeJunit($functionalReports,$report.'/'.$runId.'-functional.xml',$expectedFunctional);
            $phases['functional']=['exit_code'=>$functionalCode,'junit'=>$runId.'-functional.xml','state'=>'complete','subphases'=>$functionalPhases,
                'discovered_tests'=>count($expectedFunctional),'executed_tests'=>$functionalCounts['tests'],
                'inventory_sha256'=>hash('sha256',json_encode(array_keys($expectedFunctional),JSON_THROW_ON_ERROR))];
            $acceptanceReports['functional']=$report.'/'.$runId.'-functional.xml';
        }
        if ($suite==='performance') $expectedAll=$expectedPerformance;
        $counts=postgresqlMergeJunit($acceptanceReports,$report.'/'.$runId.'.xml',$expectedAll);
        $code=($performanceCode===0 && $functionalCode===0 && $counts['failures']===0 && $counts['errors']===0 && $counts['warnings']===0) ? 0 : 1;
    } else {
        if ($suite!=='all') array_push($tests,'--filter',$filters[$suite]);
        if (!$phpunit) array_push($tests,'--log-junit',$junitDirectory.$runId.'.xml');
        $code=command(array_merge($runtime,$tests,$phpunit),$environment);
    }
    if (!$phpunit) {
        $junit=$report.'/'.$runId.'.xml';
        if (!is_file($junit)) {$code=1;fwrite(STDERR,"Falta el informe de las pruebas obligatorias.\n");}
        else {
            $xml=file_get_contents($junit);
            if (!preg_match('/<testsuite[^>]*tests="([1-9][0-9]*)"/',$xml) || preg_match('/<skipped(?:\s|\/|>)/',$xml)) {$code=1;fwrite(STDERR,"La suite está vacía o contiene pruebas omitidas.\n");}
        }
    }
    if (!$phpunit && $suite==='all') {
        $commands=['sql'=>['php','scripts/audit_postgresql.php'],'sintaxis'=>array_merge($runtime,['scripts/lint_postgresql.php']),
            'javascript'=>array_merge(['node','--test'],glob('tests/js/*.test.cjs')),
            'navegador'=>['python3','scripts/verify_storefront_bundle.py','--output',$report.'/'.$runId.'-navegador.json'],
            'dashboard-navegador'=>['python3','scripts/verify_dashboard_bundle.py','--fixture',$report.'/'.$runId.'-dashboard-fixture.json',
                '--output',$report.'/'.$runId.'-dashboard-navegador.json'],
            'facturacion-navegador'=>['python3','scripts/verify_invoice_decimal_bundle.py','--output',$report.'/'.$runId.'-facturacion-navegador.json'],
            'exactitud-facturacion-php'=>['php','scripts/audit_exact_calculations.php','--check','--scope=billing'],
            'exactitud-facturacion-javascript'=>['node','scripts/audit_exact_calculations.cjs','--check','--scope=billing'],
            'diff'=>['git','diff','--check'],'diff-index'=>['git','diff','--cached','--check']];
        if ($currencyPolicy) {
            $commands['facturacion-navegador'][]='--ves-invoices-only';
            $javascriptFiles=glob('tests/js/*.test.cjs');
            $split=$currencyPolicy['javascript_excluded'];
            if (array_diff(array_keys($split),$javascriptFiles)) throw new RuntimeException('La selección JavaScript contiene archivos sin pruebas.');
            $commands['javascript']=array_merge(['node','--test'],array_values(array_diff($javascriptFiles,array_keys($split))));
            foreach ($split as $file=>$titles) {
                $pattern='^(?:'.implode('|',array_map(static fn (string $title): string => preg_quote($title,'/'),$titles)).')$';
                $commands['javascript-'.basename($file,'.test.cjs')]=['node','--test','--test-skip-pattern='.$pattern,$file];
            }
        }
        foreach ($commands as $label=>$check) {
            $output=$report.'/'.$runId.'-'.$label.'.log';
            $checks[$label]=['exit_code'=>command($check,$environment,$output),'log'=>basename($output)];
            if ($currencyPolicy && ($label==='javascript' || str_starts_with($label,'javascript-'))) {
                $tap=file_get_contents($output);
                if (!preg_match('/^# pass [1-9][0-9]*$/m',$tap)) $checks[$label]['exit_code']=1;
                if ($label!=='javascript') {
                    foreach ($split[$check[array_key_last($check)]] as $title) {
                        if (preg_match('/^(?:not )?ok [0-9]+ - '.preg_quote($title,'/').'$/m',$tap)) {
                            $checks[$label]['exit_code']=1;
                        }
                    }
                }
            }
            if ($checks[$label]['exit_code']!==0) $code=1;
            echo $label.': '.($checks[$label]['exit_code']===0 ? 'OK' : 'ERROR').PHP_EOL;
        }
    }
    // Only diagnostics are persisted; database files and credentials stay in the private cache.
} catch (Throwable $error) {$failure=$error->getMessage();fwrite(STDERR,$failure.PHP_EOL);$code=1;}
finally {
    if (!$cleanup()) $code=1;
    try {$unchanged=hash_equals($source['sha256'],postgresqlSourceManifest($root)['sha256']);}
    catch (Throwable $error) {$unchanged=false;$failure=$error->getMessage();}
    if (!$unchanged) {$code=1;fwrite(STDERR,"Cambió el código durante las pruebas; es necesario repetirlas.\n");}
    file_put_contents($report.'/'.$runId.'.json',json_encode(['run_id'=>$runId,'suite'=>$suite,'server_version'=>'17.11','platform'=>'linux/amd64',
        'exit_code'=>$code,'databases'=>'temporary only','source_sha256'=>$source['sha256'],'source_unchanged'=>$unchanged,
        'private_scanner'=>false,'excluded_groups'=>$excludedGroups,
        'cleanup_ok'=>$cleanupOk,'phases'=>$phases,'checks'=>$checks,'reference_update'=>$referenceUpdate,'error'=>$failure,'finished_at'=>date(DATE_ATOM)],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL);
}
exit($code);
// ######### FIN CAMBIO POSTGRESQL 17
