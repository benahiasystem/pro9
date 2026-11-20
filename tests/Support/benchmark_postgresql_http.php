<?php
// ########## INICIO CAMBIO POSTGRESQL 17
/** Run against the temporary fixture only; 30 loopback HTTP samples per workload. */
require dirname(__DIR__,2).'/vendor/autoload.php';
if (getenv('PRO9_POSTGRES_TESTS')!=='1') throw new RuntimeException('Requiere el ejecutor temporal.');
$workloads = ['http_documents', 'pos_catalog', 'sales_report'];
$requested = array_slice($argv, 1) ?: $workloads;
if (array_diff($requested, $workloads) || count($requested) !== count(array_unique($requested))) {
    throw new InvalidArgumentException('Indique cargas de rendimiento válidas y sin duplicados.');
}
$cohort = getenv('PRO9_HTTP_BENCHMARK_COHORT') ?: null;
$member = getenv('PRO9_HTTP_BENCHMARK_MEMBER') ?: null;
if (($cohort === null) !== ($member === null)
    || ($cohort !== null && (
        !in_array($member, ['a', 'b'], true)
        || !preg_match('/^pro9_http_cohort_[a-f0-9]{12}$/D', basename($cohort))
        || realpath($cohort) !== realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR.basename($cohort)
        || !is_dir($cohort)
    ))) {
    throw new InvalidArgumentException('Cohorte de rendimiento temporal inválida.');
}
$hostname = 'exact-http'.($member === null ? '' : '-'.$member).'.example.test';
$central = $cohort === null ? null : json_decode(file_get_contents($cohort.'/central.json'), true, 512, JSON_THROW_ON_ERROR);
if ($central !== null && function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGTERM, static function (): void {throw new RuntimeException('Medición de cohorte cancelada.');});
}
$companyMarker = 'Empresa temporal '.$member;
$documentMarker = 'Cliente de medición '.$member;

