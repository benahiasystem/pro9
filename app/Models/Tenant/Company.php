<?php

namespace App\Models\Tenant;

use App\Models\Tenant\Catalogs\IdentityDocumentType;
use App\Models\Tenant\FiscalEnvironment;
use Modules\LevelAccess\Models\SystemActivityLog;


/**
 * Class Company
 *
 * @package App\Models\Tenant
 * @mixin  ModelTenant
 */
class Company extends ModelTenant
{
    protected $with = ['identity_document_type'];
    protected $fillable = [
        'user_id',
        'identity_document_type_id',
        'number',
        'name',
        'trade_name',
        'fiscal_environment',
        'digital_certificate_qztray',
        'private_certificate_qztray',
        'logo',
        'logo_dark',
        'img_firm',
        'cod_digemid',
        'integrated_query_client_id',
        'integrated_query_client_secret',
        'app_logo',
        'ws_api_token',
        'ws_api_phone_number_id',
        'title_web',
    ];

    protected $casts = [
            'fiscal_profile' => 'array',
        'rif_document' => 'array',
        // ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########
        'fiscal_configuration' => 'array',
        'fiscal_credentials' => 'encrypted',
        'hka_authenticated_at' => 'datetime',
        'fiscal_environment_locked' => 'boolean',
        // ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
    ];

    // ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########
    protected $hidden = ['fiscal_credentials', 'mtc_code'];
    // ######## FIN MODALIDAD DE EMISIÓN FISCAL ########

    /**
     * @return mixed
     */
    public function getCodDigemid()
    {
        return $this->cod_digemid;
    }

    /**
     * @param mixed $cod_digemid
     *
     * @return Company
     */
    public function setCodDigemid($cod_digemid)
    {
        $this->cod_digemid = $cod_digemid;
        return $this;
    }

    public function identity_document_type()
    {
        return $this->belongsTo(IdentityDocumentType::class, 'identity_document_type_id');
    }

    // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
    public static function active()
    {
        return Company::first();
    }
    // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE

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


    /**
     *
     * Obtener campo individual
     *
     * @param  Builder $query
     * @param  string $column
     * @return Builder
     */
    public function scopeGetRecordIndividualColumn($query, $column)
    {
        return $query->select($column)->firstOrFail()->{$column};
    }


    /**
     *
     * Obtener logo de la app, se toma del logo en modo claro
     *
     * @param  Builder $query
     * @return string
     */
    public static function getAppUrlLogo()
    {
        $logo = self::select('logo')->firstOrFail()->logo;

        if ($logo) {
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
            $logo = tenant_asset('storage/uploads/logos/' . $logo);
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        }

        return $logo;
    }


    /**
     *
     * Obtener logo de la app para modo oscuro, null si no se ha subido
     *
     * @return string|null
     */
    public static function getAppUrlLogoDark()
    {
        $logo_dark = self::select('logo_dark')->firstOrFail()->logo_dark;

        if ($logo_dark) {
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
            $logo_dark = tenant_asset('storage/uploads/logos/' . $logo_dark);
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        }

        return $logo_dark;
    }


    /**
     *
     * Filtrar datos para whatsapp api
     *
     * @param  Builder $query
     * @return Builder
     */
    public function scopeSelectDataWhatsAppApi($query)
    {
        return $query->select('ws_api_token', 'ws_api_phone_number_id');
    }

    /**
     * Obtener unicamente los noombre del archivos de Qz Tray
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */

    public function scopeSelectCertificateQzTray($query)
    {
        return $query->select('digital_certificate_qztray', 'private_certificate_qztray')->withOut(['identity_document_type']);
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


    /**
     *
     * @param  string $column
     * @return string
     */
    public function getTransactionTypeForSystemActivity($column)
    {
        return "{$this->getTable()}_{$column}";
    }

    public function fiscal_environment_type()
    {
        return $this->belongsTo(FiscalEnvironment::class, 'fiscal_environment');
    }

    public function scopeGetInformationCompany($query)
    {
        return $query->select('number', 'name')->first();
    }
}
