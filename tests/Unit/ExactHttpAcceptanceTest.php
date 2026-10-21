<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExactHttpAcceptanceTest extends TestCase
{
    public function test_production_kernel_routes_and_api_authentication_run_against_disposable_mysql(): void
    {
        if (getenv('PRO9_FISCAL_MYSQL_TESTS') !== '1') $this->markTestSkipped('Requiere MySQL temporal aislado.');
        $fixture=new class extends FiscalEmissionSchemaTest {
            public bool $ready=false;
            public function start(): array { parent::setUp();$this->ready=true;return (new \ReflectionMethod(parent::class,'bootHkaFixture'))->invoke($this); }
            public function stop(): void { parent::tearDown(); }
        };
        $server=null;$referenceServer=null;$referenceAddress=null;$temp=null;
        try {
            [$db,$app,$input]=$fixture->start();
            $schema=$db->getSchemaBuilder();
            $schema->create('websites',function ($t) { $t->bigIncrements('id');$t->string('uuid');$t->timestamps();$t->softDeletes(); });
            $schema->create('hostnames',function ($t) { $t->bigIncrements('id');$t->string('fqdn');$t->string('redirect_to')->nullable();$t->boolean('force_https')->default(false);$t->timestamp('under_maintenance_since')->nullable();$t->unsignedBigInteger('website_id');$t->timestamps();$t->softDeletes(); });
            $db->table('websites')->insert(['id'=>1,'uuid'=>$db->getDatabaseName()]);
            $db->table('hostnames')->insert(['fqdn'=>'exact-http.example.test','website_id'=>1]);
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
            $cookie=$encrypter->encrypt(\Illuminate\Cookie\CookieValuePrefix::create('pro9_exact_http_session',str_repeat('k',32)).$sessionId,false);
            $private=$temp.'/fixture.json';
            file_put_contents($private,json_encode(['connection'=>$db->getConfig(),'storage'=>$temp],JSON_THROW_ON_ERROR));chmod($private,0600);
            $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);
            if (!$socket) throw new \RuntimeException($error);
            $address=stream_socket_get_name($socket,false);fclose($socket);
            $env=getenv();$env['PRO9_EXACT_HTTP_FIXTURE']=$private;$env['APP_ENV']='testing';
            $server=proc_open([PHP_BINARY,'-S',$address,dirname(__DIR__).'/Support/exact_http_router.php'],
                [0=>['pipe','r'],1=>['file',$temp.'/server.log','a'],2=>['file',$temp.'/server.log','a']],$pipes,dirname(__DIR__,2),$env);
            if (!is_resource($server)) throw new \RuntimeException('No se pudo abrir HTTP temporal.');
            fclose($pipes[0]);
            for ($attempt=0;$attempt<100;$attempt++) {
                $probe=@stream_socket_client('tcp://'.$address,$errno,$error,0.1);
                if ($probe) { fclose($probe);break; }
                usleep(50000);
            }
            $reference=getenv('PRO9_EXACT_HTTP_REFERENCE_CONTROLLER');
            if ($reference) {
                if (!is_file($reference)) throw new \RuntimeException('Falta la fuente congelada del controlador.');
                $privateReference=$temp.'/reference.json';
                file_put_contents($privateReference,json_encode(['connection'=>$db->getConfig(),'storage'=>$temp,'controller_reference'=>$reference],JSON_THROW_ON_ERROR));chmod($privateReference,0600);
                $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);$referenceAddress=stream_socket_get_name($socket,false);fclose($socket);
                $referenceEnv=$env;$referenceEnv['PRO9_EXACT_HTTP_FIXTURE']=$privateReference;
                $referenceServer=proc_open([PHP_BINARY,'-S',$referenceAddress,dirname(__DIR__).'/Support/exact_http_router.php'],
                    [0=>['pipe','r'],1=>['file',$temp.'/reference.log','a'],2=>['file',$temp.'/reference.log','a']],$referencePipes,dirname(__DIR__,2),$referenceEnv);
                if (!is_resource($referenceServer)) throw new \RuntimeException('No se pudo abrir HTTP de referencia.');
                fclose($referencePipes[0]);
                for ($attempt=0;$attempt<100;$attempt++) {
                    $probe=@stream_socket_client('tcp://'.$referenceAddress,$errno,$error,0.1);
                    if ($probe) { fclose($probe);break; }usleep(50000);
                }
            }
            $get=static function (string $path, bool $authenticated=true, ?array $payload=null,bool $useReference=false) use ($address,$referenceAddress,$token,$cookie,$csrf): array {
                $handle=curl_init('http://'.($useReference ? $referenceAddress : $address).$path);$headers=[];
                curl_setopt_array($handle,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>60,
                    CURLOPT_HTTPHEADER=>array_filter(['Host: exact-http.example.test','Accept: application/json','Content-Type: application/json',
                        'X-Requested-With: XMLHttpRequest',$authenticated ? 'Authorization: Bearer '.$token : null,
                        $authenticated ? 'Cookie: pro9_exact_http_session='.rawurlencode($cookie) : null,
                        $authenticated ? 'X-CSRF-TOKEN: '.$csrf : null]),
                    CURLOPT_HEADERFUNCTION=>static function ($handle,$line) use (&$headers) { $parts=explode(':',$line,2);if (count($parts)===2) $headers[strtolower(trim($parts[0]))]=trim($parts[1]);return strlen($line); }]);
                if ($payload!==null) curl_setopt_array($handle,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload,JSON_THROW_ON_ERROR)]);
                $body=curl_exec($handle);$code=curl_getinfo($handle,CURLINFO_HTTP_CODE);$error=curl_error($handle);curl_close($handle);
                if ($body===false) throw new \RuntimeException($error);
                return [$code,$body,$headers];
            };
            [$code,$body,$headers]=$get('/api/company');
            self::assertSame(200,$code,substr($body,0,3500));
            self::assertStringContainsString('Test fiscal',$body);
            self::assertGreaterThan(0,(int)$headers['x-pro9-test-queries']);
            [$code,$body]=$get('/api/company',false);
            self::assertSame(401,$code,$body);
            $db->table('cash')->insert(['user_id'=>$input['user_id'],'date_opening'=>'2026-10-19','time_opening'=>'12:00:00','state'=>true]);
            $note=new \App\Models\Tenant\SaleNote();
            $note->fill(array_intersect_key($input,array_flip($note->getFillable())));
            $note->forceFill(['prefix'=>'NV','document_type_id'=>'80','series'=>'NV01','number'=>'#','total'=>'0',
                'external_id'=>(string)\Illuminate\Support\Str::uuid(),'user_id'=>$input['user_id'],'state_type_id'=>'01'])->save();
            $line=$note->items()->create(array_replace($input['items'][0],['quantity'=>'1','unit_price'=>'500']));
            [$code,$body]=$get('/sale-notes/items',true,['notes_id'=>[$note->id]]);
            self::assertSame(200,$code,substr($body,0,2500));
            self::assertSame('500',\App\Support\Exact\Decimal::represent(\App\Support\Exact\Decimal::readRequired(json_decode($body,true)['data'][0],'unit_price')));
            if (getenv('PRO9_EXACT_HTTP_PROFILE')==='1') {
                $profileRow=$line->getAttributes();unset($profileRow['id']);
                $db->table('sale_note_items')->where('sale_note_id',$note->id)->delete();
                foreach ([500,500] as $count) $db->table('sale_note_items')->insert(array_fill(0,$count,$profileRow));
                $start=hrtime(true);[$code,$body,$headers]=$get('/sale-notes/items',true,['notes_id'=>[$note->id]]);
                fwrite(STDERR,json_encode(['profile_headers'=>array_filter($headers,fn($key)=>str_starts_with($key,'x-pro9'),ARRAY_FILTER_USE_KEY),'total_ms'=>(hrtime(true)-$start)/1000000,'body_bytes'=>strlen($body)]).PHP_EOL);
                self::assertSame(200,$code,substr($body,0,1500));
            }
            $output=getenv('PRO9_EXACT_HTTP_BENCH_OUTPUT');
            if ($output) {
                $hashes=[];
                foreach (['app/Http/Controllers/Tenant/SaleNoteController.php','app/Http/Kernel.php','routes/web.php','composer.lock','tests/Support/exact_http_router.php'] as $file) $hashes[$file]=hash_file('sha256',dirname(__DIR__,2).'/'.$file);
                foreach (['app','modules','config','routes','bootstrap'] as $directory) {
                    foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__,2).'/'.$directory,\FilesystemIterator::SKIP_DOTS)) as $file) {
                        if ($file->getExtension()==='php' && !str_contains($file->getPathname(),'/bootstrap/cache/')) {
                            $relative=substr($file->getPathname(),strlen(dirname(__DIR__,2))+1);$hashes[$relative]=hash_file('sha256',$file->getPathname());
                        }
                    }
                }
                $result=['scope'=>'HTTP real: kernel, rutas, sesión cifrada, CSRF y MySQL 5.7 temporal. Sólo selección de partidas de notas; no acredita facturación/POS/caja ni central separado.',
                    'reference_head'=>trim(shell_exec('git rev-parse HEAD')),'warmups'=>3,'samples'=>30,
                    'php_version'=>PHP_VERSION,'mysql_version'=>$db->selectOne('SELECT VERSION() AS v')->v,'source_sha256'=>$hashes,
                    'reference_controller_sha256'=>$reference ? hash_file('sha256',$reference):null,
                    'queries_scope'=>'SQL del kernel tras bootstrap; la latencia incluye también bootstrap y transporte HTTP.',
                    'cases'=>[]];
                $row=$line->getAttributes();unset($row['id']);
                foreach ([1,100,1000] as $size) {
                    $db->table('sale_note_items')->where('sale_note_id',$note->id)->delete();
                    for ($offset=0;$offset<$size;$offset+=500) $db->table('sale_note_items')->insert(array_fill(0,min(500,$size-$offset),$row));
                    $times=$queries=$memory=[];$variants=$reference ? ['reference','current'] : ['current'];
                    for ($sample=-3;$sample<30;$sample++) foreach (($sample%2 ? array_reverse($variants) : $variants) as $variant) {
                        $start=hrtime(true);[$code,$body,$headers]=$get('/sale-notes/items',true,['notes_id'=>[$note->id]],$variant==='reference');$elapsed=(hrtime(true)-$start)/1000000;
                        self::assertSame(200,$code,substr($body,0,1500));
                        self::assertCount($size,json_decode($body,true)['data']);
                        if ($sample>=0) {$times[$variant][]=$elapsed;$queries[$variant][]=(int)$headers['x-pro9-test-queries'];$memory[$variant][]=(int)$headers['x-pro9-test-peak-memory'];}
                    }
                    foreach ($variants as $variant) {
                        $sorted=$times[$variant];sort($sorted);
                        $result['cases'][(string)$size][$variant]=['p50_ms'=>$sorted[14],'p95_ms'=>$sorted[28],
                            'queries_max'=>max($queries[$variant]),'memory_peak_bytes'=>max($memory[$variant]),'raw_ms'=>$times[$variant]];
                    }
                    if ($reference) {
                        $base=$result['cases'][(string)$size]['reference']['p95_ms'];$limit=$base+max($base*.2,100);
                        $result['cases'][(string)$size]['allowed_p95_ms']=$limit;
                        $result['cases'][(string)$size]['passed']=$result['cases'][(string)$size]['current']['p95_ms']<=$limit;
                    }
                    if ($initialPath=getenv('PRO9_EXACT_HTTP_INITIAL_BASELINE')) {
                        $initial=json_decode(file_get_contents($initialPath),true,512,JSON_THROW_ON_ERROR);
                        $base=$initial['cases'][(string)$size]['p95_ms'];$limit=$base+max($base*.2,100);
                        $result['cases'][(string)$size]['initial_allowed_p95_ms']=$limit;
                        $result['cases'][(string)$size]['initial_passed']=$result['cases'][(string)$size]['current']['p95_ms']<=$limit;
                    }
                }
                foreach ($hashes as $file=>$hash) self::assertSame($hash,hash_file('sha256',dirname(__DIR__,2).'/'.$file),'Cambió una fuente durante la medición.');
                file_put_contents($output,json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL);
                if ($reference) foreach ($result['cases'] as $case) self::assertTrue($case['passed'],'El p95 excede el presupuesto HTTP.');
                if (getenv('PRO9_EXACT_HTTP_INITIAL_BASELINE')) foreach ($result['cases'] as $case) self::assertTrue($case['initial_passed'],'El p95 excede la referencia inicial de la continuación.');
            }
            // Re-resolve the sources on each request; a stale selection is not authority.
            [$code,$body]=$get('/sale-notes/items',true,['notes_id'=>[$note->id]]);
            $prepared=json_decode($body,true);
            self::assertSame(200,$code,substr($body,0,1500));
            self::assertSame('VES',$prepared['conversion_context']['currency_type_id']);
            self::assertCount(1,$prepared['grouped_items']);
            $other=\App\Support\Exact\LineCalculation::calculate(array_replace($input['items'][0],['unit_price'=>'499','quantity'=>'1']),'16');
            $otherRow=$line->getAttributes();unset($otherRow['id']);
            $otherItem=$input['items'][0]['item'];$otherItem['presentation']=(object)[];
            $otherRow['item']=json_encode($otherItem,JSON_THROW_ON_ERROR);
            foreach (\App\Support\Exact\Persistence::columns($line) as $field) if (isset($other[$field])) $otherRow[$field]=$other[$field];
            $otherRow['calculation_snapshot']=json_encode($other['calculation_snapshot'],JSON_THROW_ON_ERROR);
            $db->table('sale_note_items')->insert($otherRow);
            [$code,$body]=$get('/sale-notes/items',true,['notes_id'=>[$note->id]]);
            self::assertSame(200,$code,substr($body,0,1500));
            self::assertSame(['499','500'],collect(json_decode($body,true)['grouped_items'])->map(fn($row)=>\App\Support\Exact\Decimal::represent(\App\Support\Exact\Decimal::readRequired($row,'unit_price')))->sort()->values()->all());
            [$code,$body]=$get('/sale-notes/items',true,['notes_id'=>[$note->id],'select_all'=>true]);
            self::assertSame(200,$code,substr($body,0,1500));
            $raw=json_decode($body)->data;
            self::assertInstanceOf(\stdClass::class,$raw[array_key_last($raw)]->item->presentation);
            $db->table('sale_notes')->where('id',$note->id)->update(['state_type_id'=>'11']);
            [$code]=$get('/sale-notes/items',true,['notes_id'=>[$note->id]]);self::assertSame(422,$code);
            $db->table('sale_notes')->where('id',$note->id)->update(['state_type_id'=>'01']);
            [$code]=$get('/sale-notes/items',true,['notes_id'=>[$note->id,$note->id]]);self::assertSame(422,$code);
            [$code]=$get('/sale-notes/items',true,['notes_id'=>[99999999]]);self::assertSame(404,$code);
            $foreignContext=$note->replicate();
            $foreignContext->forceFill(['number'=>'#','external_id'=>(string)\Illuminate\Support\Str::uuid(),
                'currency_type_id'=>'USD','exchange_rate_sale'=>'2.00000000'])->save();
            [$code]=$get('/sale-notes/items',true,['notes_id'=>[$note->id,$foreignContext->id]]);
            self::assertSame(422,$code,'Una selección mixta no puede heredar silenciosamente la primera tasa o moneda.');
            $legacy=$note->replicate();
            $legacy->forceFill(['number'=>'#','external_id'=>(string)\Illuminate\Support\Str::uuid()])->save();
            foreach ([1,2] as $position) $legacy->items()->create(array_replace($input['items'][0],[
                'quantity'=>'1','unit_price'=>'100000000000000',
                'discounts'=>[['discount_type_id'=>'00','is_amount'=>true,'amount'=>'1234567890123.12']],
            ]));
            // A stored JSON number is not an invitation to convert it through PHP float.
            $db->table('sale_note_items')->where('sale_note_id',$legacy->id)->update([
                'discounts'=>'[{"discount_type_id":"00","is_amount":true,"amount":1234567890123.12}]',
            ]);
            [$code,$body]=$get('/sale-notes/items',true,['notes_id'=>[$legacy->id]]);
            self::assertSame(200,$code,substr($body,0,1500));
            $grouped=json_decode($body,true)['grouped_items'][0];
            self::assertSame('2469135780246.24',\App\Support\Exact\Decimal::represent(
                $grouped['discounts'][0]['amount_exact']));
        } finally {
            if (is_resource($server)) { proc_terminate($server);proc_close($server); }
            if (is_resource($referenceServer)) { proc_terminate($referenceServer);proc_close($referenceServer); }
            if ($temp) {
                if (is_file($temp.'/server.log')) file_put_contents(sys_get_temp_dir().'/pro9-exact-http-last.log',file_get_contents($temp.'/server.log'));
                (new \Illuminate\Filesystem\Filesystem())->deleteDirectory($temp);
            }
            if ($fixture->ready) $fixture->stop();
        }
    }
}
