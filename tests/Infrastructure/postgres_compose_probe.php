<?php
// ########## INICIO CAMBIO POSTGRESQL 17
/** Exercise the deployable Compose and its real init script, never an existing project. */
declare(strict_types=1);
$root=dirname(__DIR__,2);
require_once $root.'/scripts/postgresql_source_manifest.php';
$recovery=$argv===[$argv[0],'--recovery'];
if (!$recovery && count($argv)!==1) throw new InvalidArgumentException('Use --recovery para ensayar una restauración integral.');
$name='pro9-infra-'.bin2hex(random_bytes(6));
$directory=(getenv('HOME') ?: sys_get_temp_dir()).'/.cache/pro9-postgres-tests/'.$name;
$image=$name.'-php:17.11';
$source=postgresqlSourceManifest($root);
function prepareInfraProject(string $root,string $name,string $directory,string $image,string $database): array {
    foreach (['data','storage/framework/views','storage/framework/cache/data','storage/framework/sessions','storage/logs','storage/app','bootstrap-cache','bcv','php-build/docker','transfer'] as $path) mkdir($directory.'/'.$path,0700,true);
    file_put_contents($directory.'/bcv/.env',"API_TOKEN=fictional-test-only\n");
    $buildInputs=[];
    foreach (['docker/php.Dockerfile','docker/php-entrypoint.sh'] as $file) {
        if (!copy($root.'/'.$file,$directory.'/php-build/'.$file)) throw new RuntimeException('No se pudo preparar el contexto mínimo PHP.');
        if (!chmod($directory.'/php-build/'.$file,fileperms($root.'/'.$file) & 0777)) throw new RuntimeException('No se pudieron conservar los permisos del contexto PHP.');
        $buildInputs[$file]=hash_file('sha256',$directory.'/php-build/'.$file);
    }
    $environment=getenv();foreach (['SUPABASE_POSTGRES_PASSWORD','DB_PASSWORD','DB_MIGRATOR_PASSWORD','DB_PROVISIONER_PASSWORD','CENTRIFUGO_API_KEY','CENTRIFUGO_TOKEN_HMAC_SECRET_KEY'] as $variable) $environment[$variable]=bin2hex(random_bytes(24));
    $environment=array_merge($environment,['DB_DATABASE'=>$database,'DB_USERNAME'=>'pro9_infra_runtime','DB_MIGRATOR_USERNAME'=>'pro9_infra_owner','DB_PROVISIONER_USERNAME'=>'pro9_infra_provisioner']);
    $environment['PRO9_INFRA_STDERR']=$directory.'/stderr.log';
    $environment['PRO9_INFRA_PHP_IMAGE']=$image;
    $environment['API_BCV_PATH']=$directory.'/bcv';$environment['PRO9_APP_ENV_FILE']=$directory.'/app.env';
    $env=['APP_ENV'=>'testing','APP_URL'=>'http://central.recovery.example.test','APP_URL_BASE'=>'recovery.example.test','PREFIX_URL'=>'central','PREFIX_DATABASE'=>'pro9_recovery','PRO9_RECOVERY_PROJECT'=>$name,'APP_KEY'=>'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=','TENANCY_KEY'=>'infrastructure-test-only','DB_CONNECTION'=>'system','DB_HOST'=>'postgres','DB_PORT'=>'5432','DB_PROVISIONER_DATABASE'=>'postgres','CACHE_DRIVER'=>'array','MAIL_MAILER'=>'array','SESSION_DRIVER'=>'array','QUEUE_CONNECTION'=>'sync'];
    foreach (['DB_DATABASE','DB_USERNAME','DB_PASSWORD','DB_MIGRATOR_USERNAME','DB_MIGRATOR_PASSWORD','DB_PROVISIONER_USERNAME','DB_PROVISIONER_PASSWORD','CENTRIFUGO_API_KEY','CENTRIFUGO_TOKEN_HMAC_SECRET_KEY'] as $variable) $env[$variable]=$environment[$variable];
    file_put_contents($directory.'/app.env',implode("\n",array_map(static fn($key,$value)=>$key.'='.$value,array_keys($env),$env))."\n");chmod($directory.'/app.env',0644); // Fictional credentials; the parent directory stays private. FPM must read this bind-mounted file.
    file_put_contents($directory.'/override.json',json_encode(['services'=>[
        'postgres'=>['command'=>['postgres','-D','/etc/postgresql','-c','pro9.test_run='.$name],'mem_limit'=>'768m','cpus'=>2,'volumes'=>[$directory.'/data:/var/lib/postgresql/data']],
        'php'=>['image'=>$environment['PRO9_INFRA_PHP_IMAGE'],'build'=>['context'=>$directory.'/php-build','dockerfile'=>'docker/php.Dockerfile'],'mem_limit'=>'1536m','cpus'=>2,'volumes'=>[
            $root.':/var/www/pro9:ro',$directory.'/storage:/var/www/pro9/storage',
            $directory.'/bootstrap-cache:/var/www/pro9/bootstrap/cache',$directory.'/app.env:/var/www/pro9/.env:ro',
            $directory.'/transfer:/pro9-transfer:ro']],
        'nginx'=>['mem_limit'=>'128m','cpus'=>1],'redis'=>['mem_limit'=>'128m','cpus'=>1]],
        'networks'=>['default'=>['internal'=>true],'bcv'=>['internal'=>true]]],JSON_UNESCAPED_SLASHES));
    $compose=['docker','compose','-p',$name,'--env-file',$directory.'/app.env','-f',$root.'/docker-compose.yml','-f',$directory.'/override.json'];
    return [$compose,$environment,$buildInputs];
}
[$compose,$environment,$buildInputs]=prepareInfraProject($root,$name,$directory,$image,'pro9_infra_central');
function runInfra(array $command,array $environment): string {
    $p=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['file',$environment['PRO9_INFRA_STDERR'],'a']],$pipes,null,$environment);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);fclose($pipes[1]);$code=proc_close($p);
    if ($code!==0) throw new RuntimeException('Falló la verificación de infraestructura (salida '.$code.'): '.implode(' ',$command).' '.substr($out,0,1200).'. Consulte el diagnóstico privado del sondeo.');return trim($out);
}
function recoverInfra(array $source,array $sourceEnv,array $destination,array $destinationEnv,string $directory,string $sourceName,string $destinationName): array {
    $cli=static fn($project,$env,$args)=>runInfra(array_merge($project,['run','--rm','--no-deps','php','php'],$args),$env);
    $fixture='tests/Infrastructure/postgres_recovery_fixture.php';
    $probe=static fn($project,$env,$mode)=>json_decode($cli($project,$env,[$fixture,$mode]),true,512,JSON_THROW_ON_ERROR);
    $cluster=static fn($project,$env)=>runInfra(array_merge($project,['exec','-T','postgres','psql','--no-psqlrc','-U','supabase_admin','-d','postgres','-Atc',
        "SELECT system_identifier::text || ':' || current_setting('pro9.test_run') FROM pg_control_system()"]),$env);
    $origination=$cluster($source,$sourceEnv);
    $recovery=$cluster($destination,$destinationEnv);
    if (strtok($origination,':')===strtok($recovery,':') || !str_ends_with($origination,':'.$sourceName)
        || !str_ends_with($recovery,':'.$destinationName)) throw new RuntimeException('Los clústeres temporales deben ser independientes.');
    $cli($source,$sourceEnv,['artisan','pro9:install-postgres']);
    $cli($source,$sourceEnv,[$fixture,'seed']);
    $cli($source,$sourceEnv,['artisan','down']);
    $before=$probe($source,$sourceEnv,'tenants');
    $package=$cli($source,$sourceEnv,['artisan','bk:bd','todos','--quiesced']);
    if (!preg_match('~^/var/www/pro9/storage/app/backups/zip/postgresql-[0-9-]+-[a-f0-9]+\.zip$~D',$package)) throw new RuntimeException('El comando no devolvió su respaldo integral.');
    runInfra(array_merge($source,['run','--rm','--no-deps','-v',$directory.'/transfer:/pro9-export','php','sh','-c',
        'cp -- "$1" /pro9-export/integral.zip && chmod 0644 /pro9-export/integral.zip','sh',$package]),$sourceEnv);
    $package='/pro9-transfer/integral.zip';
    $cli($destination,$destinationEnv,['artisan','down']);
    $cli($destination,$destinationEnv,['artisan','pro9:restore-central',$package,'--source=pro9_infra_central']);
    $central=$probe($destination,$destinationEnv,'central');
    if ($central!==array_intersect_key($before,$central)) throw new RuntimeException('Los datos centrales no coinciden con el corte.');
    foreach (array_keys($before['tenants']) as $tenant) {
        $restored=json_decode($cli($destination,$destinationEnv,['artisan','pro9:restore-tenant',$tenant,$package,'--source='.$tenant,'--activate']),true,512,JSON_THROW_ON_ERROR);
        if ($restored['database']!==$tenant || !$restored['activated'] || $restored['previous_database']!==null) throw new RuntimeException('No se activó la empresa en la instalación vacía.');
    }
    $after=$probe($destination,$destinationEnv,'tenants');
    if ($after['ledger_count']!==$before['ledger_count']+4) throw new RuntimeException('Faltan las operaciones de recuperación de los tenants.');
    unset($before['ledger'],$before['ledger_count'],$after['ledger'],$after['ledger_count']);
    if ($after!==$before) throw new RuntimeException('Difieren las tablas, esquemas o archivos de la recuperación integral.');
    $cli($destination,$destinationEnv,['artisan','up']);
    runInfra(array_merge($destination,['up','-d','--no-deps','--wait','--wait-timeout','90','php','redis','centrifugo']),$destinationEnv);
    runInfra(array_merge($destination,['run','-d','--rm','--no-deps','--name',$destinationName.'-http','nginx']),$destinationEnv);
    $http=$probe($destination,$destinationEnv,'http');
    return ['source_cluster'=>$origination,'destination_cluster'=>$recovery,'archive_sha256'=>hash_file('sha256',$directory.'/transfer/integral.zip'),
        'central_tables'=>count($central['central_data'])+1,'central_schema_sha256'=>$central['central_schema'],
        'tenant_tables'=>array_map(static fn($tenant)=>count($tenant['data']),$after['tenants']),
        'tenant_schema_sha256'=>array_column($after['tenants'],'schema'),'attachments_verified'=>count($after['tenants']),
        'shared_upload_sha256'=>$after['shared_file_sha256'],'owners_permissions_sequences'=>true,'http'=>$http];
}
$destinationName=$name.'-destination';$destinationDirectory=$directory.'/destination';$destination=null;$destinationEnv=null;
$started=false;$httpStarted=false;$imageBuilt=false;$cleanupOk=false;$code=1;
$report=['run_id'=>$name,'temporary_only'=>true];
try {
    runInfra(array_merge($compose,['config','--quiet']),$environment);$started=true;
    runInfra(array_merge($compose,['build','php']),$environment);$imageBuilt=true;
    runInfra(array_merge($compose,['up','-d','--no-deps','--wait','--wait-timeout','150','postgres']),$environment);
    $sql=static fn($query)=>runInfra(array_merge($compose,['exec','-T','postgres','psql','--no-psqlrc','-U','supabase_admin','-d','pro9_infra_central','-Atc',$query]),$environment);
    $version=$sql('SELECT version()');if (!str_contains($version,'PostgreSQL 17.11 on x86_64') || !str_contains($version,'15.2.0')) throw new RuntimeException('La versión o arquitectura no coincide.');
    $roles=$sql("SELECT rolname||':'||rolsuper||':'||rolcreatedb||':'||rolcreaterole FROM pg_roles WHERE rolname LIKE 'pro9_infra_%' ORDER BY rolname");
    if (!str_contains($roles,'pro9_infra_runtime:false:false:false') || !str_contains($roles,'pro9_infra_provisioner:false:true:true')) throw new RuntimeException('Privilegios incorrectos.');
    if ($recovery) {
        [$destination,$destinationEnv]=prepareInfraProject($root,$destinationName,$destinationDirectory,$image,'pro9_infra_destination');
        runInfra(array_merge($destination,['config','--quiet']),$destinationEnv);
        runInfra(array_merge($destination,['up','-d','--no-deps','--wait','--wait-timeout','150','postgres']),$destinationEnv);
        $report+=['version'=>$version,'recovery'=>recoverInfra($compose,$environment,$destination,$destinationEnv,$destinationDirectory,$name,$destinationName)];
    } else {
    $sql('CREATE TABLE persistence_probe(id integer PRIMARY KEY); INSERT INTO persistence_probe VALUES (17)');
    runInfra(array_merge($compose,['up','-d','--no-deps','--force-recreate','--wait','--wait-timeout','90','postgres']),$environment);
    if ($sql('SELECT id FROM persistence_probe')!=='17') throw new RuntimeException('No persistieron los datos.');
    $sql('DROP TABLE persistence_probe');
    runInfra(array_merge($compose,['run','--rm','--no-deps','php','php','artisan','pro9:install-postgres']),$environment);
    runInfra(array_merge($compose,['run','--rm','--no-deps','php','php','artisan','pro9:install-postgres','--resume']),$environment);
    if ($sql('SELECT count(*) FROM users')!=='1' || $sql('SELECT count(*) FROM plans')!=='2') throw new RuntimeException('Semillas incompletas o duplicadas.');
    runInfra(array_merge($compose,['up','-d','--no-deps','--wait','--wait-timeout','90','php','redis','centrifugo']),$environment);
    runInfra(array_merge($compose,['run','-d','--rm','--no-deps','--name',$name.'-http','nginx']),$environment);$httpStarted=true;
    $status=runInfra(array_merge($compose,['exec','-T','php','php','-r',
        '$url="http://'.$name.'-http/login";$context=stream_context_create(["http"=>["header"=>"Host: central.recovery.example.test\\r\\nAccept: text/html\\r\\n","ignore_errors"=>true,"timeout"=>30]]);$body=file_get_contents($url,false,$context);echo ($http_response_header[0] ?? "missing");if ($body===false || !str_contains($http_response_header[0] ?? ""," 200 ")) exit(1);']),$environment);
    if (runInfra(array_merge($compose,['exec','-T','redis','redis-cli','ping']),$environment)!=='PONG') throw new RuntimeException('Redis temporal no respondió.');
    $report=['run_id'=>$name,'version'=>$version,'roles'=>$roles,'persistence_after_recreate'=>true,'installation_and_resume'=>true,'central_migrations'=>(int)$sql('SELECT count(*) FROM migrations'),
        'nginx_fpm_http'=>$status,'redis'=>'PONG','centrifugo_health'=>'healthy','temporary_only'=>true];
    }
    $code=0;
} catch (Throwable $error) {
    $report['error']=$error->getMessage();fwrite(STDERR,$error->getMessage()."\n");
    $log=is_file($environment['PRO9_INFRA_STDERR']) ? file_get_contents($environment['PRO9_INFRA_STDERR']) : '';
    if ($destinationEnv!==null && is_file($destinationEnv['PRO9_INFRA_STDERR'])) $log.="\n".file_get_contents($destinationEnv['PRO9_INFRA_STDERR']);
    if ($started) {
        try {
            $log.="\n".runInfra(array_merge($compose,['logs','--no-color','postgres','php']),$environment);
            if ($destination!==null) $log.="\n".runInfra(array_merge($destination,['logs','--no-color','postgres','php']),$destinationEnv);
            if ($httpStarted) $log.="\n".runInfra(array_merge($compose,['exec','-T','php','php','-r','foreach (glob("storage/logs/*.log") as $file) echo file_get_contents($file);']),$environment);
        } catch (Throwable $diagnostic) {$log.="\nNo se pudo recopilar el diagnóstico de contenedores temporales.\n";}
    }
    $diagnostics=dirname($directory).'/diagnostics';if (!is_dir($diagnostics)) mkdir($diagnostics,0700,true);
    $private=$diagnostics.'/'.$name.'-private.log';file_put_contents($private,$log);chmod($private,0600);
    $report['private_diagnostic']=$private;fwrite(STDERR,'Diagnóstico privado: '.$private."\n");
}
finally {
    $cleanupOk=true;
    $stops=[];
    if ($httpStarted) $stops[]=['docker','rm','-f',$name.'-http'];
    if ($started) $stops[]=array_merge($compose,['down','-v','--remove-orphans','--timeout','10']);
    if ($destination!==null) {
        try {runInfra(array_merge($destination,['down','-v','--remove-orphans','--timeout','10']),$destinationEnv);}
        catch (Throwable $error) {$cleanupOk=false;fwrite(STDERR,'No se completó la parada del destino temporal: '.$error->getMessage()."\n");}
    }
    foreach ($stops as $stop) {
        try {runInfra($stop,$environment);}
        catch (Throwable $error) {$cleanupOk=false;fwrite(STDERR,'No se completó la parada temporal: '.$error->getMessage()."\n");}
    }
    // Never remove database files if a private service could still be running.
    if ($cleanupOk) {
        try {
            if ($imageBuilt) runInfra(['docker','image','rm',$environment['PRO9_INFRA_PHP_IMAGE']],$environment);
            runInfra(['docker','run','--rm','--entrypoint','sh','-v',$directory.':/pro9_test_cleanup',
                'supabase/postgres:17.11.0.019@sha256:eca9f664ced0c453ae499e75bb503283831c94f2eef69fc2e69bf86f70278ddc',
                '-c','find /pro9_test_cleanup -mindepth 1 -delete'],$environment);
            if (!rmdir($directory)) throw new RuntimeException('El directorio temporal no quedó vacío.');
        } catch (Throwable $error) {$cleanupOk=false;fwrite(STDERR,'No se completó la limpieza: '.$error->getMessage()."\n");}
    }
    if (!$cleanupOk) $code=1;
}
$report['source_sha256']=$source['sha256'];
$report['source_unchanged']=$source['sha256']===postgresqlSourceManifest($root)['sha256'];
if (!$report['source_unchanged']) $code=1;
$report['build_inputs_sha256']=$buildInputs;
$report['cleanup_ok']=$cleanupOk;$report['exit_code']=$code;$report['finished_at']=date(DATE_ATOM);
$output=$root.'/informes/evidencias/postgresql_17_implementacion/resultados';if (!is_dir($output)) mkdir($output,0755,true);
file_put_contents($output.'/'.$name.'.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n");
echo json_encode($report,JSON_THROW_ON_ERROR)."\n";
exit($code);
// ######### FIN CAMBIO POSTGRESQL 17
