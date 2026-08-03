<?php

namespace Modules\Marketplace\Services;

use App\Models\System\Configuration;
use Modules\Marketplace\Models\Setting;

/**
 * Acceso a marketplace_settings, cacheado en Redis bajo la versión global.
 *
 * Se lee la tabla entera de una vez: es una decena de filas y evita N
 * consultas cuando una vista pide varias claves.
 */
class Settings
{
    private static ?array $memo = null;

    public static function all(): array
    {
        if (self::$memo !== null) {
            return self::$memo;
        }

        return self::$memo = MarketplaceCache::rememberForever('settings', function () {
            return Setting::all()
                ->mapWithKeys(fn (Setting $s) => [$s->key => $s->typedValue()])
                ->all();
        });
    }

    public static function get(string $key, $default = null)
    {
        return self::all()[$key] ?? $default;
    }

    public static function set(string $key, $value): void
    {
        $setting = Setting::where('key', $key)->first();

        if (! $setting) {
            return;
        }

        $setting->value = Setting::serialize($value, $setting->type);
        $setting->save();

        self::forget();
        MarketplaceCache::bump();
    }

    /**
     * Olvida solo el memo en memoria del request actual. El cache de Redis se
     * invalida subiendo la versión global, no borrando claves.
     */
    public static function forget(): void
    {
        self::$memo = null;
    }

    // ---------------------------------------------------------------------
    // Helpers de dominio
    // ---------------------------------------------------------------------

    /**
     * Interruptor global del marketplace. Default false: nace apagado y el
     * admin lo enciende cuando ya tiene tiendas aprobadas.
     */
    public static function isEnabled(): bool
    {
        return (bool) self::get('is_enabled', false);
    }

    /**
     * URL de términos y condiciones, o null si no están activos.
     *
     * Replica la lógica de App\Http\Controllers\System\TermsController: la ruta
     * existe siempre, pero solo muestra algo si terms_mode está configurado.
     * Si devuelve null, el enlace no debe mostrarse en ninguna parte.
     */
    public static function termsUrl(): ?string
    {
        $configuration = Configuration::first();

        if (! $configuration) {
            return null;
        }

        $isActive = ($configuration->terms_mode === 'url' && ! empty($configuration->terms_url))
            || ($configuration->terms_mode === 'content' && ! empty($configuration->terms_content));

        return $isActive ? url('terminos-y-condiciones') : null;
    }
}
