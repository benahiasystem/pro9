<?php
/** Read-only verification of synthetic notes already emitted and annulled by an acceptance matrix. */
require dirname(__DIR__).'/vendor/autoload.php';
$root=dirname(__DIR__);$host=$argv[1] ?? '';$source=realpath($root.'/'.($argv[2] ?? ''));
if (getenv('PRO9_FISCAL_MYSQL_TESTS') !== '1' || !$source || !str_starts_with($source,$root.'/informes/evidencias/')) exit("Specify a synthetic evidence report.\n");
$matrix=json_decode(file_get_contents($source),true,512,JSON_THROW_ON_ERROR);
if (empty($matrix['synthetic_commercial_data'])) exit("Only synthetic acceptance evidence may be used.\n");
$app=require $root.'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$hostname=Hyn\Tenancy\Models\Hostname::where('fqdn',$host)->firstOrFail();app(Hyn\Tenancy\Environment::class)->tenant($hostname->website);
$company=App\Models\Tenant\Company::firstOrFail();if($company->fiscal_environment!=='demo'||$company->fiscal_emission_mode!=='digital')exit("DEMO only.\n");
$transport=app(App\Services\Fiscal\HkaTransport::class);$token=$transport->token($company);
$report=['date'=>date('c'),'environment'=>'demo','read_only'=>true,'source'=>basename($source),'email_sent'=>false,'cases'=>[]];$path=$root.'/informes/evidencias/notas_hka_anulacion_consultas_'.date('Y_m_d_His').'.json';
foreach ($matrix['cases'] as $case) {
    if (($case['annulment']['status'] ?? '') !== 'completed') continue;
    $id=['tipoDocumento'=>$case['type']==='07'?'02':'03','numeroDocumento'=>(string)$case['number'],'serie'=>''];
    if (!empty($case['transaction_id'])) $id['transaccionId']=$case['transaction_id'];
    $response=$transport->status($token,$id);$body=$response->json();$state=$body['estado'] ?? [];
    $verified=$response->successful() && (string)($body['codigo'] ?? '')==='200' && empty($body['validaciones']) && ($state['estadoDocumento'] ?? '')==='Anulada'
        && (!isset($id['transaccionId']) || ($state['transaccionId'] ?? '')===$id['transaccionId']) && ($state['tipoDocumento'] ?? '')===$id['tipoDocumento']
        && (string)($state['numeroDocumento'] ?? '')===$id['numeroDocumento'] && ($state['numeroControl'] ?? '')===$case['control'];
    $report['cases'][]=['type'=>$id['tipoDocumento'],'number'=>$id['numeroDocumento'],'control'=>$case['control'],'status'=>$state['estadoDocumento'] ?? null,'code'=>$body['codigo'] ?? null,'verified'=>$verified];
    file_put_contents($path,json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    echo json_encode(['query'=>count($report['cases']),'verified'=>$verified])."\n";
}
