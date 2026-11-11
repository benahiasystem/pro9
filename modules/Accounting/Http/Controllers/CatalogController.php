<?php
namespace Modules\Accounting\Http\Controllers;
use Modules\Accounting\Domain\{ApiProblem,CompanyContext,Exact};
use Modules\Accounting\Models\{Account,ThirdParty,CostCenter,Establishment,ExchangeRate,RuleSet,Period,JournalLine};
use Modules\Accounting\Services\{AuditService,CompanyService};
use Illuminate\Http\Request;
use Modules\Accounting\Support\TenantDB as DB;
final class CatalogController extends \App\Http\Controllers\Controller {
 private const MODELS=['accounts'=>Account::class,'third-parties'=>ThirdParty::class,'cost-centers'=>CostCenter::class,'establishments'=>Establishment::class,'exchange-rates'=>ExchangeRate::class,'rules'=>RuleSet::class,'periods'=>Period::class];
 private function model(string $kind):string {return self::MODELS[$kind]??throw new ApiProblem('NOT_FOUND','Catálogo no disponible.',404);}
 public function index(Request $r,string $company,string $kind):array {
  $model=$this->model($kind);$q=$model::query();
  if($kind==='accounts')$q->orderBy('code');
  elseif($kind==='periods')$q->orderByDesc('year')->orderBy('month');
  elseif($kind==='exchange-rates')$q->orderByDesc('effective_date');
  else $q->orderBy('created_at');
  return ['data'=>$q->limit(5000)->get()];
 }
 private function fields(Request $r,string $kind):array {
  return $r->validate(match($kind){
   'accounts'=>['code'=>'required|string|max:40|regex:/^[0-9A-Z.\\-]+$/','name'=>'required|string|max:255','parent_id'=>'nullable|uuid','kind'=>'required|in:asset,liability,equity,income,cost,expense','nature'=>'required|in:debit,credit','posting'=>'required|boolean','active'=>'sometimes|boolean','requires_party'=>'sometimes|boolean','auxiliary'=>'nullable|in:cash,bank,receivable,payable,vat_credit,vat_debit,capital,retained_earnings,current_result,revenue,exchange_gain,exchange_loss,expense,inventory,cost_of_sales,other_tax,rounding,vat_withheld_receivable,vat_withheld_payable,islr_withheld_receivable,islr_withheld_payable','report_group'=>'nullable|string|max:80'],
   'third-parties'=>['tax_id'=>'required|string|max:30','name'=>'required|string|max:255','kind'=>'required|in:customer,supplier,both,other','email'=>'nullable|email|max:255','phone'=>'nullable|string|max:40','address'=>'nullable|string|max:2000','active'=>'sometimes|boolean'],
   'cost-centers','establishments'=>['code'=>'required|string|max:30','name'=>'required|string|max:255','active'=>'sometimes|boolean','address'=>'nullable|string|max:255'],
   'exchange-rates'=>['currency'=>'required|in:USD,EUR','effective_date'=>'required|date_format:Y-m-d','value'=>'required|string|max:40','source'=>'required|string|max:255','reason'=>'required|string|max:2000'],
   default=>throw new ApiProblem('READ_ONLY_CATALOG','Este catálogo tiene un proceso de gestión específico.'),
  });
 }
 public function store(Request $r,string $company,string $kind):array {
  $v=$this->fields($r,$kind);$model=$this->model($kind);
  return DB::transaction(function()use($r,$v,$model,$kind){
   $this->validateBusiness($kind,$v,null);
   if($kind==='exchange-rates'){$v['value']=(string)Exact::of($v['value'],12);$v['created_by']=$r->user()->id;if(!Exact::of($v['value'],12)->isPositive())throw new ApiProblem('INVALID_RATE','La tasa debe ser mayor que cero.');}
   $m=$model::create($v);app(AuditService::class)->record($r->user()->id,$kind.'.created',$kind,$m->id,['data'=>$v]);return ['data'=>$m];
  });
 }
 public function update(Request $r,string $company,string $kind,string $id):array {
  $v=$this->fields($r,$kind);$model=$this->model($kind);
  if($kind==='exchange-rates')throw new ApiProblem('IMMUTABLE_RATE','Registra una nueva versión de la tasa para conservar el histórico.');
  return DB::transaction(function()use($r,$kind,$id,$v,$model){
   $m=$model::whereKey($id)->lockForUpdate()->firstOrFail();$this->validateBusiness($kind,$v,$m);
   $before=$m->toArray();$m->fill($v);if(isset($m->version))$m->version++;$m->save();
   app(AuditService::class)->record($r->user()->id,$kind.'.updated',$kind,$id,['before'=>$before,'after'=>$v]);return ['data'=>$m];
  });
 }
 private function validateBusiness(string $kind,array $v,mixed $old):void {
  $model=$this->model($kind);$key=$kind==='third-parties'?'tax_id':'code';
  if(isset($v[$key]) && $model::where($key,$v[$key])->when($old,fn($q)=>$q->where('id','!=',$old->id))->exists())throw new ApiProblem('DUPLICATE_CODE','El código o identificación ya existe en esta empresa.');
  if($kind==='accounts'){
   if(!empty($v['parent_id'])){
    $parent=Account::whereKey($v['parent_id'])->lockForUpdate()->first();
    if(!$parent||$parent->posting)throw new ApiProblem('INVALID_PARENT','Selecciona una cuenta padre de agrupación de esta empresa.');
    if($parent->kind!==$v['kind'])throw new ApiProblem('INVALID_ACCOUNT_KIND','La cuenta debe conservar la clasificación de su padre.');
    if($old){$visited=[$old->id];$current=$parent;while($current){if(in_array($current->id,$visited,true))throw new ApiProblem('ACCOUNT_CYCLE','La jerarquía no puede contener ciclos.');$visited[]=$current->id;$current=$current->parent_id?Account::find($current->parent_id):null;}}
   }
   if($old && JournalLine::where('account_id',$old->id)->exists())foreach(['code','kind','nature','parent_id','posting'] as $field)if(($v[$field]??null)!==$old->$field)throw new ApiProblem('ACCOUNT_USED','Una cuenta con movimientos conserva su código, naturaleza y clasificación.');
   if($old && $v['posting'] && Account::where('parent_id',$old->id)->exists())throw new ApiProblem('ACCOUNT_HAS_CHILDREN','Una cuenta con subcuentas debe ser de agrupación.');
  }
 }
}
