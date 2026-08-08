<?php

namespace Modules\Sync\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Sync\Models\OfflineMachine;

/**
 * Autenticación por token de máquina (header X-Machine-Token).
 * La autoridad del canal offline es la máquina enrolada, no un usuario:
 * los PIN solo desbloquean la máquina en local y nunca viajan al pro.
 */
class AuthenticateMachine
{
    public function handle(Request $request, Closure $next)
    {
        $machine = OfflineMachine::findByPlainToken($request->header('X-Machine-Token'));

        if (!$machine) {
            return response()->json([
                'success' => false,
                'message' => 'Máquina no autorizada o revocada',
            ], 401);
        }

        $machine->touchSeen();
        $request->attributes->set('offline_machine', $machine);

        return $next($request);
    }
}
