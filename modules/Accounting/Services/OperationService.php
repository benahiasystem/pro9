<?php
namespace Modules\Accounting\Services;
use Modules\Accounting\Domain\{ApiProblem,CompanyContext};
use Modules\Accounting\Models\OperationResult;
use Modules\Accounting\Support\TenantDB as DB;
use Illuminate\Database\QueryException;
final class OperationService {
    public function execute(int $user,string $action,?string $key,array $payload,callable $fn):array {
        if(!$key || !preg_match('/^[a-zA-Z0-9_.:-]{8,100}$/D',$key))throw new ApiProblem('IDEMPOTENCY_KEY_REQUIRED','Se requiere una identidad válida de operación.');
        $hash=hash('sha256',json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
        $lookup=fn()=>OperationResult::where('user_id',$user)->where('action',$action)->where('operation_key',$key);
        $replay=function($old)use($hash){if(!$old || $old->content_hash!==$hash)throw new ApiProblem('IDEMPOTENCY_CONFLICT','La identidad de operación ya se utilizó con otros datos.',409);if(!$old->result)throw new ApiProblem('OPERATION_PENDING','La operación sigue en curso; consulta de nuevo con la misma identidad.',409);return $old->result;};
        try {
            return DB::transaction(function()use($user,$action,$key,$hash,$fn,$lookup,$replay){
                if($old=$lookup()->lockForUpdate()->first())return $replay($old);
                $op=OperationResult::create(['user_id'=>$user,'action'=>$action,'operation_key'=>$key,'content_hash'=>$hash]);
                $result=$fn();$op->result=$result;$op->save();return $result;
            },5);
        }catch(QueryException $e){
            if($old=$lookup()->first())return $replay($old);throw $e;
        }
    }
}