$barrier = static function (int $documents, string $workload, int $sample) use ($cohort, $member): void {
    if ($cohort === null) return;

    $phase = $cohort.DIRECTORY_SEPARATOR.$documents.'-'.$workload.'-'.$sample;
    $peer = $member === 'a' ? 'b' : 'a';
    if (!touch($phase.'-'.$member)) throw new RuntimeException('No se pudo sincronizar la carga HTTP.');
    $deadline = hrtime(true) + 90_000_000_000;
    while (hrtime(true) < $deadline) {
        clearstatcache(true, $phase.'-'.$peer);
        if (is_file($phase.'-'.$peer)) return;
        usleep(10000);
    }
    throw new RuntimeException('El otro tenant no alcanzó la fase '.$documents.' '.$workload.' '.$sample.'.');
};

        $fixture=new class extends \Tests\Unit\FiscalEmissionSchemaTest {
            public bool $ready=false;
            public function start(): array { parent::setUp();$this->ready=true;return (new \ReflectionMethod(parent::class,'bootHkaFixture'))->invoke($this); }
            public function stop(): void { parent::tearDown(); }
        };
        $server=null;$referenceServer=null;$referenceAddress=null;$raceServer=null;$temp=null;
        try {
            [$db,$app,$input]=$fixture->start();
            if ($central === null) {
                $schema=$db->getSchemaBuilder();
                $schema->create('websites',function ($t) { $t->bigIncrements('id');$t->string('uuid');$t->timestamps();$t->softDeletes(); });
                $schema->create('hostnames',function ($t) { $t->bigIncrements('id');$t->string('fqdn');$t->string('redirect_to')->nullable();$t->boolean('force_https')->default(false);$t->timestamp('under_maintenance_since')->nullable();$t->unsignedBigInteger('website_id');$t->timestamps();$t->softDeletes(); });
                $db->table('websites')->insert(['id'=>1,'uuid'=>$db->getDatabaseName()]);
                $hostnameId=$db->table('hostnames')->insertGetId(['fqdn'=>$hostname,'website_id'=>1]);
                // Minimal central plan for the real document-limit check; both tables are disposable.
                $schema->create('plans',function ($t) { $t->increments('id');$t->string('name');$t->integer('limit_documents');$t->boolean('include_sale_notes_limit_documents')->default(false); });
                $schema->create('clients',function ($t) { $t->increments('id');$t->unsignedBigInteger('hostname_id');$t->string('name');$t->unsignedInteger('plan_id');$t->integer('whatsapp_messages_limit_override')->nullable(); });
                $db->table('plans')->insert(['id'=>1,'name'=>'Plan HTTP temporal','limit_documents'=>0]);
                $db->table('clients')->insert(['hostname_id'=>$hostnameId,'name'=>'Cliente HTTP temporal','plan_id'=>1]);
            } else {
                \Tests\Support\BenchmarkTenantCohort::register($central, $db, $hostname, $companyMarker, $app);
                $db->table('companies')->update(['name'=>$companyMarker,'trade_name'=>$companyMarker]);
                $db->table('persons')->where('id', $input['customer_id'])->update(['name'=>$documentMarker]);
                $input['customer']['name']=$documentMarker;
            }
            $token=bin2hex(random_bytes(30));
            $db->table('users')->where('id',$input['user_id'])->update(['api_token'=>$token,'email_verified_at'=>'2026-10-19 12:00:00']);
            $db->table('configurations')->update(['auto_send_hka'=>false]);
            $temp=sys_get_temp_dir().'/pro9-http-'.bin2hex(random_bytes(6));
            mkdir($temp,0700);
            foreach (['framework/views','framework/cache','framework/sessions','logs','tenant'] as $directory) mkdir($temp.'/'.$directory,0700,true);
            $sessionId=bin2hex(random_bytes(20));$csrf=bin2hex(random_bytes(20));
            file_put_contents($temp.'/framework/sessions/'.$sessionId,serialize([
                '_token'=>$csrf,'login_web_'.sha1(\Illuminate\Auth\SessionGuard::class)=>$input['user_id'],
            ]));
            $encrypter=new \Illuminate\Encryption\Encrypter(str_repeat('k',32),'AES-256-CBC');
            $cookieName = 'sab_exact_http_session';
            $cookie=$encrypter->encrypt(\Illuminate\Cookie\CookieValuePrefix::create($cookieName,str_repeat('k',32)).$sessionId,false);
            $private=$temp.'/fixture.json';$profileSql=getenv('PRO9_POSTGRES_QUERY_PROFILE')==='1';
            $configuration=['connection'=>$db->getConfig(),'storage'=>$temp,'profile_sql'=>$profileSql];
            if ($central !== null) $configuration += ['benchmark_central'=>$central['connection'],'benchmark_tenancy_key'=>$central['key']];
            file_put_contents($private,json_encode($configuration,JSON_THROW_ON_ERROR));chmod($private,0600);
            $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);
            if (!$socket) throw new \RuntimeException($error);
            $address=stream_socket_get_name($socket,false);fclose($socket);
            $env=getenv();$env['PRO9_EXACT_HTTP_FIXTURE']=$private;$env['APP_ENV']='testing';
            $server=proc_open([PHP_BINARY,'-d','display_errors=0','-S',$address,dirname(__DIR__).'/Support/exact_http_router.php'],
                [0=>['pipe','r'],1=>['file',$temp.'/server.log','a'],2=>['file',$temp.'/server.log','a']],$pipes,dirname(__DIR__,2),$env);
            if (!is_resource($server)) throw new \RuntimeException('No se pudo abrir HTTP temporal.');
            fclose($pipes[0]);
            for ($attempt=0;$attempt<100;$attempt++) {
                $probe=@stream_socket_client('tcp://'.$address,$errno,$error,0.1);
                if ($probe) { fclose($probe);break; }
                usleep(50000);
            }
            $reference=null;
            if ($reference) {
                if (!is_file($reference)) throw new \RuntimeException('Falta la fuente congelada del controlador.');
                $privateReference=$temp.'/reference.json';
                file_put_contents($privateReference,json_encode(['connection'=>$db->getConfig(),'storage'=>$temp,'controller_reference'=>$reference],JSON_THROW_ON_ERROR));chmod($privateReference,0600);
                $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);$referenceAddress=stream_socket_get_name($socket,false);fclose($socket);
                $referenceEnv=$env;$referenceEnv['PRO9_EXACT_HTTP_FIXTURE']=$privateReference;
                $referenceServer=proc_open([PHP_BINARY,'-d','display_errors=0','-S',$referenceAddress,dirname(__DIR__).'/Support/exact_http_router.php'],
                    [0=>['pipe','r'],1=>['file',$temp.'/reference.log','a'],2=>['file',$temp.'/reference.log','a']],$referencePipes,dirname(__DIR__,2),$referenceEnv);
                if (!is_resource($referenceServer)) throw new \RuntimeException('No se pudo abrir HTTP de referencia.');
                fclose($referencePipes[0]);
                for ($attempt=0;$attempt<100;$attempt++) {
                    $probe=@stream_socket_client('tcp://'.$referenceAddress,$errno,$error,0.1);
                    if ($probe) { fclose($probe);break; }usleep(50000);
                }
            }
            $get=static function (string $path, bool $authenticated=true, ?array $payload=null,bool $useReference=false,string $method='POST',?string $host=null) use ($address,$referenceAddress,$token,$cookieName,$cookie,$csrf,$hostname): array {
                $handle=curl_init('http://'.($useReference ? $referenceAddress : $address).$path);$headers=[];
                curl_setopt_array($handle,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>60,
                    CURLOPT_HTTPHEADER=>array_filter(['Host: '.($host ?? $hostname),'Accept: application/json','Content-Type: application/json',
                        'X-Requested-With: XMLHttpRequest',$authenticated ? 'Authorization: Bearer '.$token : null,
                        $authenticated && !str_starts_with($path,'/api/') ? 'Cookie: '.$cookieName.'='.rawurlencode($cookie) : null,
                        $authenticated ? 'X-CSRF-TOKEN: '.$csrf : null]),
                    CURLOPT_HEADERFUNCTION=>static function ($handle,$line) use (&$headers) { $parts=explode(':',$line,2);if (count($parts)===2) $headers[strtolower(trim($parts[0]))]=trim($parts[1]);return strlen($line); }]);
                if ($payload!==null) {
                    curl_setopt($handle,CURLOPT_POSTFIELDS,json_encode($payload,JSON_THROW_ON_ERROR));
                    curl_setopt($handle,CURLOPT_CUSTOMREQUEST,$method);
                }
                $body=curl_exec($handle);$code=curl_getinfo($handle,CURLINFO_HTTP_CODE);$error=curl_error($handle);curl_close($handle);
                if ($body===false) throw new \RuntimeException($error);
                return [$code,$body,$headers];
            };

            if ($central !== null) {
                $barrier(0, 'identity', 0);
                [$status,$identity]=$get('/api/company');
                $peerHost='exact-http-'.($member === 'a' ? 'b' : 'a').'.example.test';
                [$unauthorized]=$get('/api/company',host:$peerHost);
                if ($status!==200 || !str_contains($identity,$companyMarker) || $unauthorized!==401) {
                    throw new RuntimeException('Falló la identidad o el aislamiento del hostname de medición.');
                }
            }
            $db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);
            $input['auto_send_hka']=false;
            $first=(new \App\CoreFacturalo\Facturalo())->save($input)->getDocument();
            $template=(array)$db->table('documents')->where('id',$first->id)->first();
            $invoice=(array)$db->table('invoices')->where('document_id',$first->id)->first();
            $line=(array)$db->table('document_items')->where('document_id',$first->id)->first();
            $resources=static function (): array {
                $result=[];
                foreach (['cpu.stat','cpu.pressure','memory.current','memory.events'] as $name) {
                    $file='/sys/fs/cgroup/'.$name;
                    if (is_readable($file)) $result[$name]=trim(file_get_contents($file));
                }
                return $result;
            };
            $report=['engine'=>$db->getDriverName(),'server_version'=>$db->selectOne('SELECT version() AS version')->version,'php_version'=>PHP_VERSION,'samples'=>30,'warmups'=>3,'temporary_database_pattern'=>'pro9_fiscal_test_*','profile_sql'=>$profileSql,'resources_start'=>$resources(),'cases'=>[]];
            if ($member !== null) {
                $report['tenant']=['member'=>$member,'hostname'=>$hostname,'database_sha256'=>hash('sha256',$db->getDatabaseName()),
                    'central_sha256'=>hash('sha256',$central['connection']['database']),'isolation_verified'=>true];
            }
            foreach ([1000,10000] as $count) {
                $current=$db->table('documents')->count();
                for ($offset=$current;$offset<$count;$offset+=100) {
                    $rows=[];$lines=[];$invoices=[];
                    for ($i=$offset;$i<min($count,$offset+100);$i++) {
                        $row=$template;$row['id']=$i+1;$row['number']=1000+$i;$row['external_id']=(string)\Illuminate\Support\Str::uuid();$row['unique_filename']='demo-J123456789-01-FF01-'.$row['number'];$row['filename']='J123456789-01-FF01-'.$row['number'];$rows[]=$row;
                        $iv=$invoice;unset($iv['id']);$iv['document_id']=$i+1;$invoices[]=$iv;
                        $l=$line;unset($l['id']);$l['document_id']=$i+1;$lines[]=$l;
                    }
                    $db->table('documents')->insert($rows);$db->table('invoices')->insert($invoices);$db->table('document_items')->insert($lines);
                }
                if ($db->getDriverName()==='pgsql') {\App\Database\SequenceSynchronizer::sync($db);$db->statement('ANALYZE documents');$db->statement('ANALYZE document_items');}
                $routes=['http_documents'=>'/documents/records?column=series&value=FF01',
                    'pos_catalog'=>'/pos/items?input=MOCK',
                    'sales_report'=>'/reports/sales/records?period=month&month_start=2026-09&month_end=2026-09&document_type_id=01&establishment_id='.$input['establishment_id']];
                foreach (array_intersect_key($routes, array_flip($requested)) as $label=>$path) {
                    $times=[];$queryCounts=[];$sql=[];$memory=[];$boot=[];$handler=[];$bootCpu=[];$handlerCpu=[];$hash=null;$slowestMs=-1;$slowestProfile=null;
                    $resourcesBefore=$resources();
                    for ($i=-3;$i<30;$i++) {
                        $barrier($count, $label, $i);
                        $start=hrtime(true);[$status,$response,$headers]=$get($path);
                        $elapsed=(hrtime(true)-$start)/1000000;
                        if ($status!==200) throw new \RuntimeException($label.' HTTP '.$status.': '.substr($response,0,1800));
                        $payload=json_decode($response,true,512,JSON_THROW_ON_ERROR);
                        if ($central !== null && (($payload['meta']['total'] ?? null) !== $count || empty($payload['data'])
                            || array_unique(array_column($payload['data'],'customer_name')) !== [$documentMarker])) {
                            throw new RuntimeException('El conjunto devuelto no coincide con la carga de '.$hostname.' '.$count.' '.$label.'.');
                        }
                        if (!isset($headers['x-pro9-test-queries'])) throw new \RuntimeException('Faltan las métricas de la respuesta HTTP.');
                        if ($i>=0) {$times[]=$elapsed;$queryCounts[]=(int)$headers['x-pro9-test-queries'];$sql[]=(float)$headers['x-pro9-test-sql-ms'];$memory[]=(int)$headers['x-pro9-test-peak-memory'];$boot[]=(float)$headers['x-pro9-test-boot-ms'];$handler[]=(float)$headers['x-pro9-test-handler-ms'];$bootCpu[]=(float)$headers['x-pro9-test-boot-cpu-ms'];$handlerCpu[]=(float)$headers['x-pro9-test-handler-cpu-ms'];}
                        if ($i>=0 && $profileSql && $elapsed>$slowestMs) {
                            $profile=json_decode(file_get_contents($temp.'/query-profile.json'),true,512,JSON_THROW_ON_ERROR);
                            if ($profile['path']!==parse_url($path,PHP_URL_PATH)) throw new \RuntimeException('El perfil SQL no corresponde a la solicitud medida.');
                            $slowestMs=$elapsed;$slowestProfile=$profile;
                        }
                        $hash=hash('sha256',$response);
                    }
                    sort($times);$report['cases'][(string)$count][$label]=['p50_ms'=>$times[14],'p95_ms'=>$times[28],'queries_max'=>max($queryCounts),'sql_max_ms'=>max($sql),'memory_peak_bytes'=>max($memory),'response_sha256'=>$hash,'samples_ms'=>$times];
                    sort($boot);sort($handler);$report['cases'][(string)$count][$label]+=['bootstrap_p95_ms'=>$boot[28],'handler_p95_ms'=>$handler[28]];
                    sort($bootCpu);sort($handlerCpu);$report['cases'][(string)$count][$label]+=[
                        'bootstrap_cpu_p95_ms'=>$bootCpu[28],'handler_cpu_p95_ms'=>$handlerCpu[28],
                        'resources_before'=>$resourcesBefore,'resources_after'=>$resources()];
                    if ($profileSql) $report['cases'][(string)$count][$label]['slowest_request_profile']=['elapsed_ms'=>$slowestMs,'profile'=>$slowestProfile];
                }
                fwrite(STDERR, 'Cargas medidas para '.$count.' documentos: '.implode(', ', array_keys($report['cases'][(string)$count])).PHP_EOL);
            }
            $report['resources_end']=$resources();
            echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
        } finally {
            foreach ([$server,$referenceServer] as $process) if (is_resource($process)) {proc_terminate($process);proc_close($process);}
            if ($temp) (new \Illuminate\Filesystem\Filesystem())->deleteDirectory($temp);
            if ($fixture->ready) $fixture->stop();
        }
// ######### FIN CAMBIO POSTGRESQL 17
