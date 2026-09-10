<?php
// ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Tenant\Configuration;


/**
 * Class CompanyResource
 *
 * @package App\Http\Resources\Tenant
 * @mixin JsonResource
 */
class CompanyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request
     * @return array
     */
    public function toArray($request)
    {
        $configuration = Configuration::first();
        return [
            'id' => $this->id,
            'number' => $this->number,
            'name' => $this->name,
            'trade_name' => $this->trade_name,
            ...\App\Services\FiscalEmissionSettings::publicData($this->resource),
            'logo' => $this->logo,
            'logo_dark' => $this->logo_dark,
            'logo_store' => $this->logo_store,
            'operation_amazonia' => (bool) $this->operation_amazonia,
            'img_firm' => $this->img_firm,
            'favicon' => $this->favicon,
            'cod_digemid' => $this->cod_digemid,
            'is_pharmacy' => $configuration->isPharmacy(),
            'integrated_query_client_id' => $this->integrated_query_client_id,
            'integrated_query_client_secret' => $this->integrated_query_client_secret,
            'app_logo' => $this->app_logo,
            'title_web' => $this->title_web,
            'smtp_host' => $configuration->smtp_host,
            'smtp_port' => $configuration->smtp_port,
            'smtp_user' => $configuration->smtp_user,
            'smtp_password' => $configuration->smtp_password,
            'smtp_encryption' => $configuration->smtp_encryption,
            'qr_api_enable_ws' => $configuration->qr_api_enable,
            'qr_api_url_ws' => $configuration->qr_api_url,
            'qr_api_key_ws' => $configuration->qr_api_apiKey,
            'mtc_code' => $this->mtc_code
        ];
    }
}
// ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
