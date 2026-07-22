<?php

namespace Modules\Marketplace\Services;

use Illuminate\Cache\Repository;

/**
 * Invalidación por versión global.
 *
 * Las escrituras son raras y las lecturas frecuentes, así que no hacen falta
 * tags: un único contador `mkt:v` manda sobre todo el cache del módulo. Un
 * INCR invalida el módulo entero al instante, sin FLUSHDB y sin tocar el cache
 * del resto de Pro8. Las claves viejas expiran solas.
 */
class MarketplaceCache
{
    /** El prefijo `mkt` lo pone el repositorio, no la clave. */
    private const VERSION_KEY = 'v';

    /**
     * Repositorio propio del módulo (ver MarketplaceServiceProvider::registerCache).
     *
     * Nunca Cache::store('redis'): ese store lleva un prefijo que la app
     * reescribe por tenant en cada request, y el marketplace es data de la
     * conexión `system`, común a toda la instalación.
     */
    public static function store(): Repository
    {
        return app('marketplace.cache');
    }

    public static function version(): int
    {
        return (int) self::store()->rememberForever(self::VERSION_KEY, fn () => 1);
    }

    public static function key(string $suffix): string
    {
        return 'v' . self::version() . ':' . $suffix;
    }

    /**
     * Sube la versión: invalida todo el cache del módulo de golpe.
     *
     * Se dispara en: sync completado · aprobar/rechazar/deshabilitar/habilitar
     * tienda · bloquear/desbloquear ítem · renombrar/ocultar categoría ·
     * guardar ajustes.
     */
    public static function bump(): int
    {
        $store = self::store();

        // increment() sobre una clave inexistente devuelve false en algunos
        // drivers: nos aseguramos de que exista antes de subirla.
        self::version();

        return (int) $store->increment(self::VERSION_KEY);
    }

    public static function remember(string $suffix, int $seconds, callable $callback)
    {
        return self::store()->remember(self::key($suffix), $seconds, $callback);
    }

    public static function rememberForever(string $suffix, callable $callback)
    {
        return self::store()->rememberForever(self::key($suffix), $callback);
    }
}
