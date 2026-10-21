<?php

// ######## INICIO CALCULOS EXACTOS PRO9 ########
/** Own a disposable MySQL server; never change the application's MySQL settings/data. */
require dirname(__DIR__).'/vendor/autoload.php';
$root=dirname(__DIR__);
$environment=is_file($root.'/.env') ? \Dotenv\Dotenv::parse(file_get_contents($root.'/.env')) : [];
$username=$environment['DB_USERNAME'] ?? 'root';
$password=$environment['DB_PASSWORD'] ?? '';
$name='pro9-exact-test-'.bin2hex(random_bytes(6));
$run=static function (array $command, ?array $env=null): array {
    $process=proc_open($command,[1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$env);
    if (!is_resource($process)) throw new RuntimeException('No se pudo iniciar el proceso de pruebas.');
    $output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);return [proc_close($process),$output,$error];
};
$cleanup=static function () use ($name,$run): void { $run(['docker','rm','-f',$name]); };
$env=getenv();
if ($password === '') $env['MYSQL_ALLOW_EMPTY_PASSWORD']='yes';
else $env['MYSQL_ROOT_PASSWORD']=$password;
if ($username !== 'root') { $env['MYSQL_USER']=$username;$env['MYSQL_PASSWORD']=$password;$env['MYSQL_DATABASE']='pro9_test_control'; }
$command=['docker','run','--pull=never','--detach','--name',$name,'--publish','127.0.0.1::3306',
    '--tmpfs','/var/lib/mysql:rw,nosuid,size=1024m','--memory','1536m','--env','MYSQL_ROOT_HOST=%'];
foreach (['MYSQL_ROOT_PASSWORD','MYSQL_ALLOW_EMPTY_PASSWORD','MYSQL_USER','MYSQL_PASSWORD','MYSQL_DATABASE'] as $key) {
    if (isset($env[$key])) { $command[]='--env';$command[]=$key; }
}
array_push($command,'mysql:5.7.33','--innodb-flush-log-at-trx-commit=2','--sync-binlog=0','--skip-log-bin');
$started=false;
try {
    [$status,$output,$error]=$run($command,$env);
    if ($status !== 0) throw new RuntimeException('No se pudo crear MySQL temporal: '.$error);
    $started=true;
    if (function_exists('pcntl_async_signals')) {
        pcntl_async_signals(true);
        pcntl_signal(SIGTERM,static function () use ($cleanup) { $cleanup();exit(143); });
        pcntl_signal(SIGINT,static function () use ($cleanup) { $cleanup();exit(130); });
    }
    [$status,$port]=$run(['docker','port',$name,'3306/tcp']);
    if ($status !== 0 || !preg_match('/127\.0\.0\.1:(\d+)/', $port,$match)) throw new RuntimeException('Falta puerto local de MySQL temporal.');
    $port=$match[1];$ready=false;
    // Root probes readiness; each fixture keeps using its existing configured username.
    for ($attempt=0;$attempt<90;$attempt++) {
        try { $probe=new PDO('mysql:host=127.0.0.1;port='.$port.';dbname=information_schema','root',$password);$ready=true;break; }
        catch (PDOException $error) { usleep(500000); }
    }
    if (!$ready) throw new RuntimeException('MySQL temporal no llegó a estar disponible.');
    if ($username !== 'root') {
        $probe->exec('GRANT ALL PRIVILEGES ON *.* TO '.$probe->quote($username)."@'%' WITH GRANT OPTION");
    }
    unset($probe);
    $testEnv=getenv();$testEnv['DB_HOST']='127.0.0.1';$testEnv['DB_PORT']=$port;$testEnv['PRO9_FISCAL_MYSQL_TESTS']='1';
    fwrite(STDERR,'MySQL 5.7 temporal aislado; sin cambios en la instancia de la aplicación.'.PHP_EOL);
    $process=proc_open(array_merge([PHP_BINARY,$root.'/vendor/bin/phpunit'],array_slice($argv,1)),[0=>STDIN,1=>STDOUT,2=>STDERR],$pipes,$root,$testEnv);
    if (!is_resource($process)) throw new RuntimeException('No se pudo iniciar PHPUnit.');
    $exit=proc_close($process);
} catch (Throwable $error) { fwrite(STDERR,$error->getMessage().PHP_EOL);$exit=2; }
finally { if ($started) $cleanup(); }
exit($exit);
// ######## FIN CALCULOS EXACTOS PRO9 ########
