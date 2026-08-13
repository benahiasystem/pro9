<?php

namespace Modules\Payment\Models;

use App\Models\Tenant\ModelTenant;

class PaymentConfiguration extends ModelTenant
{

    protected $fillable = [
        'enabled_yape',
        'qrcode_yape',
        'name_yape',
        'telephone_yape',
        'default_payment_for_payment_links',
        'enabled_mp',
        'access_token_mp',
        'public_key_mp',
        'enabled_culqi',
        'publickey_culqi',
        'privatekey_culqi',
        'idrsa_culqi',
        'rsa_culqi',
        'enabled_izipay',
        'username_izipay',
        'password_izipay',
        'publickey_izipay',
        'sha256key_izipay',
        'enabled_mp',
        'access_token_mp',
        'public_key_mp'
    ];

    protected $hidden = [
        'access_token_mp',
        'privatekey_culqi',
        'sha256key_izipay',
    ];


    protected $casts = [
        'enabled_yape' => 'bool',
        'enabled_mp' => 'bool',
        'enabled_culqi' => 'bool',
        'enabled_izipay' => 'bool',
    ];


    public function getImageUrlYapeAttribute()
    {
        return $this->qrcode_yape ? asset('storage'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'payment_configurations'.DIRECTORY_SEPARATOR.$this->qrcode_yape) : null;
    }

    public function getRowResource()
    {
        return [
            'id' => $this->id,
            'enabled_yape' => $this->enabled_yape,
            'qrcode_yape' => $this->qrcode_yape,
            'name_yape' => $this->name_yape,
            'telephone_yape' => $this->telephone_yape,
            'image_url_yape' => $this->image_url_yape,
            'enabled_mp' => $this->enabled_mp,
            'public_key_mp' => $this->public_key_mp,
            'has_access_token_mp' => !empty($this->access_token_mp),
            'access_token_mp_suffix' => $this->access_token_mp
                ? substr($this->access_token_mp, -8)
                : null,
            'access_token_mp_length' => $this->access_token_mp
                ? strlen($this->access_token_mp)
                : null,
            'enabled_culqi' => $this->enabled_culqi,
            'publickey_culqi' => $this->publickey_culqi,
            'privatekey_culqi' => $this->privatekey_culqi,
            'idrsa_culqi' => $this->idrsa_culqi,
            'rsa_culqi' => $this->rsa_culqi,
            'enabled_izipay' => $this->enabled_izipay,
            'username_izipay' => $this->username_izipay,
            'password_izipay' => $this->password_izipay,
            'publickey_izipay' => $this->publickey_izipay,
            'sha256key_izipay' => $this->sha256key_izipay,
            'default_payment_for_payment_links' => $this->default_payment_for_payment_links,
        ];
    }


    public static function getPublicRowResource()
    {

        $record = PaymentConfiguration::first();

        return [
            'name_yape' => $record->name_yape,
            'telephone_yape' => $record->telephone_yape,
            'image_url_yape' => $record->image_url_yape,
        ];

    }

    public static function getPaymentPermissions()
    {

        $record = PaymentConfiguration::firstOrFail();

        return [
            'enabled_yape' => $record->enabled_yape,
            'enabled_mp' => $record->enabled_mp,
            'qrcode_yape' => $record->enabled_yape ? $record->getImageUrlYapeAttribute() : '',
            'name_yape' => $record->enabled_yape ? $record->name_yape : '',
            'telephone_yape' => $record->enabled_yape ? $record->telephone_yape : '',
        ];

    }


    /**
     * Obtener llave publica de mercado pago
     *
     * @return string
     */
    public static function getPublicKeyMp()
    {
        return PaymentConfiguration::select('public_key_mp')->firstOrFail()->public_key_mp;
    }

    /**
     * Obtener llave publica de mercado pago
     *
     * @return string
     */
    public static function getAccessTokenMp()
    {
        return PaymentConfiguration::select('access_token_mp')->firstOrFail()->access_token_mp;
    }

    /**
     * Credenciales de izipay, null si está deshabilitado o no hay configuración
     */
    public function scopeAccessIzipay($query)
    {
        return $query->where('enabled_izipay', true)
            ->select('username_izipay', 'password_izipay', 'publickey_izipay', 'sha256key_izipay');
    }

    /**
     * Normaliza credenciales Izipay (trim) para evitar INT_904 por espacios.
     */
    public static function normalizeIzipayCredential(?string $value): string
    {
        return trim((string) $value);
    }

