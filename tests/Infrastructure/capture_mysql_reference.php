<?php
// ########## INICIO CAMBIO POSTGRESQL 17
/** Frozen, disposable MySQL reference for comparison only; never loaded by the application. */
declare(strict_types=1);
$root=dirname(__DIR__,2);$destination=$argv[1] ?? '';
if (!$destination) throw new InvalidArgumentException('Indique el JSON de evidencia de destino.');
$flags = array_slice($argv, 2);
if ($flags !== [] && $flags !== ['--http-multitenant']) {
    throw new InvalidArgumentException('Indique una modalidad de referencia reconocida.');
}
$multipleTenants = $flags === ['--http-multitenant'];
$benchmark = $multipleTenants ? 'benchmark_postgresql_tenants.php' : 'benchmark_postgresql_http.php';
$name='sab-reference-'.bin2hex(random_bytes(6));$directory=(getenv('HOME') ?: sys_get_temp_dir()).'/.cache/sab-postgres-tests/'.$name;
foreach (['source','data'] as $part) mkdir($directory.'/'.$part,0700,true);
$environment=getenv();$baseline='861228462e208d2060c9148b87fd7d7d3f354da5';
function referenceCommand(array $command,array $environment,?string $cwd=null,?string $output=null): void {
    $descriptors=[0=>STDIN,1=>$output ? ['file',$output,'w'] : STDOUT,2=>STDERR];$p=proc_open($command,$descriptors,$pipes,$cwd,$environment);
    if (!is_resource($p) || proc_close($p)!==0) throw new RuntimeException('Falló la referencia temporal: '.$command[0]);
}
$network=false;$server=false;$code=1;
try {
    referenceCommand(['git','archive','--output='.$directory.'/source.tar',$baseline],$environment,$root);
    referenceCommand(['tar','-xf',$directory.'/source.tar','-C',$directory.'/source'],$environment);
    referenceCommand(['cp','-a','--reflink=auto',$root.'/vendor',$directory.'/source/vendor'],$environment);
    file_put_contents($directory.'/source/.env',"APP_ENV=testing\nAPP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=\nTENANCY_KEY=temporary-reference-only\nDB_USERNAME=root\nDB_PASSWORD=\n");chmod($directory.'/source/.env',0600);
    copy($root.'/tests/Support/benchmark_postgresql_http.php',$directory.'/source/tests/Support/benchmark_postgresql_http.php');
    if ($multipleTenants) {
        foreach ([$benchmark,'BenchmarkTenantCohort.php'] as $file) {
            copy($root.'/tests/Support/'.$file, $directory.'/source/tests/Support/'.$file);
        }
    }
    $router=str_replace("['127.0.0.1', 'postgres']","['mysql']",file_get_contents($root.'/tests/Support/exact_http_router.php'));
    file_put_contents($directory.'/source/tests/Support/exact_http_router.php',$router);
    referenceCommand(['docker','build','-t','sab-mysql-reference-php:temporary','-f',$root.'/tests/Infrastructure/php-mysql-reference.Dockerfile',$root],$environment);
    referenceCommand(['docker','network','create','--internal','--label','sab.postgresql.temporary=true',$name],$environment);$network=true;
    referenceCommand(['docker','run','-d','--name',$name,'--network',$name,'--network-alias','mysql','--label','sab.postgresql.temporary=true','--memory=768m','--cpus=2','-e','MYSQL_ALLOW_EMPTY_PASSWORD=yes','-v',$directory.'/data:/var/lib/mysql','mysql:5.7.33@sha256:e42a18d0bd0aa746a734a49cbbcc079ccdf6681c474a238d38e79dc0884e0ecc'],$environment);$server=true;
    $ready=false;
    for ($i=0;$i<120;$i++) {
        $p=proc_open(['docker','exec',$name,'mysqladmin','--protocol=TCP','--host=127.0.0.1','--connect-timeout=2','ping','--silent'],[0=>['pipe','r'],1=>['file','/dev/null','w'],2=>['file','/dev/null','w']],$pipes);fclose($pipes[0]);if (proc_close($p)===0) {$ready=true;break;}usleep(1000000);
    }
    if (!$ready) throw new RuntimeException('MySQL de referencia no llegó a estar disponible.');
    $args=['docker','run','--rm','--label','sab.postgresql.temporary=true','--network',$name,'--memory=1536m','--cpus=2','-e','APP_ENV=testing','-e','DB_HOST=mysql','-e','DB_PORT=3306','-e','SAB_POSTGRES_TESTS=1','-e','SAB_FISCAL_MYSQL_TESTS=1','-e','SAB_FISCAL_SNAPSHOT=0','-v',$directory.'/source:/var/www/sab','-w','/var/www/sab','sab-mysql-reference-php:temporary','php','-d','memory_limit=768M','-d','display_errors=0','tests/Support/'.$benchmark];
    referenceCommand($args,$environment,null,$directory.'/reference.json');
    $result=json_decode(file_get_contents($directory.'/reference.json'),true,512,JSON_THROW_ON_ERROR);
    $reports = $multipleTenants ? $result : [$result];
    if (count($reports) !== ($multipleTenants ? 2 : 1)) throw new RuntimeException('Referencia incompleta.');
    foreach ($reports as $report) {
        if (($report['engine'] ?? null) !== 'mysql' || array_keys($report['cases'] ?? []) !== [1000, 10000]) {
            throw new RuntimeException('Referencia incompleta.');
        }
    }
    if ($multipleTenants) $result = ['php_version' => $reports['a']['php_version'], 'tenants' => $reports];
    $result+=['baseline_revision'=>$baseline,'temporary_only'=>true,'cpu_limit'=>2,'server_memory_bytes'=>805306368,'php_memory_bytes'=>1610612736,'captured_at'=>date(DATE_ATOM)];
    if (!is_dir(dirname($destination))) mkdir(dirname($destination),0755,true);
    file_put_contents($destination,json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n");$code=0;
} catch (Throwable $error) {fwrite(STDERR,$error->getMessage()."\n");}
finally {
    $cleanup=[];
    if ($server) $cleanup[]=['docker','rm','-f',$name];
    if ($network) $cleanup[]=['docker','network','rm',$name];
    $cleanup[]=['docker','run','--rm','--entrypoint','sh','-v',$directory.':/sab_test_cleanup','supabase/postgres:17.11.0.019@sha256:eca9f664ced0c453ae499e75bb503283831c94f2eef69fc2e69bf86f70278ddc','-c','find /sab_test_cleanup -mindepth 1 -delete'];
    foreach ($cleanup as $command) {
        try {referenceCommand($command,$environment);}
        catch (Throwable $error) {fwrite(STDERR,'No se completó la limpieza de referencia: '.$error->getMessage()."\n");$code=1;}
    }
    if (!@rmdir($directory)) {$code=1;fwrite(STDERR,"No se pudo eliminar el directorio de referencia.\n");}
}
exit($code);
// ######### FIN CAMBIO POSTGRESQL 17
