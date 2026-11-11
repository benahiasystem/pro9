<?php
namespace Modules\Accounting\Console;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Hyn\Tenancy\Models\Hostname;
use Hyn\Tenancy\Environment;
use App\Models\Tenant\User;
use Modules\Accounting\Support\TenantDB as DB;
use Modules\Accounting\Domain\CompanyContext;
use Modules\Accounting\Models\Company;
use Modules\Accounting\Services\CompanyService;
final class InstallAccounting extends Command {
 protected $signature='accounting:install {--hostname= : Dominio exacto del tenant} {--owner-user= : ID del administrador de PRO9} {--demo : Crear dos libros de datos ficticios}';
 protected $description='Instala las tablas del módulo contable dentro de un tenant de PRO9.';
 public function handle():int {
  if(!$this->option('hostname')||!ctype_digit((string)$this->option('owner-user'))){$this->error('Indica --hostname y --owner-user.');return 1;}
  if($this->option('demo')&&(!app()->environment(['local','testing'])||!config('accounting.demo'))){$this->error('Los datos ficticios requieren un entorno local o de pruebas con ACCOUNTING_DEMO_MODE.');return 1;}
  $host=Hostname::query()->with('website')->where('fqdn',$this->option('hostname'))->first();
  if(!$host?->website){$this->error('El tenant no existe.');return 1;}
  $env=app(Environment::class);$env->hostname($host);$env->tenant($host->website);
  $owner=User::without('establishment')->find((int)$this->option('owner-user'));
  if(!$owner||$owner->type!=='admin'||$owner->locked||!$owner->active){$this->error('El propietario debe ser un administrador activo de PRO9.');return 1;}
  $code=$this->call('migrate',['--database'=>'tenant','--path'=>__DIR__.'/../Database/Migrations','--realpath'=>true,'--force'=>true]);
  if($code!==0)return $code;
  DB::transaction(function()use($owner){
   $org=DB::table('accounting_organizations')->first();
   if(!$org){$id=(string)Str::uuid();DB::table('accounting_organizations')->insert(['id'=>$id,'name'=>'Organización contable de PRO9','created_at'=>now(),'updated_at'=>now()]);$org=DB::table('accounting_organizations')->where('id',$id)->first();}
   $source=DB::table('companies')->first();
   $books=$this->option('demo')?[['Empresa de muestra A, C.A.','J-00000000-0'],['Empresa de muestra B, C.A.','J-00000000-1']]:[[$source->name??'Empresa de PRO9',$source->number??'POR-DEFINIR']];
   foreach($books as [$name,$tax]){
    $company=Company::where('organization_id',$org->id)->where('tax_id',$tax)->first();
    if(!$company)$company=app(CompanyService::class)->create(['name'=>$name,'tax_id'=>$tax,'approval_mode'=>'separate','settings'=>['source'=>$this->option('demo')?'fictitious':'pro9','source_company_id'=>$this->option('demo')?null:($source->id??null)]],$org->id,$owner->id);
    else DB::table('accounting_memberships')->updateOrInsert(['company_id'=>$company->id,'user_id'=>$owner->id],['id'=>(string)Str::uuid(),'organization_id'=>$org->id,'role'=>'admin','active'=>true,'created_at'=>now(),'updated_at'=>now()]);
    app(CompanyContext::class)->run($company,fn()=>app(CompanyService::class)->defaultChart());
   }
  },5);
  $this->info('Módulo contable instalado en el tenant indicado. La PWA utiliza la API de PRO9.');return 0;
 }
}

