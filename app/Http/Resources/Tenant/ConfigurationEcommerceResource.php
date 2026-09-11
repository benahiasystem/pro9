<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Resources\Json\JsonResource;

class ConfigurationEcommerceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request
     * @return array
     */
    public function toArray($request) {
        return [
            'id' => $this->id,
            'information_contact_name' => $this->information_contact_name,
            'information_contact_email' =>  $this->information_contact_email,
            'information_contact_phone' =>  $this->information_contact_phone,
            'information_contact_address' =>  $this->information_contact_address,
            'script_paypal' => $this->script_paypal,
            'token_private_culqui' => $this->token_private_culqui,
            'token_public_culqui' => $this->token_public_culqui,
            'logo' => $this->logo,
            'link_youtube' => $this->link_youtube,
            'link_twitter' => $this->link_twitter,
            'link_facebook' => $this->link_facebook,
            'link_tiktok' => $this->link_tiktok,
            'link_instagram' => $this->link_instagram,
            'phone_whatsapp' => $this->phone_whatsapp,
            'title_one_customised_link' => $this->title_one_customised_link,
            'title_two_customised_link' => $this->title_two_customised_link,
            'title_three_customised_link' => $this->title_three_customised_link,
            'customised_link_one' => $this->customised_link_one,
            'customised_link_two' => $this->customised_link_two,
            'customised_link_three' => $this->customised_link_three,
            'color_ecommerce' => $this->color_ecommerce,
            'preferences' => $this->preferences,
            'terms_conditions'              => $this->terms_conditions,
            'privacy_policy'               => $this->privacy_policy,
            'about_us'                     => $this->about_us,
            'enable_electronic_documents'  => (bool) $this->enable_electronic_documents,
            'enable_store_pickup'           => (bool) $this->enable_store_pickup,
            'quotation_enabled'             => (bool) ($this->quotation_enabled ?? false),
            'quotation_mode'                => $this->quotation_mode ?: 'quote_and_sell',
            'quotation_show_prices'         => (bool) ($this->quotation_show_prices ?? true),
            'quotation_success_message'     => $this->quotation_success_message,
            'quotation_validity_days'       => (int) ($this->quotation_validity_days ?: 7),
            'quotation_terms'               => $this->quotation_terms,
            'enable_yape'                   => (bool) $this->enable_yape,
            'enable_transfer'               => (bool) $this->enable_transfer,
            'delivery_no_coverage_message' => $this->delivery_no_coverage_message,
            'publicidad_activa' => (bool) $this->publicidad_activa,
            'publicidad_texto' => $this->publicidad_texto,
            'publicidad_color_fondo' => $this->publicidad_color_fondo,
            'publicidad_link' => $this->publicidad_link,
            'ecommerce_as_home' => (bool) $this->ecommerce_as_home,
        ];
    }
}
