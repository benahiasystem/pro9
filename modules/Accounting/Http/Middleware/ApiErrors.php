<?php
namespace Modules\Accounting\Http\Middleware;
use Closure;
use Throwable;
use Illuminate\Http\{Request,JsonResponse};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Modules\Accounting\Domain\ApiProblem;
use Illuminate\Auth\{AuthenticationException,Access\AuthorizationException};
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
final class ApiErrors {
 public function handle(Request $r,Closure $next):mixed {
  $id=(string)Str::uuid();$r->attributes->set('accounting_request_id',$id);
  try{$response=$next($r);}
  catch(Throwable $e){$this->report($e);$response=$this->render($r,$e);}
  $response->headers->set('Cache-Control','no-store, private');$response->headers->set('X-Request-Id',$id);$response->headers->set('X-Content-Type-Options','nosniff');
  if(app()->environment('testing')&&config('accounting.isolated_tests'))$response->headers->set('X-Accounting-Isolated-Test','1');
  return $response;
 }
 public function render(Request $r,Throwable $e):JsonResponse {
  $id=$r->attributes->get('accounting_request_id')??(string)Str::uuid();
  $status=500;$body=['code'=>'INTERNAL_ERROR','message'=>'No se pudo completar la operación.'];
  if($e instanceof ApiProblem){$status=$e->status;$body=['code'=>$e->errorCode,'message'=>$e->getMessage(),'details'=>$e->details];}
  elseif($e instanceof ValidationException){$status=422;$body=['code'=>'VALIDATION_ERROR','message'=>'Revisa los campos señalados.','errors'=>$e->errors()];}
  elseif($e instanceof AuthenticationException){$status=401;$body=['code'=>'UNAUTHENTICATED','message'=>'Inicia sesión con tu cuenta de PRO9.'];}
  elseif($e instanceof AuthorizationException){$status=403;$body=['code'=>'FORBIDDEN_ACTION','message'=>'Tu cuenta no tiene permiso para esta operación.'];}
  elseif($e instanceof ModelNotFoundException){$status=404;$body=['code'=>'NOT_FOUND','message'=>'El recurso no está disponible en esta empresa.'];}
  elseif($e instanceof QueryException){
   $state=(string)($e->errorInfo[0]??$e->getCode());
   if(in_array($state,['23000','23505','23503','45000'],true)){$status=409;$body=['code'=>'DATA_CONFLICT','message'=>'El registro cambió o tiene referencias que impiden esta operación.'];}
  }
  elseif($e instanceof HttpExceptionInterface){$status=$e->getStatusCode();$body=['code'=>'HTTP_'.$status,'message'=>match($status){403=>'No tienes permiso para esta operación.',404=>'La dirección solicitada no está disponible.',405=>'Método no permitido.',429=>'Has realizado muchas solicitudes. Espera un momento.',default=>'No se pudo completar la solicitud.'}];}
  return response()->json([...$body,'request_id'=>$id],$status,['Cache-Control'=>'no-store, private','X-Request-Id'=>$id,'X-Content-Type-Options'=>'nosniff']);
 }
 public function report(Throwable $e):void {
  if($e instanceof ApiProblem||$e instanceof ValidationException||$e instanceof AuthenticationException||$e instanceof AuthorizationException||$e instanceof ModelNotFoundException||$e instanceof HttpExceptionInterface)return;
  // Do not log bindings, payloads, passwords or raw SQL from accounting requests.
  \Log::error('Accounting request failed',['request_id'=>request()->attributes->get('accounting_request_id'),'exception_type'=>get_class($e),'sqlstate'=>$e instanceof QueryException?($e->errorInfo[0]??null):null]);
 }
}
