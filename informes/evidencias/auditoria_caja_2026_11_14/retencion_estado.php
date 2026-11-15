<?php
if (!defined('STDOUT')) define('STDOUT',fopen('php://stdout','w'));
if (!defined('STDERR')) define('STDERR',fopen('php://stderr','w'));
require getcwd().'/vendor/autoload.php'; $app=require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\DB::purge('tenant');
config(['database.connections.tenant'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'']]);
Illuminate\Support\Facades\Schema::connection('tenant')->create('cash_document_credits',function($t){$t->increments('id');$t->integer('document_id');$t->string('status');$t->timestamps();});
$db=Illuminate\Support\Facades\DB::connection('tenant'); $db->table('cash_document_credits')->insert(['document_id'=>123,'status'=>'PENDING']);
$document=new class extends App\Models\Tenant\Document {
    public string $syntheticBalance='0';
    public function exactBalance(bool $current=false): Brick\Math\BigRational {return App\Support\Exact\Decimal::of($this->syntheticBalance);}
};
$document->id=123;
$method=new ReflectionMethod(App\Services\Fiscal\ReceivedRetentionService::class,'syncCreditBalance');$method->setAccessible(true);
foreach (['0','0.001','0.005','0.005000000001'] as $balance) {
    $document->syntheticBalance=$balance;
    $method->invoke(null,$document);
    echo 'Saldo exacto=', $balance, '; estado=', $db->table('cash_document_credits')->where('document_id',123)->value('status'), PHP_EOL;
}
