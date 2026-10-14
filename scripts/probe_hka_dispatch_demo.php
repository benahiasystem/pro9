<?php
/** Explicit synthetic protocol probe. All commercial writes use a disposable database. */
require dirname(__DIR__).'/vendor/autoload.php';
use App\Models\Tenant\Company;
use App\Services\Fiscal\{HkaAuthentication,HkaTransport,HkaResponse,HkaPayloadBuilder};
use Illuminate\Support\Str;
$root=dirname(__DIR__);
if (getenv('PRO9_FISCAL_MYSQL_TESTS')!=='1' || ($argv[1] ?? '')!=='bbc.localhost') exit("Enable isolated tests and specify bbc.localhost.\n");
$app=require $root.'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$host=Hyn\Tenancy\Models\Hostname::where('fqdn',$argv[1])->firstOrFail();app(Hyn\Tenancy\Environment::class)->tenant($host->website);
$source=Company::firstOrFail();
if ($source->fiscal_environment!=='demo' || $source->fiscal_emission_mode!=='digital') exit("DEMO digital required.\n");
$credentials=HkaAuthentication::credentials($source);$issuer=$source->only(['number','name','trade_name']);unset($source);
$fixture=new class('test_hka_emission_runs_after_outer_commit_and_recovers_without_duplicate_sales') extends Tests\Unit\FiscalEmissionSchemaTest {
    public function start() { $this->setUp();$m=new ReflectionMethod(parent::class,'bootHkaFixture');$m->setAccessible(true);return $m->invoke($this); }
    public function stop() { $this->tearDown(); }
};
try {
    [$db,$testApp,$input]=$fixture->start();
    $key=random_bytes(32);config(['app.key'=>'base64:'.base64_encode($key)]);$testApp->instance('encrypter',new Illuminate\Encryption\Encrypter($key,'AES-256-CBC'));Illuminate\Support\Facades\Facade::clearResolvedInstance('encrypter');
    $db->table('companies')->update(['fiscal_credentials'=>null]);$company=Company::firstOrFail();$company->forceFill($issuer)->save();$company->fiscal_credentials=json_encode($credentials);$company->save();unset($credentials,$key);
    $operation=(string)Str::uuid();$number=(string)random_int(1600000000,1900000000);
    $identity=['tipoDocumento'=>'04','serie'=>'','numeroDocumento'=>$number,'fechaEmision'=>date('d/m/Y'),'horaEmision'=>date('h:i:s a'),'tipoTransaccion'=>'01','tipoDeVenta'=>'Interna','moneda'=>'VES','transaccionId'=>HkaPayloadBuilder::transactionId($operation),'sucursal'=>'0000'];
    $payload=['documentoElectronico'=>['encabezado'=>['identificacionDocumento'=>$identity,'comprador'=>['tipoIdentificacion'=>'J','numeroIdentificacion'=>'000000004','razonSocial'=>'PRUEBA-OE-CLIENTE','direccion'=>'Direccion sintetica Caracas','pais'=>'VE','notificar'=>'No']],
        'guiaDespacho'=>['esGuiaDespacho'=>'1','motivoTraslado'=>'Reparación o perfeccionamiento','descripcionServicio'=>'Reparación sintética DEMO','pesoOVolumenTotal'=>'2.00 KGM','conductor'=>['nombreCompleto'=>'PRUEBA-OE-CONDUCTOR','tipoIdentificacion'=>'V','numeroIdentificacion'=>'12345678','tipoLicencia'=>'Tercera','infoContacto'=>'04120000000'],'vehiculo'=>['tipoVehiculo'=>'Camion de prueba','numeroTransporte'=>'PRUEBAOE01','numeroPlaca'=>'PRUEBA01'],'transportista'=>['razonSocial'=>'PRUEBA-OE-TRANSPORTE','numeroIdentificacion'=>'J000000004','domicilioFiscal'=>'Direccion sintetica Caracas']],
        'transporte'=>['tipo'=>'Privado','origen'=>'Direccion sintetica Caracas','destino'=>'Direccion sintetica Valencia','fechaSalida'=>date('d/m/Y')],
        'detallesItems'=>[['numeroLinea'=>'1','indicadorBienoServicio'=>'1','descripcion'=>'PRUEBA-OE-BIEN','cantidad'=>'2.0000','unidadMedida'=>'C62']]]];
    if (($argv[2] ?? '')==='price-only') $payload['documentoElectronico']['detallesItems'][0]['precioUnitario']='0.0000';
    if (($argv[2] ?? '')==='zero-totals') {
        $payload['documentoElectronico']['encabezado']['totales']=['nroItems'=>'1','montoGravadoTotal'=>'0.00','montoExentoTotal'=>'0.00','subtotal'=>'0.00','totalIVA'=>'0.00','montoTotalConIVA'=>'0.00','totalAPagar'=>'0.00'];
        $payload['documentoElectronico']['detallesItems'][0]+= ['precioUnitario'=>'0.0000','precioItem'=>'0.00','codigoImpuesto'=>'E','tasaIVA'=>'0.00','valorIVA'=>'0.00','valorTotalItem'=>'0.00'];
    }
    if (in_array($argv[2] ?? '',['priced','priced-no-totals'],true)) {
        $payload['documentoElectronico']['detallesItems'][0]+=['precioUnitario'=>'100.0000','precioItem'=>'200.00','codigoImpuesto'=>'G','tasaIVA'=>'16.00','valorIVA'=>'32.00','valorTotalItem'=>'232.00'];
        $payload['documentoElectronico']['encabezado']['totales']=['nroItems'=>'1','montoGravadoTotal'=>'200.00','montoExentoTotal'=>'0.00','subtotal'=>'200.00','totalIVA'=>'32.00','montoTotalConIVA'=>'232.00','totalAPagar'=>'232.00'];
    }
    if (($argv[2] ?? '')==='priced-no-totals') unset($payload['documentoElectronico']['encabezado']['totales']);
    (new App\Services\Fiscal\HkaSchemaValidator)->validate($payload);
    $transport=app(HkaTransport::class);$token=$transport->token($company);$response=$transport->emission($token,$payload);
    $body=$response->json();$result=HkaResponse::interpret($response->status(),$body,$identity);
    $validations=array_map(fn($s)=>preg_replace('/[VEJPGCR][-0-9]{8,15}/i','[identidad]',(string)$s),$body['validaciones'] ?? []);
    $report=['environment'=>'demo','synthetic'=>true,'operation_key'=>$operation,'number'=>$number,'result'=>$result,'validations'=>$validations,'payload'=>$payload];
    // Payload contains exclusively synthetic people/transport and no credentials.
    file_put_contents($root.'/informes/evidencias/ordenes_hka_probe_'.date('Y_m_d_His').'.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    echo json_encode(['result'=>$result,'validations'=>$validations],JSON_UNESCAPED_UNICODE),PHP_EOL;
} finally { $fixture->stop(); }
