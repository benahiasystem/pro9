<?php

namespace App\Models\System;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class Configuration extends Model
{
    use UsesSystemConnection;

    protected $fillable = [
        'locked_admin',
        'certificate',
        'soap_send_id',
        'soap_type_id',
        'soap_username',
        'soap_password',
        'soap_url',
        'token_public_culqui',
        'token_private_culqui',
        'url_apiruc',
        'token_apiruc',
        'apk_url',
        'openai_api_key',
        'openai_model',
        'login',
        'use_login_global',
        'enable_guest_register',
        'guest_register_plan_id',
        'validate_ruc_register',
        'regex_password_client',
        'tenant_show_ads',
        'tenant_image_ads',
        'tenant_ads_link',
        'tenant_ads_toolbar',
        'tenant_ads_notification',
        'mail_host',
        'mail_port',
        'mail_username',
        'mail_password',
        'mail_encryption',
        'google_maps_api_key',
        'qr_api_msg',
        'evolution_server_url',
        'evolution_server_apikey',
        'whatsapp_provider',
        'notify_wa_instance',
        'notify_wa_connection_state',
        'notify_wa_connected_phone',
        'notify_wa_profile_name',
        'notify_wa_connected_at',
        'notify_wa_enabled',
        'notify_wa_api_token',
        'notify_wa_provider',
        'notify_wa_waha_server_key',
        'active_cron',
        'hour_generate_payment_order',
        'day_before_due',
        'send_notification_cron',
        'username_izipay',
        'password_izipay',
        'publickey_izipay',
        'sha256key_izipay',
        'enabled_izipay',
        'enabled_culqi',
        'enabled_mp',
        'access_token_mp',
        'public_key_mp',
        'terms_mode',
        'terms_content',
        'terms_url',
        'mozo_configuration',
        'vendeya_configuration',
        'git_remote_url',
        'git_provider',
        'git_user',
        'git_token',
    ];


    protected $casts = [
        'regex_password_client' => 'boolean',
        'tenant_show_ads' => 'boolean',
        'enable_guest_register' => 'boolean', // Añadir aquí
        'validate_ruc_register' => 'boolean',
        'active_cron' => 'boolean',
        'enabled_izipay' => 'boolean',
        'enabled_culqi' => 'boolean',
        'enabled_mp' => 'boolean',
        'notify_wa_enabled' => 'boolean',
        'notify_wa_connected_at' => 'datetime',
        'mozo_configuration' => 'array',
        'vendeya_configuration' => 'array',
        'tenant_ads_toolbar' => 'array',
        'tenant_ads_notification' => 'array',
    ];

    /**
     * Memoria del request con la fila de publicidad. false = aun no consultado.
     *
     * @var self|null|false
     */
    private static $tenant_ads_configuration = false;


    public static function boot()
    {
        parent::boot();
        static::creating(function (self $item) {

            // if(empty($item->apk_url)) $item->apk_url = 'https://facturaloperu.com/apk/app-debug.apk';
        });
        static::retrieved(function (self $item) {

            // if (empty($item->apk_url)) $item->apk_url = 'https://facturaloperu.com/apk/app-debug.apk';
        });

    }

    public function getUseLoginGlobalAttribute($value)
    {
        return $value ? true : false;
    }

    public function setLoginAttribute($value)
    {
        $this->attributes['login'] = is_null($value) ? null : json_encode($value);
    }

    public function getLoginAttribute($value)
    {
        return is_null($value) ? null : (object) json_decode($value);
    }


    public static function getApiServiceToken(){
        $configuration = self::first();
        // $api_service_token = $configuration->token_apiruc =! '' ? $configuration->token_apiruc : config('configuration.api_service_token');
        $api_service_token = $configuration->token_apiruc == 'false' ? config('configuration.api_service_token') : $configuration->token_apiruc;
        return $api_service_token;
    }

    public static function getDataModuleViewComposer()
    {
        return self::select([
                        'use_login_global'
                    ])
                    ->firstOrFail();
    }


