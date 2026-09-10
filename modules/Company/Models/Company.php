<?php

namespace Modules\Company\Models;

use App\Models\Tenant\Catalogs\IdentityDocumentType;
use App\Models\Tenant\ModelTenant;
use Modules\LevelAccess\Models\SystemActivityLog;

class Company extends ModelTenant
{
    protected $fillable = [
        'user_id',
        'identity_document_type_id',
        'number',
        'name',
        'trade_name',
        'logo',
        'logo_dark',
        'operation_amazonia',
        'img_firm',
        'cod_digemid',
        'integrated_query_client_id',
        'integrated_query_client_secret',
        'app_logo',

        // section disabled

        'ws_api_token',
        'ws_api_phone_number_id',
        'qr_api_url_ws',
        'qr_api_key_ws',
        'qr_api_enable_ws',
        'title_web',
        'sire_client_id',
        'sire_client_secret',
        'sire_username',
        'sire_password',
        'security_code',
        'mtc_code',
        'name_person_support_contaweb',
        'telephone_support_contaweb',
        'email_support_contaweb',
    ];

    // ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########
    protected $hidden = ['fiscal_credentials'];
    // ######## FIN MODALIDAD DE EMISIÓN FISCAL ########

    protected $casts = [
        'qr_api_enable_ws' => 'bool'
    ];

    /**
     * @return mixed
     */
    public function getCodDigemid() {
        return $this->cod_digemid;
    }

    /**
     * @param mixed $cod_digemid
     *
     * @return Company
     */
    public function setCodDigemid($cod_digemid) {
        $this->cod_digemid = $cod_digemid;
        return $this;
    }

    public function identity_document_type()
    {
        return $this->belongsTo(IdentityDocumentType::class, 'identity_document_type_id');
    }

    public static function active()
    {
        return Company::first();
    }

    /**
     * @return string|null
     */
    public function getLogo(): ?string
    {
        return $this->logo;
    }

    /**
     * @param string|null $logo
     *
     * @return Company
     */
    public function setLogo(?string $logo): Company
    {
        $this->logo = $logo;
        return $this;
    }


    public function system_activity_logs()
    {
        return $this->morphMany(SystemActivityLog::class, 'origin');
    }


    /**
     *
     * Obtener fiscal_environment para registro de entorno en tablas relacionadas
     *
     * @return string
     */
    public static function getCompanyFiscalEnvironment()
    {
        return Company::select('fiscal_environment')->withOut(['identity_document_type'])->firstOrFail()->fiscal_environment;
    }


    /**
     *
     * Obtener campos para cabecera de reportes
     *
     * @return string
     */
    public static function getDataForReportHeader()
    {
        return self::select(['number', 'name'])->withOut(['identity_document_type'])->firstOrFail();
    }

    public function scopeGetRecordIndividualColumn($query, $column)
    {
        return $query->select($column)->firstOrFail()->{$column};
    }

    // El logo de la app se toma del logo en modo claro
    public static function getAppUrlLogo()
    {
        $logo = self::select('logo')->firstOrFail()->logo;

        if($logo)
        {
            $logo = asset('storage/uploads/logos/'.$logo);
        }

        return $logo;
    }

    public static function getAppUrlLogoDark()
    {
        $logo_dark = self::select('logo_dark')->firstOrFail()->logo_dark;

        if($logo_dark)
        {
            $logo_dark = asset('storage/uploads/logos/'.$logo_dark);
        }

        return $logo_dark;
    }

    public function scopeSelectDataWhatsAppApi($query)
    {
        return $query->select('ws_api_token', 'ws_api_phone_number_id');
    }

    public function scopeSelectQrApiWhatsapp($query)
    {
        return $query->select('qr_api_url_ws', 'qr_api_key_ws', 'qr_api_enable_ws');
    }

    public function scopeGetFiscalEnvironment($query)
    {
        return $query->select('fiscal_environment')->first();
    }

    /**
     *
     * Descripción  del tipo de transaccion asociado al modelo
     *
     * @param  string $column
     * @return string
     */
    // public function getDescriptionColumnForSystemActivity($column)
    // {
    //     $key = "validation.attributes.{$column}";
    //     $trans = __($key);
    //     $description = ($trans == $key) ? $column : $trans;

    //     return 'Actualización del campo '.$description.' en configuración de empresa';
    // }


    /**
     *
     * Descripción de los tipos de transacción para cada actividad
     *
     * @return array
     */
    // public function getTransactionTypesForSystemActivity()
    // {
    //     $data = [];

    //     foreach ($this->getCheckColumnsForSystemActivity() as $column)
    //     {
    //         $data [$this->getTransactionTypeForSystemActivity($column)] = $this->getDescriptionColumnForSystemActivity($column);
    //     }

    //     return $data;
    // }


    /**
     *
     * Columnas a verificar para registro de actividad
     *
     * @return array
     */
    public function getCheckColumnsForSystemActivity()
    {
        return ['number', 'name'];
    }

    public function scopeSelectCertificateQzTray($query)
    {
        return $query->select('digital_certificate_qztray', 'private_certificate_qztray')->withOut(['identity_document_type']);
    }

    /**
     *
     * @param  string $column
     * @return string
     */
    public function getTransactionTypeForSystemActivity($column)
    {
        return "{$this->getTable()}_{$column}";
    }

}
