<?php

namespace Modules\Marketplace\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Da identidad al visitante anónimo del marketplace.
 *
 * Emite una cookie con un uuid aleatorio la primera vez que alguien entra. Esa
 * es la identidad con la que se registran las recomendaciones.
 *
 * Por qué no la IP: en el wifi de un edificio —justo el caso de uso de este
 * marketplace— todos los vecinos comparten IP pública. Con ella, el primero en
 * recomendar consumiría el pulgar de todos los demás y la pantalla no tendría
 * forma de decírselo. La cookie distingue navegadores, así que el pulgar puede
 * mostrar siempre la verdad.
 *
 * Limitación asumida: quien borre sus cookies vuelve a empezar. Es aceptable —
 * el techo por navegador sigue siendo un voto por producto, y el throttle por
 * IP de la ruta corta cualquier intento de automatizarlo.
 *
 * No lleva datos personales ni sirve para seguir a nadie fuera de esta
 * pantalla: es una cookie técnica, del mismo orden que la de sesión.
 */
class EnsureMarketplaceVisitor
{
    public const COOKIE = 'mkt_visitor';

    /** Clave bajo la que la encuentran los controladores. */
    public const ATTRIBUTE = 'marketplace_visitor';

    public function handle(Request $request, Closure $next)
    {
        $id = $request->cookie(self::COOKIE);

        if (! is_string($id) || ! Str::isUuid($id)) {
            $id = (string) Str::uuid();

            // Encolada, no puesta a mano: así viaja en el mismo response y pasa
            // por EncryptCookies, que la firma. Un visitante no puede
            // fabricarse identidades nuevas editándola.
            Cookie::queue(cookie()->forever(self::COOKIE, $id));
        }

        // Se expone en la request para que el mismo ciclo que la crea ya pueda
        // usarla: si no, el primer clic de un visitante nuevo se perdería.
        $request->attributes->set(self::ATTRIBUTE, $id);

        return $next($request);
    }

    /** Identidad del visitante actual. Null fuera del grupo de rutas públicas. */
    public static function id(Request $request): ?string
    {
        $id = $request->attributes->get(self::ATTRIBUTE);

        return is_string($id) ? $id : null;
    }
}