    /**
     * Llave pública Izipay cruda desde payment_configurations.
     */
    public static function getPublicKeyIzipay(): ?string
    {
        $record = static::query()->select('publickey_izipay')->first();

        if (! $record) {
            return null;
        }

        $key = static::normalizeIzipayCredential($record->publickey_izipay);

        return $key !== '' ? $key : null;
    }

    /**
     * Llave pública en formato Krypton: "{username}:{publickey}".
     * Requerido por kr-public-key; sin el prefijo Krypton responde INT_904.
     */
    public static function extractRawPublicKey(?string $publicKey): string
    {
        $publicKey = static::normalizeIzipayCredential($publicKey);

        if ($publicKey === '') {
            return '';
        }

        if (! str_contains($publicKey, ':')) {
            return $publicKey;
        }

        $parts = array_values(array_filter(
            array_map([static::class, 'normalizeIzipayCredential'], explode(':', $publicKey)),
            fn ($part) => $part !== ''
        ));

        foreach (array_reverse($parts) as $part) {
            if (preg_match('/^(test|prod)publickey_/i', $part)) {
                return $part;
            }
        }

        return static::normalizeIzipayCredential(end($parts) ?: '');
    }

    public static function buildKryptonPublicKey(?string $username, ?string $publicKey): ?string
    {
        $username = static::normalizeIzipayCredential($username);
        $rawKey = static::extractRawPublicKey($publicKey);

        if ($rawKey === '') {
            return null;
        }

        if ($username === '') {
            $normalized = static::normalizeIzipayCredential($publicKey);

            if (str_contains($normalized, ':')) {
                $merchantId = static::normalizeIzipayCredential(strtok($normalized, ':'));

                if ($merchantId !== '') {
                    return $merchantId . ':' . $rawKey;
                }
            }

            return null;
        }

        return $username . ':' . $rawKey;
    }

    /**
     * Normaliza la llave pública antes de persistirla (solo token, sin prefijo duplicado).
     */
    public static function sanitizePublicKeyForStorage(?string $publicKey): ?string
    {
        $rawKey = static::extractRawPublicKey($publicKey);

        return $rawKey !== '' ? $rawKey : null;
    }

    /**
     * Llave pública lista para el SDK Krypton del checkout ecommerce.
     */
    public static function getKryptonPublicKeyIzipay(): ?string
    {
        $record = static::query()->select('username_izipay', 'publickey_izipay')->first();

        if (! $record) {
            return null;
        }

        return static::buildKryptonPublicKey($record->username_izipay, $record->publickey_izipay);
    }

    /**
     * Credenciales Izipay del tenant para crear formToken (solo si está habilitada).
     */
    public static function accessIzipayCredentials(): ?array
    {
        $record = static::query()
            ->where('enabled_izipay', true)
            ->select('username_izipay', 'password_izipay', 'publickey_izipay', 'sha256key_izipay')
            ->first();

        if (! $record) {
            return null;
        }

        $credentials = [
            'username_izipay' => static::normalizeIzipayCredential($record->username_izipay),
            'password_izipay' => static::normalizeIzipayCredential($record->password_izipay),
            'publickey_izipay' => static::normalizeIzipayCredential($record->publickey_izipay),
            'sha256key_izipay' => static::normalizeIzipayCredential($record->sha256key_izipay),
        ];

        foreach ($credentials as $value) {
            if ($value === '') {
                return null;
            }
        }

        return $credentials;
    }

    /**
     * Devuelve la pasarela de pago habilitada ('izipay'|'culqi'|'mercadopago') o null si no hay ninguna.
     */
    public static function enabledCheckout()
    {
        $record = static::query()->select('enabled_izipay', 'enabled_culqi', 'enabled_mp')->first();

        if (! $record) {
            return null;
        }

        if ($record->enabled_izipay) {
            return 'izipay';
        }

        if ($record->enabled_culqi) {
            return 'culqi';
        }

        if ($record->enabled_mp) {
            return 'mercadopago';
        }

        return null;
    }

    public function scopeGetPublicKey($query)
    {
        $record = $query->where('enabled_mp', true)
            ->orWhere('enabled_culqi', true)
            ->orWhere('enabled_izipay', true)
            ->first();

        if (!$record) return null;

        if ($record->enabled_mp) return $record->public_key_mp;
        if ($record->enabled_culqi) return $record->publickey_culqi;
        if ($record->enabled_izipay) return $record->publickey_izipay;

    }

