<?php

namespace App\Http\Middleware;

use App\Support\Exact\JsonDecimals;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
final class PreserveJsonDecimals
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isJson() && $request->getContent() !== '') {
            try {
                $request->json()->replace(JsonDecimals::decode($request->getContent()));
            } catch (\JsonException $exception) {
                throw new BadRequestHttpException('El cuerpo JSON no es válido.', $exception);
            }
        }
        return $next($request);
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
