<?php

namespace App\Models\Tenant;

class ConfigurationEcommerce extends ModelTenant
{
    protected $table = "configuration_ecommerce";

    protected $fillable = [
        'information_contact_name',
        'information_contact_email',
        'information_contact_phone',
        'information_contact_address',
        'script_paypal',
        'token_private_culqui',
        'token_public_culqui',
        'link_youtube',
        'link_twitter',
        'link_facebook',
        'link_tiktok',
        'link_instagram',
        'phone_whatsapp',
        'title_one_customised_link',
        'title_two_customised_link',
        'title_three_customised_link',
        'customised_link_one',
        'customised_link_two',
        'customised_link_three',
        'color_ecommerce',
        'preferences',
        'terms_conditions',
        'privacy_policy',
        'about_us',
        'delivery_no_coverage_message',
        'enable_electronic_documents',
        'enable_store_pickup',
        'quotation_enabled',
        'quotation_mode',
        'quotation_show_prices',
        'quotation_success_message',
        'quotation_validity_days',
        'quotation_terms',
        'enable_yape',
        'enable_transfer',
        'publicidad_activa',
        'publicidad_texto',
        'publicidad_color_fondo',
        'publicidad_link',
        'ecommerce_as_home'
    ];

    protected $casts = [
        'preferences'                  => 'array',
        'enable_electronic_documents'  => 'boolean',
        'enable_store_pickup'          => 'boolean',
        'quotation_enabled'            => 'boolean',
        'quotation_show_prices'        => 'boolean',
        'quotation_validity_days'      => 'integer',
        'enable_yape'                  => 'boolean',
        'enable_transfer'              => 'boolean',
        'ecommerce_as_home'            => 'boolean',
    ];
    /**
     * Devuelve los enlaces personalizados para el header
     */
    public static function getCustomLinks()
    {
        $config = self::first();
        return [
            'title_one' => $config->title_one_customised_link ?? null,
            'link_one' => $config->customised_link_one ?? null,
            'title_two' => $config->title_two_customised_link ?? null,
            'link_two' => $config->customised_link_two ?? null,
            'title_three' => $config->title_three_customised_link ?? null,
            'link_three' => $config->customised_link_three ?? null,
        ];
    }

    /**
     * Tienda virtual como página principal: la raíz del subdominio redirige al ecommerce.
     */
    public static function isHomePage(): bool
    {
        return (bool) optional(self::first())->ecommerce_as_home;
    }

    /**
     * Configuración de cotizaciones de la tienda virtual.
     */
    public static function storefrontQuotationConfig(): array
    {
        $config = self::first();

        $enabled = (bool) optional($config)->quotation_enabled;
        $mode = optional($config)->quotation_mode ?: 'quote_and_sell';
        // Ocultar precios solo aplica en modo "solo cotizar".
        $showPrices = ($enabled && $mode === 'quote_only')
            ? (bool) (optional($config)->quotation_show_prices ?? true)
            : true;

        return [
            'enabled' => $enabled,
            'mode' => $mode,
            'show_prices' => $showPrices,
            'success_message' => optional($config)->quotation_success_message
                ?: 'Registramos tu solicitud. Nuestro equipo la revisará a la brevedad.',
            'validity_days' => max(1, min(90, (int) (optional($config)->quotation_validity_days ?: 7))),
            'terms' => optional($config)->quotation_terms,
        ];
    }

    /**
     * Cotizaciones activas y en modo solo cotizar (sin ventas en tienda).
     */
    public static function isStorefrontQuoteOnly(): bool
    {
        $settings = self::storefrontQuotationConfig();

        return $settings['enabled'] && $settings['mode'] === 'quote_only';
    }

    /**
     * ¿Mostrar precios en la tienda virtual?
     * Si cotizaciones están desactivadas o el modo es "cotizar y vender", siempre sí.
     * Solo en "solo cotizar" se respeta quotation_show_prices.
     */
    public static function storefrontShowsPrices(): bool
    {
        return (bool) self::storefrontQuotationConfig()['show_prices'];
    }

}