    /**
     *
     * Url de imagen para publicidad en clientes
     *
     * @return string
     */
    public function getUrlTenantImageAds()
    {
        if($this->tenant_image_ads)
        {
            $separator = DIRECTORY_SEPARATOR;
            return asset("storage{$separator}uploads{$separator}system_ads{$separator}" . $this->tenant_image_ads);
        }

        return null;
    }

    /**
     *
     * Fila con la publicidad que se muestra en los tenant. Memoizada porque el
     * layout consulta varios tipos de anuncio en el mismo request.
     *
     * @return self|null
     */
    private static function tenantAdsConfiguration()
    {
        if(self::$tenant_ads_configuration === false)
        {
            self::$tenant_ads_configuration = self::select([
                                                    'tenant_show_ads',
                                                    'tenant_image_ads',
                                                    'tenant_ads_link',
                                                    'tenant_ads_toolbar',
                                                    'tenant_ads_notification'
                                                ])
                                                ->first();
        }

        return self::$tenant_ads_configuration;
    }

    /**
     *
     * Solo se aceptan enlaces http/https: se pintan en un href del layout del
     * tenant y un javascript: seria XSS.
     *
     * @param  string|null $link
     * @return string|null
     */
    private static function sanitizeTenantAdsLink($link)
    {
        return ($link && preg_match('#^https?://#i', $link)) ? $link : null;
    }