    /**
     * Indica si un valor de credencial está presente y no vacío.
     */
    private static function hasCredentialValue(?string $value): bool
    {
        return filled(trim((string) $value));
    }

    /**
     * Yape habilitado globalmente con todos los campos requeridos.
     */
    public static function isYapeConfigured(?self $record = null): bool
    {
        $record = $record ?? static::first();

        if (! $record || ! $record->enabled_yape) {
            return false;
        }

        return static::hasCredentialValue($record->telephone_yape)
            && static::hasCredentialValue($record->name_yape)
            && static::hasCredentialValue($record->qrcode_yape);
    }

    /**
     * Mercado Pago habilitado globalmente con tokens público y privado.
     */
    public static function isMercadoPagoConfigured(?self $record = null): bool
    {
        $record = $record ?? static::first();

        if (! $record || ! $record->enabled_mp) {
            return false;
        }

        return static::hasCredentialValue($record->public_key_mp)
            && static::hasCredentialValue($record->access_token_mp);
    }

    /**
     * Culqi habilitado globalmente con todas las claves requeridas.
     */
    public static function isCulqiConfigured(?self $record = null): bool
    {
        $record = $record ?? static::first();

        if (! $record || ! $record->enabled_culqi) {
            return false;
        }

        return static::hasCredentialValue($record->publickey_culqi)
            && static::hasCredentialValue($record->privatekey_culqi)
            && static::hasCredentialValue($record->idrsa_culqi)
            && static::hasCredentialValue($record->rsa_culqi);
    }

    /**
     * Izipay habilitado globalmente con todas las credenciales requeridas.
     */
    public static function isIzipayConfigured(?self $record = null): bool
    {
        $record = $record ?? static::first();

        if (! $record || ! $record->enabled_izipay) {
            return false;
        }

        return static::hasCredentialValue($record->username_izipay)
            && static::hasCredentialValue($record->password_izipay)
            && static::hasCredentialValue($record->publickey_izipay)
            && static::hasCredentialValue($record->sha256key_izipay);
    }

    /**
     * Disponibilidad de pasarelas para la configuración de Tienda Virtual.
     */
    public static function getEcommerceGatewayAvailability(): array
    {
        $record = static::first();

        return [
            'yape' => static::isYapeConfigured($record),
            'mercadopago' => static::isMercadoPagoConfigured($record),
            'culqi' => static::isCulqiConfigured($record),
            'izipay' => static::isIzipayConfigured($record),
        ];
    }

    public const IZIPAY_CULQI_EXCLUSIVITY_MESSAGE = 'No puedes activar Izipay y Culqi simultáneamente';

    /**
     * Valida que Izipay y Culqi no estén activos al mismo tiempo.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public static function validateIzipayCulqiExclusivity(bool $enableIzipay, bool $enableCulqi): void
    {
        if ($enableIzipay && $enableCulqi) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'enable_izipay' => [self::IZIPAY_CULQI_EXCLUSIVITY_MESSAGE],
                'enable_culqi' => [self::IZIPAY_CULQI_EXCLUSIVITY_MESSAGE],
            ]);
        }
    }

    /**
     * Garantiza que solo una pasarela de tarjeta (Izipay o Culqi) quede habilitada.
     */
    public function enforceIzipayCulqiExclusivity(): void
    {
        if ($this->enabled_izipay) {
            $this->enabled_culqi = false;
        } elseif ($this->enabled_culqi) {
            $this->enabled_izipay = false;
        }
    }

    /**
     * Valida exclusividad en preferencias de ecommerce (enable_izipay / enable_culqi).
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public static function validateEcommerceIzipayCulqiExclusivity(bool $enableIzipay, bool $enableCulqi): void
    {
        static::validateIzipayCulqiExclusivity($enableIzipay, $enableCulqi);
    }

    /**
     * Aplica exclusividad en preferencias de ecommerce tras un guardado válido.
     */
    public static function enforceEcommerceIzipayCulqiExclusivity(array &$preferences): void
    {
        $enableIzipay = (bool) ($preferences['enable_izipay'] ?? false);
        $enableCulqi = (bool) ($preferences['enable_culqi'] ?? false);

        if ($enableIzipay) {
            $preferences['enable_culqi'] = 0;
        } elseif ($enableCulqi) {
            $preferences['enable_izipay'] = 0;
        }
    }

}
