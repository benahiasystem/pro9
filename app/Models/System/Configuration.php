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
    ];


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
                        'use_login_global',
                        'tenant_show_ads',
                        'tenant_image_ads'
                    ])
                    ->firstOrFail();
    }

    
    /**
     * 
     * Url de imagen para publicidad en clientes (header)
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