    /**
     *
     * El color termina en un atributo style, asi que solo se acepta hexadecimal.
     *
     * @param  string|null $color
     * @param  string      $fallback
     * @return string
     */
    private static function sanitizeTenantAdsColor($color, $fallback)
    {
        $color = trim((string) $color);

        return preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $color) ? $color : $fallback;
    }

    /**
     *
     * El svg del icono se inyecta sin escapar en el layout del tenant, asi que
     * se valida contra lista blanca: el catalogo de Tabler solo usa <path> con
     * los atributos d, fill, opacity y stroke. Cualquier otra cosa se descarta.
     *
     * @param  string|null $svg
     * @return string|null
     */
    private static function sanitizeTenantAdsIconSvg($svg)
    {
        $svg = trim((string) $svg);

        if($svg === '')
        {
            return null;
        }

        $allowed = '#^(?:<path(?:\s+(?:d|fill|opacity|stroke)="[^"<>]*")+\s*/?>)+$#';

        return preg_match($allowed, $svg) ? $svg : null;
    }

    /**
     *
     * Publicidad tipo modal que se muestra centrada en los tenant.
     *
     * @return object|null
     */
    public static function getTenantModalAds()
    {
        $configuration = self::tenantAdsConfiguration();

        if($configuration && $configuration->tenant_show_ads && $configuration->tenant_image_ads)
        {
            $link = self::sanitizeTenantAdsLink($configuration->tenant_ads_link);

            return (object) [
                'image' => $configuration->getUrlTenantImageAds(),
                'link' => $link,
                'version' => substr(md5($configuration->tenant_image_ads.'|'.$configuration->tenant_ads_link), 0, 12),
            ];
        }

        return null;
    }

    /**
     *
     * Publicidad tipo barra que se muestra encima del header de los tenant.
     *
     * @return object|null
     */
    public static function getTenantToolbarAds()
    {
        $configuration = self::tenantAdsConfiguration();
        $toolbar = $configuration ? $configuration->tenant_ads_toolbar : null;

        if(!is_array($toolbar) || empty($toolbar['enabled']))
        {
            return null;
        }

        $text = trim((string) ($toolbar['text'] ?? ''));

        if($text === '')
        {
            return null;
        }

        $link = self::sanitizeTenantAdsLink($toolbar['link'] ?? null);

        return (object) [
            'text' => $text,
            'link' => $link,
            'background_color' => self::sanitizeTenantAdsColor($toolbar['background_color'] ?? null, '#1b7fd4'),
            'text_color' => self::sanitizeTenantAdsColor($toolbar['text_color'] ?? null, '#ffffff'),
            'dismissible' => (bool) ($toolbar['dismissible'] ?? true),
            'version' => substr(md5($text.'|'.$link), 0, 12),
        ];
    }

    /**
     *
     * Publicidad tipo notificacion (toast) que se muestra en una esquina.
     *
     * @return object|null
     */
    public static function getTenantNotificationAds()
    {
        $configuration = self::tenantAdsConfiguration();
        $notification = $configuration ? $configuration->tenant_ads_notification : null;

        if(!is_array($notification) || empty($notification['enabled']))
        {
            return null;
        }

        $title = trim((string) ($notification['title'] ?? ''));
        $description = trim((string) ($notification['description'] ?? ''));

        if($title === '' && $description === '')
        {
            return null;
        }

        $positions = ['top-left', 'top-right', 'bottom-left', 'bottom-right'];
        $position = $notification['position'] ?? null;
        $icon_type = $notification['icon_type'] ?? 'none';
        $icon_svg = self::sanitizeTenantAdsIconSvg($notification['icon_svg'] ?? null);
        $emoji = trim((string) ($notification['emoji'] ?? ''));

        if($icon_type === 'tabler' && $icon_svg === null)
        {
            $icon_type = 'none';
        }

        if($icon_type === 'emoji' && $emoji === '')
        {
            $icon_type = 'none';
        }

        $link = self::sanitizeTenantAdsLink($notification['link'] ?? null);

        return (object) [
            'position' => in_array($position, $positions, true) ? $position : 'bottom-right',
            'duration' => max(0, (int) ($notification['duration'] ?? 0)),
            'icon_type' => in_array($icon_type, ['none', 'tabler', 'emoji'], true) ? $icon_type : 'none',
            'icon_svg' => $icon_svg,
            'emoji' => $emoji,
            'title' => $title,
            'description' => $description,
            'link' => $link,
            'version' => substr(md5($title.'|'.$description.'|'.$link), 0, 12),
        ];
    }

    /**
     * True si hay un numero conectado por QR habilitado para enviar
     * notificaciones por WhatsApp. Las credenciales legacy
     * (qr_api_url/qr_api_token) quedaron muertas y ya no cuentan.
     */
    public function hasWhatsappNotifySender(): bool
    {
        // No se exige connection_state === 'open': ese campo solo se refresca
        // cuando el admin abre la pantalla de configuracion y puede quedar
        // desactualizado. El envio verifica el estado real contra el proveedor.
        return $this->notify_wa_enabled && !empty($this->notify_wa_instance);
    }

    public function validationConfigNotify()
    {
        $errors = [
            'ws' => null,
            'email' => null,
        ];
        if (!$this->hasWhatsappNotifySender()) {
            $errors['ws'] = 'Falta configurar los parámetros para el envío de notificaciones por WhatsApp';
            return $errors;
        } else if (
            empty($this->mail_host) ||
            empty($this->mail_port) ||
            empty($this->mail_username) ||
            empty($this->mail_password) ||
            empty($this->mail_encryption)
        ) {
            $errors['email'] = 'Falta configurar los parámetros para el envío de notificaciones por email';
            return $errors;
        }

        return $errors;

    }

    public static function setConfigSmtpMail()
    {
        $config = self::first();
                if (
                    !empty($config->mail_host) &&
                    !empty($config->mail_port) &&
                    !empty($config->mail_username) &&
                    !empty($config->mail_password) &&
                    !empty($config->mail_encryption)
                ) {

                    Config::set('mail.host', $config->mail_host);
                    Config::set('mail.port', $config->mail_port);
                    Config::set('mail.username', $config->mail_username);
                    Config::set('mail.password', $config->mail_password);
                    Config::set('mail.encryption', $config->mail_encryption);
                }
    }

    /**
     * Credenciales de izipay, null si no hay configuración
     */
    public function scopeAccessIzipay($query)
    {
        $record = $query
            ->select('username_izipay', 'password_izipay', 'publickey_izipay', 'sha256key_izipay')
            ->first();

        return optional($record)->toArray();
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
}
