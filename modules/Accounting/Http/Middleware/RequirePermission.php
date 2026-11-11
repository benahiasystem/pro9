<?php
namespace Modules\Accounting\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Modules\Accounting\Domain\Permissions;
use Modules\Accounting\Services\SecurityService;
final class RequirePermission {
 public function handle(Request $r,Closure $next,string $permission):mixed {
  app(Permissions::class)->require($r->user(),$permission);
  if(in_array($permission,['approve','post','reverse','close','reopen','users','rules','integration','backup','company'],true))app(SecurityService::class)->requireVerified($r);
  return $next($r);
 }
}
