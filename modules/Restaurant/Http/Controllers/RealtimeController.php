<?php

namespace Modules\Restaurant\Http\Controllers;

use App\Models\Tenant\User;
use App\Services\CentrifugoTokenService;
use App\Tenancy\Contracts\CurrentHostname;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class RealtimeController extends Controller
{
    public function token(Request $request, CentrifugoTokenService $tokens)
    {
        $hostname = app(CurrentHostname::class);
        $user = $request->user('api');
        abort_unless($hostname && $hostname->website_id && $hostname->fqdn === $request->getHost(), 403);
        abort_unless($user instanceof User && !$user->locked, 403);
        abort_unless($user->type === 'admin' || $user->restaurant_role()->exists(), 403);

        return response()->json($tokens->issue($user, $hostname->fqdn, (int) $hostname->website_id))
            ->header('Cache-Control', 'private, no-store')
            ->header('Pragma', 'no-cache')
            ->header('X-Content-Type-Options', 'nosniff');
    }
}
