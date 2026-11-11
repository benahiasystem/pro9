<?php
namespace Modules\Accounting\Domain;
use App\Models\Tenant\User;
use Modules\Accounting\Support\TenantDB as DB;
final class Permissions {
    private const ROLES=[
        'viewer'=>['read','reports'],
        'auditor'=>['read','reports','audit'],
        'auxiliary'=>['read','draft','documents','attachments','import','local','reports'],
        'accountant'=>['read','draft','documents','attachments','import','local','reports','audit','review','approve','post','reverse','close','reopen','catalogs','rules','settlements'],
        'admin'=>['read','draft','documents','attachments','import','local','reports','audit','review','approve','post','reverse','close','reopen','catalogs','rules','settlements','users','company','integration','backup'],
    ];
    public static function all():array{return self::ROLES;}
    public function membership(User $user,string $companyId): object {
        $m=DB::table('accounting_memberships')->where('user_id',$user->id)->where('company_id',$companyId)->where('active',true)->first();
        if(!$m)throw new ApiProblem('FORBIDDEN_COMPANY','No tienes acceso a esta empresa.',403);
        return $m;
    }
    public function require(User $user,string $action): object {
        $m=$this->membership($user,app(CompanyContext::class)->id());
        if(!in_array($action,self::ROLES[$m->role]??[],true))throw new ApiProblem('FORBIDDEN_ACTION','Tu rol no permite realizar esta operación.',403);
        return $m;
    }
    public function forRole(string $role):array{return self::ROLES[$role]??[];}
}
