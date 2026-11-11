<?php
namespace Modules\Accounting\Http\Controllers;
use Illuminate\Http\Request;
use Modules\Accounting\Domain\{ApiProblem,CompanyContext,Permissions};
use Modules\Accounting\Models\{Device,Account,ThirdParty,Period,JournalEntry};
use Modules\Accounting\Services\{AuditService,EntryService,OperationService,ReportService};
use Modules\Accounting\Support\TenantDB as DB;
final class OfflineController extends \App\Http\Controllers\Controller {
 public function prepare(Request $r):array {
  $v=$r->validate(['device_id'=>'required|uuid','name'=>'required|string|max:100','period'=>'required|date_format:Y-m']);
  return DB::transaction(function()use($r,$v){
   $device=Device::where('user_id',$r->user()->id)->where('device_id',$v['device_id'])->lockForUpdate()->first();
   if($device?->revoked_at)throw new ApiProblem('DEVICE_REVOKED','Este equipo fue revocado. Solicita su habilitación al administrador.',403);
   $hours=min(72,max(1,(int)app(CompanyContext::class)->company()->offline_hours));
   $until=now()->addHours($hours);
   if(!$device)$device=new Device(['user_id'=>$r->user()->id,'device_id'=>$v['device_id']]);
   $device->fill(['name'=>$v['name'],'authorized_until'=>$until,'last_seen_at'=>now()])->save();
   $entries=JournalEntry::whereBetween('accounting_date',[$v['period'].'-01',\Carbon\Carbon::parse($v['period'].'-01')->endOfMonth()->toDateString()])->orderBy('accounting_date')->limit(2001)->get();
   if($entries->count()>2000)throw new ApiProblem('WORKSPACE_TOO_LARGE','Este período supera los 2.000 comprobantes de la copia local. Reduce el alcance antes de prepararla.',413);
   $context=app(CompanyContext::class);$membership=app(Permissions::class)->membership($r->user(),$context->id());
   $package=['schema'=>1,'device_id'=>$v['device_id'],'issued_at'=>now()->toIso8601String(),'expires_at'=>$until->toIso8601String(),'period'=>$v['period'],
    'company'=>[...$context->company()->toArray(),'role'=>$membership->role,'permissions'=>app(Permissions::class)->forRole($membership->role)],
    'user'=>$r->user()->only(['id','name','email']),
    'accounts'=>Account::orderBy('code')->get(),'parties'=>ThirdParty::orderBy('name')->get(),'periods'=>Period::orderBy('year')->orderBy('month')->get(),
    'entries'=>$entries->map(fn($entry)=>app(EntryService::class)->resource($entry))->all(),'demo'=>(bool)config('accounting.demo')];
   $filters=['from'=>$v['period'].'-01','to'=>\Carbon\Carbon::parse($v['period'].'-01')->endOfMonth()->toDateString()];
   foreach(['dashboard','trial-balance','balance-sheet','income-statement'] as $kind)$package['reports'][$kind]=app(ReportService::class)->build($kind,$filters);
   if(strlen(json_encode($package,JSON_THROW_ON_ERROR))>(int)config('accounting.workspace_max_bytes'))throw new ApiProblem('WORKSPACE_TOO_LARGE','La copia supera el tamaño permitido para este equipo.',413);
   app(AuditService::class)->record($r->user()->id,'offline.prepared','device',$device->id,['period'=>$v['period'],'entries'=>$entries->count(),'authorized_until'=>$until->toIso8601String()]);
   return ['data'=>$package];
  },5);
 }
 public function sync(Request $r):array {
  $v=$r->validate(['device_id'=>'required|uuid','entity_id'=>'nullable|uuid','base_version'=>'nullable|integer|min:1','local_id'=>'required|uuid']);
  $draft=\Modules\Accounting\Support\EntryInput::validate($r);
  return ['data'=>DB::transaction(function()use($r,$v,$draft){
   $device=Device::where('user_id',$r->user()->id)->where('device_id',$v['device_id'])->lockForUpdate()->first();
   if(!$device||$device->revoked_at||!$device->authorized_until||$device->authorized_until->isPast())throw new ApiProblem('OFFLINE_AUTHORIZATION_EXPIRED','Vuelve a autorizar este equipo en línea. Tus borradores locales se conservan.',403);
   app(Permissions::class)->require($r->user(),'draft');
   return app(OperationService::class)->execute($r->user()->id,'offline.sync',$r->header('Idempotency-Key'),[...$draft,'entity_id'=>$v['entity_id']??null,'base_version'=>$v['base_version']??null,'device_id'=>$v['device_id']],function()use($r,$v,$draft){
    $service=app(EntryService::class);
    if(!empty($v['entity_id'])){
     if(empty($v['base_version']))throw new ApiProblem('VERSION_REQUIRED','Falta la versión del borrador preparado.',428);
     $entry=$service->edit($v['entity_id'],$draft,$v['base_version'],$r->user()->id);
    }else $entry=$service->create($draft,$r->user()->id);
    app(AuditService::class)->record($r->user()->id,'offline.draft_synchronized','journal_entry',$entry->id,['device_id'=>$v['device_id'],'local_id'=>$v['local_id']]);
    return $service->resource($entry);
   });
  },5)];
 }
 public function devices(Request $r):array {return ['data'=>Device::where('user_id',$r->user()->id)->orderByDesc('last_seen_at')->get()];}
 public function revoke(Request $r,string $company,string $id):array {
  $device=Device::whereKey($id)->where('user_id',$r->user()->id)->firstOrFail();
  $device->revoked_at=now();$device->save();
  app(AuditService::class)->record($r->user()->id,'offline.revoked','device',$id,[]);
  return ['success'=>true];
 }
}
