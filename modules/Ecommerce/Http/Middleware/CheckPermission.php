<?php

namespace Modules\Ecommerce\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Tenant\User;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (! self::tenantHasEcommerce()) {
            abort(404);
        }

        return $next($request);
    }

    /**
     * Si el tenant tiene habilitado el módulo ecommerce. Se consulta el primer usuario
     * (administrador) para conocer los permisos, así funciona también sin sesión.
     */
    public static function tenantHasEcommerce(): bool
    {
        $user = User::first();
        if (! $user) {
            return false;
        }

        return $user->getModules()->contains(function ($module) {
            return $module->value === 'ecommerce';
        });
    }
}
