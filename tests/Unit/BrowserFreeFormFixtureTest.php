<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Opt-in, temporary browser environment; never uses the application's databases. */
final class BrowserFreeFormFixtureTest extends TestCase
{
    public function test_serve_temporary_free_form_tenant_until_released(): void
    {
        if (getenv('PRO9_BROWSER_FIXTURE') !== '1' || getenv('PRO9_FISCAL_MYSQL_TESTS') !== '1') {
            self::markTestSkipped('La sesión de navegador temporal se inicia sólo explícitamente.');
        }
        $fixture=new class extends FiscalEmissionSchemaTest {
            public function start(): array { parent::setUp(); return (new \ReflectionMethod(parent::class,'bootHkaFixture'))->invoke($this); }
            public function stop(): void { parent::tearDown(); }
        };
        $server=null; $temp=null;
        try {
            [$db,$app,$input]=$fixture->start();
            $schema=$db->getSchemaBuilder();
            // This fixture shares the tenant and system connection; add only the
            // central configuration fields read by the authenticated layout.
            $schema->table('configurations',function ($t) {
                $t->boolean('use_login_global')->default(false);
                $t->boolean('tenant_show_ads')->default(false);
                $t->text('tenant_image_ads')->nullable();
            });
            $schema->create('websites',function ($t) { $t->bigIncrements('id');$t->string('uuid');$t->timestamps();$t->softDeletes(); });
            $schema->create('hostnames',function ($t) { $t->bigIncrements('id');$t->string('fqdn');$t->string('redirect_to')->nullable();$t->boolean('force_https')->default(false);$t->timestamp('under_maintenance_since')->nullable();$t->unsignedBigInteger('website_id');$t->timestamps();$t->softDeletes(); });
            $schema->create('plans',function ($t) { $t->increments('id');$t->string('name');$t->integer('limit_documents');$t->boolean('include_sale_notes_limit_documents')->default(false); });
            $schema->create('clients',function ($t) { $t->increments('id');$t->unsignedBigInteger('hostname_id');$t->string('name');$t->unsignedInteger('plan_id');$t->integer('whatsapp_messages_limit_override')->nullable(); });
            if (getenv('PRO9_BROWSER_STOREFRONT_PRICES') === '1') {
                $schema->table('clients',function ($t) { $t->string('number')->nullable(); });
                $schema->create('column_visibility_configs',function ($t) { $t->bigIncrements('id');$t->string('module')->unique();$t->json('columns');$t->timestamps(); });
            }
            $db->table('websites')->insert(['id'=>1,'uuid'=>$db->getDatabaseName()]);
            $hostname=$db->table('hostnames')->insertGetId(['fqdn'=>'qa-forma-libre.localhost','website_id'=>1]);
            $db->table('plans')->insert(['id'=>1,'name'=>'Plan temporal','limit_documents'=>0]);
            $db->table('clients')->insert(['hostname_id'=>$hostname,'name'=>'Empresa de pruebas','plan_id'=>1]);
            $db->table('companies')->update(['fiscal_emission_mode'=>'free_form','fiscal_environment'=>'demo']);
            // A continuous-unit item is available to the Garage/POS workflows.
            $db->table('items')->where('internal_id','MOCK-ITEM-VES-001')->update(['calculate_quantity'=>true,'unit_type_id'=>'LT']);
            $db->table('configurations')->update(['auto_send_hka'=>false,
                'login'=>json_encode(['type'=>'image','image'=>'/images/login-v2.svg','position_form'=>'right','show_logo_in_form'=>false,
                    'position_logo'=>'top-left','show_socials'=>false],JSON_THROW_ON_ERROR)]);
            if (getenv('PRO9_BROWSER_LIST_TOTALS') === '1') {
                $db->table('configurations')->update(['show_totals_on_cpe_list'=>true]);
            }
            $today=(new \DateTimeImmutable('now',new \DateTimeZone('America/Caracas')))->format('Y-m-d');
            $db->table('exchange_rates')->updateOrInsert(['date'=>$today],['date_original'=>$today,
                'sale_original'=>'40','purchase_original'=>'40','sale'=>'40','purchase'=>'40']);
            if (getenv('PRO9_BROWSER_LIST_TOTALS') === '1') {
                $pendingCommissions=getenv('PRO9_BROWSER_PENDING_COMMISSIONS') === '1';
                $yesterday=(new \DateTimeImmutable($today))->modify('-1 day')->format('Y-m-d');
                if ($pendingCommissions) {
                    $db->table('configurations')->update(['finances'=>json_encode(['restriction_expired_debt'=>true,'max_expired_days'=>3])]);
                    $db->table('payment_method_types')->where('id','10')->update(['collection_kind'=>'cash','currency_type_id'=>'USD',
                        'cash_currency_type_id'=>'USD','is_cash'=>1,'is_active'=>1]);
                    \Modules\Sale\Models\PendingAccountCommission::create(['seller_id'=>$input['user_id'],'commission_type'=>'porcentaje','amount'=>'10']);
                }
                if (getenv('PRO9_BROWSER_COMMISSIONS') === '1') {
                    $db->table('items')->where('id',$input['items'][0]['item_id'])->update(['commission_type'=>'percentage','commission_amount'=>'10']);
                }
                foreach (['VES', 'USD'] as $currency) {
                    $case = array_replace($input, [
                        'external_id'=>(string) \Illuminate\Support\Str::uuid(),
                        'date_of_issue'=>$today, 'currency_type_id'=>$currency,
                        'exchange_rate_sale'=>'40', 'payments'=>[], 'payment_condition_id'=>'02',
                    ]);
                    if (getenv('PRO9_BROWSER_COMMISSIONS') === '1' || $pendingCommissions) $case['seller_id']=$input['user_id'];
                    if ($pendingCommissions) {
                        $case['date_of_issue']=$yesterday;
                        $case['invoice']['date_of_due']=$yesterday;
                    }
                    $document = (new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($case)->getDocument()->fresh();
                    self::assertSame($currency, $document->currency_type_id);
                    self::assertSame('232', \App\Support\Exact\Decimal::represent($document->exactValue('total')));
                    if (getenv('PRO9_BROWSER_CASH_REPORT') === '1') {
                        $cashId=$db->table('cash')->where('user_id',$input['user_id'])->where('state',true)->value('id');
                        self::assertNotNull($cashId);
                        $db->table('cash_documents')->updateOrInsert(['cash_id'=>$cashId,'document_id'=>$document->id]);
                    }
                    if ($pendingCommissions) {
                        \App\Services\Fiscal\FiscalDocumentPersistence::payment($document,[
                            'date_of_payment'=>$today,'payment_method_type_id'=>$currency === 'USD' ? '10' : '01',
                            'payment_destination_id'=>'cash','currency_type_id'=>$currency,
                            'original_amount'=>$currency === 'USD' ? '5' : '10','exchange_rate'=>'40',
                            'igtf_status'=>'not_applicable','operation_key'=>(string) \Illuminate\Support\Str::uuid(),
                        ]);
                    }
                }
            }
            $db->table('users')->where('id',$input['user_id'])->update([
                'email'=>'qa-forma-libre@example.test','password'=>password_hash('pro9-temporal-qa',PASSWORD_BCRYPT),
                'email_verified_at'=>'2026-10-19 12:00:00',
            ]);
            // Expose commercial workflows to this synthetic tenant only.
            foreach (['order-note','quotations','dispatches','technical-service','list_document','new_document'] as $value) {
                $level=$db->table('module_levels')->where('value',$value)->first();
                self::assertNotNull($level, "El catálogo temporal debe incluir $value.");
                $db->table('module_user')->updateOrInsert(['user_id'=>$input['user_id'],'module_id'=>$level->module_id]);
                $db->table('module_level_user')->updateOrInsert(['user_id'=>$input['user_id'],'module_level_id'=>$level->id]);
            }
            if (getenv('PRO9_BROWSER_STOREFRONT_PRICES') === '1') {
                $quotationId=$db->table('quotations')->insertGetId([
                    'user_id'=>$input['user_id'],'external_id'=>(string)\Illuminate\Support\Str::uuid(),
                    'establishment_id'=>$input['establishment_id'],'establishment'=>json_encode(\App\CoreFacturalo\Requests\Inputs\Common\EstablishmentInput::set($input['establishment_id'])),
                    'source'=>'ecommerce','fiscal_environment'=>'demo','state_type_id'=>'05','prefix'=>'COT','series'=>'COT1','number'=>1,
                    'date_of_issue'=>$today,'time_of_issue'=>'09:00:00','customer_id'=>$input['customer_id'],
                    'customer'=>json_encode(\App\CoreFacturalo\Requests\Inputs\Common\PersonInput::set($input['customer_id'])),
                    'currency_type_id'=>'VES','exchange_rate_sale'=>'40','total'=>'0','created_at'=>now(),'updated_at'=>now(),
                ]);
                \App\Models\Tenant\QuotationItem::create(array_replace($input['items'][0],[
                    'quotation_id'=>$quotationId,'quantity'=>'0.333333333333','unit_price'=>'3','affectation_igv_type_id'=>'20']));
            }
            if (getenv('PRO9_BROWSER_REPORTS') === '1') {
                $module=$db->table('modules')->where('value','reports')->first();
                self::assertNotNull($module);
                $db->table('module_user')->updateOrInsert(['user_id'=>$input['user_id'],'module_id'=>$module->id]);
            }
            if (getenv('PRO9_BROWSER_HOTEL') === '1') {
                $level=$db->table('module_levels')->where('value','hotels_reception')->first();
                self::assertNotNull($level);
                $db->table('module_user')->updateOrInsert(['user_id'=>$input['user_id'],'module_id'=>$level->module_id]);
                $db->table('module_level_user')->updateOrInsert(['user_id'=>$input['user_id'],'module_level_id'=>$level->id]);
                $category=$db->table('hotel_categories')->insertGetId(['description'=>'Categoría temporal','establishment_id'=>$input['establishment_id']]);
                $floor=$db->table('hotel_floors')->insertGetId(['description'=>'Piso temporal','establishment_id'=>$input['establishment_id']]);
                $paidRoom=getenv('PRO9_BROWSER_HOTEL_PAID') === '1';
                $roomProduct=$paidRoom ? \App\Models\Tenant\Item::where('internal_id','MOCK-SERV-VES-001')->firstOrFail() : null;
                $roomItemId=$paidRoom ? $roomProduct->id : $input['items'][0]['item_id'];
                $room=$db->table('hotel_rooms')->insertGetId(['name'=>'Prueba 101','hotel_category_id'=>$category,'hotel_floor_id'=>$floor,
                    'item_id'=>$roomItemId,'status'=>'OCUPADO','establishment_id'=>$input['establishment_id']]);
                $db->table('hotel_rents')->insert(['id'=>1,'customer_id'=>$input['customer_id'],'customer'=>json_encode($input['customer']),
                    'hotel_room_id'=>$room,'payment_status'=>$paidRoom ? 'PAID' : 'DEBT','output_date'=>$today,'output_time'=>'12:00','establishment_id'=>$input['establishment_id']]);
                $roomItem=$input['items'][0];
                if ($paidRoom) { $roomItem['item_id']=$roomItemId; $roomItem['item']=$roomProduct->toArray(); }
                $roomItem['price_input']=['amount'=>'116','currency_type_id'=>'VES','includes_iva'=>true];
                $db->table('hotel_rent_items')->insert(['type'=>'HAB','hotel_rent_id'=>1,'item_id'=>$roomItem['item_id'],
                    'item'=>json_encode($roomItem,JSON_THROW_ON_ERROR),'payment_status'=>$paidRoom ? 'PAID' : 'DEBT']);
            }
            $temp=sys_get_temp_dir().'/pro9-browser-'.bin2hex(random_bytes(6));
            mkdir($temp,0700);
            foreach (['framework/views','framework/cache','framework/sessions','logs','tenant'] as $directory) mkdir($temp.'/'.$directory,0700,true);
            $private=$temp.'/fixture.json';
            file_put_contents($private,json_encode(['connection'=>$db->getConfig(),'storage'=>$temp],JSON_THROW_ON_ERROR));
            chmod($private,0600);
            file_put_contents($temp.'/alive','1');
            $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);
            if (!$socket) throw new \RuntimeException($error);
            $address=stream_socket_get_name($socket,false); fclose($socket);
            $env=getenv(); $env['PRO9_EXACT_HTTP_FIXTURE']=$private; $env['APP_ENV']='testing';
            $server=proc_open([PHP_BINARY,'-S',$address,'-t',base_path('public'),dirname(__DIR__).'/Support/exact_http_router.php'],
                [0=>['pipe','r'],1=>['file',$temp.'/server.log','a'],2=>['file',$temp.'/server.log','a']],$pipes,base_path(),$env);
            if (!is_resource($server)) throw new \RuntimeException('No se pudo iniciar el servidor temporal.');
            fclose($pipes[0]);
            for ($attempt=0;$attempt<100;$attempt++) {
                $probe=@stream_socket_client('tcp://'.$address,$errno,$error,0.1);
                if ($probe) { fclose($probe); break; }
                usleep(50000);
            }
            if (!$probe) throw new \RuntimeException('El servidor temporal no respondió.');
            $context=stream_context_create(['http'=>['header'=>"Host: qa-forma-libre.localhost\r\n",'timeout'=>20,'ignore_errors'=>true]]);
            $login=file_get_contents('http://'.$address.'/login',false,$context);
            self::assertStringContainsString('Bienvenido',$login,'No se pudo cargar el acceso temporal.');
            self::assertStringContainsString('200', $http_response_header[0] ?? '');
            self::assertNotEmpty(array_filter($http_response_header,fn ($header)=>str_starts_with($header,'Content-Security-Policy:')));
            $manifest=json_decode(file_get_contents(base_path('public/build/manifest.json')),true,512,JSON_THROW_ON_ERROR);
            $asset=$manifest['resources/js/app.js']['file'] ?? null;
            if (!$asset) throw new \RuntimeException('No se encontró el asset compilado de la aplicación.');
            $stream=fopen('http://'.$address.'/build/'.$asset,'rb',false,$context);
            self::assertNotFalse($stream,'No se pudo cargar el asset compilado.');
            self::assertNotSame('',fread($stream,128)); fclose($stream);
            $themes=json_decode(file_get_contents('http://'.$address.'/json/themes/themes.json',false,$context),true,512,JSON_THROW_ON_ERROR);
            self::assertNotEmpty($themes,'No se pudo cargar el catálogo de temas.');
            $sessionId=bin2hex(random_bytes(20));
            file_put_contents($temp.'/framework/sessions/'.$sessionId,serialize([
                '_token'=>bin2hex(random_bytes(20)),
                'login_web_'.sha1(\Illuminate\Auth\SessionGuard::class)=>$input['user_id'],
            ]));
            $key=str_repeat('k',32);
            $cookie=(new \Illuminate\Encryption\Encrypter($key,'AES-256-CBC'))->encrypt(
                \Illuminate\Cookie\CookieValuePrefix::create('pro9_exact_http_session',$key).$sessionId,false);
            $authenticated=stream_context_create(['http'=>['header'=>"Host: qa-forma-libre.localhost\r\nCookie: pro9_exact_http_session=".rawurlencode($cookie)."\r\n",'timeout'=>20,'ignore_errors'=>true]]);
            $documents=file_get_contents('http://'.$address.'/documents',false,$authenticated);
            self::assertStringContainsString('200', $http_response_header[0] ?? '');
            self::assertStringContainsString('Forma libre',$documents,'El listado autenticado debe cargar en Forma libre.');
            self::assertStringContainsString('Facturas y notas',$documents,'El menú debe identificar los comprobantes vigentes.');
            self::assertStringNotContainsString('Boleta/factura',$documents,'El menú no debe ofrecer tipos retirados.');
            fwrite(STDOUT,"\nBROWSER_FIXTURE http://qa-forma-libre.localhost:".substr(strrchr($address,':'),1)." $temp\n");
            $deadline=time()+1800;
            do {
                clearstatcache(true,$temp.'/alive');
                if (!is_file($temp.'/alive') || !proc_get_status($server)['running'] || time()>=$deadline) break;
                sleep(1);
            } while (true);
            self::assertTrue(true);
        } finally {
            if (is_resource($server)) { proc_terminate($server); proc_close($server); }
            if ($temp && is_dir($temp)) (new \Illuminate\Filesystem\Filesystem())->deleteDirectory($temp);
            $fixture->stop();
        }
    }
}
