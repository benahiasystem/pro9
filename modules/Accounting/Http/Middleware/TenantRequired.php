<?php
namespace Modules\Accounting\Http\Middleware;
use Closure;
use Hyn\Tenancy\Environment;
use Modules\Accounting\Domain\ApiProblem;
use Illuminate\Http\Request;
final class TenantRequired {
 public function handle(Request $r,Closure $next):mixed {
  $env=app(Environment::class);
  if(!$env->tenant()||!$env->hostname()||$env->hostname()->fqdn!==$r->getHost()||!config('database.connections.tenant'))
   throw new ApiProblem('TENANT_REQUIRED','La empresa de PRO9 no está disponible en este dominio.',404);
  return $next($r);
 }
}
