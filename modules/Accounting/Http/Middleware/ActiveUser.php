<?php
namespace Modules\Accounting\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Modules\Accounting\Domain\ApiProblem;
final class ActiveUser {
 public function handle(Request $r,Closure $next):mixed {
  $u=$r->user();$active=$u?->getAttribute('active');
  if(!$u||$u->locked||$active===false||$active===0||$active==='0')throw new ApiProblem('INACTIVE_USER','El usuario no tiene acceso activo en PRO9.',403);
  return $next($r);
 }
}
