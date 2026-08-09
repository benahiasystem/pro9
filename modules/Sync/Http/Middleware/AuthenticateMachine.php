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
        $token = (string) $request->header('X-Machine-Token');
        $known = $token !== ''
            ? OfflineMachine::where('token_hash', OfflineMachine::hashToken($token))->first()
            : null;

        // Código distinguible: la app bloquea la emisión y ofrece el
        // re-enrolamiento cuando la revocación es explícita.
        if ($known && $known->status !== 'active') {
            return response()->json([
                'success' => false,
                'code' => 'machine_revoked',
                'message' => 'Esta máquina fue revocada por el administrador',
            ], 401);
        }

        if (!$known) {
            return response()->json([
                'success' => false,
                'code' => 'machine_unknown',
                'message' => 'Máquina no autorizada',
            ], 401);
        }

        $machine = $known;

        $machine->touchSeen();
        $request->attributes->set('offline_machine', $machine);

        return $next($request);
    }
}
