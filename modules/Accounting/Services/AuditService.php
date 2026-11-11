<?php
namespace Modules\Accounting\Services;
use Modules\Accounting\Domain\CompanyContext;
use Modules\Accounting\Support\TenantDB as DB;
use Illuminate\Support\Str;
final class AuditService {
    public function record(?int $user,string $action,string $type,?string $id,array $payload=[]): array {
        return DB::transaction(function()use($user,$action,$type,$id,$payload){
            $c=app(CompanyContext::class);$now=now()->toDateTimeString();
            DB::table('accounting_audit_heads')->insertOrIgnore(['id'=>(string)Str::uuid(),'organization_id'=>$c->organizationId(),'company_id'=>$c->id(),'last_sequence'=>0,'last_hash'=>'','created_at'=>$now,'updated_at'=>$now]);
            $head=DB::table('accounting_audit_heads')->where('company_id',$c->id())->lockForUpdate()->first();
            $sequence=$head->last_sequence+1;
            $event=['id'=>(string)Str::uuid(),'organization_id'=>$c->organizationId(),'company_id'=>$c->id(),'sequence'=>$sequence,'user_id'=>$user,'action'=>$action,'resource_type'=>$type,'resource_id'=>$id,'payload'=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'previous_hash'=>$head->last_hash,'created_at'=>$now,'updated_at'=>$now];
            $event['hash']=hash('sha256',json_encode($event,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
            DB::table('accounting_audit_events')->insert($event);
            DB::table('accounting_audit_heads')->where('id',$head->id)->update(['last_hash'=>$event['hash'],'last_sequence'=>$sequence,'updated_at'=>$now]);
            return $event;
        },5);
    }
}
