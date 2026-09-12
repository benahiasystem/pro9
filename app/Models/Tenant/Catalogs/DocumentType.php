<?php

    namespace App\Models\Tenant\Catalogs;

    use App\Models\Tenant\Dispatch;
    use App\Models\Tenant\Document;
    use App\Models\Tenant\Perception;
    use App\Models\Tenant\PerceptionDocument;
    use App\Models\Tenant\Purchase;
    use App\Models\Tenant\PurchaseSettlement;
    use App\Models\Tenant\Retention;
    use App\Models\Tenant\RetentionDocument;
    use App\Models\Tenant\SaleNote;
    use App\Models\Tenant\Series;
    use Hyn\Tenancy\Traits\UsesTenantConnection;
    use Illuminate\Database\Eloquent\Builder;
    use Illuminate\Database\Eloquent\Collection;
    use Illuminate\Database\Eloquent\Relations\HasMany;
    use Modules\Document\Models\SeriesConfiguration;
    use Modules\Purchase\Models\FixedAssetPurchase;


    /**
     * Class DocumentType
     *
     * @property string                           $id
     * @property bool                             $active
     * @property string|null                      $short
     * @property string                           $description
     * @property Collection|Dispatch[]            $dispatches_where_document_type
     * @property Collection|Document[]            $documents_where_document_type
     * @property Collection|FixedAssetPurchase[]  $fixed_asset_purchases_where_document_type
     * @property Collection|PerceptionDocument[]  $perception_documents_where_document_type
     * @property Collection|Perception[]          $perceptions_where_document_type
     * @property Collection|PurchaseSettlement[]  $purchase_settlements_where_document_type
     * @property Collection|Purchase[]            $purchases_where_document_type
     * @property Collection|RetentionDocument[]   $retention_documents_where_document_type
     * @property Collection|Retention[]           $retentions_where_document_type
     * @property Collection|Series[]              $series_where_document_type
     * @property Collection|SeriesConfiguration[] $series_configurations_where_document_type
     * @mixin ModelCatalog
     * @package App\Models\Tenant\Catalogs
     * @property-read int|null                    $dispatches_where_document_type_count
     * @property-read int|null                    $documents_where_document_type_count
     * @property-read int|null                    $fixed_asset_purchases_where_document_type_count
     * @property-read int|null                    $perception_documents_where_document_type_count
     * @property-read int|null                    $perceptions_where_document_type_count
     * @property-read int|null                    $purchase_settlements_where_document_type_count
     * @property-read int|null                    $purchases_where_document_type_count
     * @property-read int|null                    $retention_documents_where_document_type_count
     * @property-read int|null                    $retentions_where_document_type_count
     * @property-read int|null                    $series_configurations_where_document_type_count
     * @property-read int|null                    $series_where_document_type_count
     * @method static Builder|DocumentType documentsActiveToPurchase()
     * @method static Builder|DocumentType newModelQuery()
     * @method static Builder|DocumentType newQuery()
     * @method static Builder|DocumentType onlyActive()
     * @method static Builder|DocumentType onlyAvaibleDocuments()
     * @method static Builder|ModelCatalog orderByDescription()
     * @method static Builder|DocumentType query()
     * @method static Builder|ModelCatalog whereActive()
     */
    class DocumentType extends ModelCatalog
    {
        use UsesTenantConnection;

        // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
        public const SALE_DOCUMENT_TYPES = ['01', '80'];
        // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES

        public const DOCUMENT_TYPE_NOTES = ['07', '08'];
        public const CREDIT_NOTE_ID = '07';

        public $incrementing = false;
        public $timestamps = false;
        protected $table = "cat_document_types";
        protected $fillable = [
            'id',
            'active',
            'short',
            'description'
        ];

        /**
         * @return mixed
         */
        public function getActive()
        {
            return $this->active;
        }

        /**
         * @param mixed $active
         *
         * @return DocumentType
         */
        public function setActive($active)
        {
            $this->active = $active;
            return $this;
        }

        /**
         * @return mixed
         */
        public function getShort()
        {
            return $this->short;
        }

        /**
         * @param mixed $short
         *
         * @return DocumentType
         */
        public function setShort($short)
        {
            $this->short = $short;
            return $this;
        }

        /**
         * @return mixed
         */
        public function getDescription()
        {
            return $this->description;
        }

        /**
         * @param mixed $description
         *
         * @return DocumentType
         */
        public function setDescription($description)
        {
            $this->description = $description;
            return $this;
        }

        /**
         * @return Builder
         */
        public function scopeOnlyActive($query)
        {
            return $query->where('active', 1);
        }

        /**
         * @return Builder
         */
        public function scopeOnlyAvaibleDocuments($query)
        {
            // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
            return $query->OnlyActive()->wherein('id', [
                '01', 'FE', '07', '08', '20', 'ISLR', '09', 'CBU', '80', 'U2', 'U3', 'U4',
            ]);
            // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES
        }

        /**
         * Devuelve los elementos activos para compras
         *
         * @return Builder
         */
        public function scopeDocumentsActiveToPurchase($query)
        {
            return $query->OnlyActive()->wherein('id', ['01', 'NE76']);
        }

        public function scopeDocumentsActiveToSettlement($query)
        {
            return $query->OnlyActive()->wherein('id', ['04']);
        }


        /**
         * @return HasMany
         */
        public function dispatches_where_document_type()
        {
            return $this->hasMany(Dispatch::class, 'document_type_id', 'id');
        }

        /**
         * @return HasMany
         */
        public function documents_where_document_type()
        {
            return $this->hasMany(Document::class, 'document_type_id', 'id');
        }

        /**
         * @return HasMany
         */
        public function fixed_asset_purchases_where_document_type()
        {
            return $this->hasMany(FixedAssetPurchase::class, 'document_type_id', 'id');
        }

        /**
         * @return HasMany
         */
        public function perception_documents_where_document_type()
        {
            return $this->hasMany(PerceptionDocument::class, 'document_type_id', 'id');
        }

        /**
         * @return HasMany
         */
        public function perceptions_where_document_type()
        {
            return $this->hasMany(Perception::class, 'document_type_id', 'id');
        }

        /**
         * @return HasMany
         */
        public function purchase_settlements_where_document_type()
        {
            return $this->hasMany(PurchaseSettlement::class, 'document_type_id', 'id');
        }

        /**
         * @return HasMany
         */
        public function purchases_where_document_type()
        {
            return $this->hasMany(Purchase::class, 'document_type_id', 'id');
        }

        /**
         * @return HasMany
         */
        public function retention_documents_where_document_type()
        {
            return $this->hasMany(RetentionDocument::class, 'document_type_id', 'id');
        }

        /**
         * @return HasMany
         */
        public function retentions_where_document_type()
        {
            return $this->hasMany(Retention::class, 'document_type_id', 'id');
        }

        /**
         * @return HasMany
         */
        public function series_where_document_type()
        {
            return $this->hasMany(Series::class, 'document_type_id', 'id');
        }

        /**
         * @return HasMany
         */
        public function series_configurations_where_document_type()
        {
            return $this->hasMany(SeriesConfiguration::class, 'document_type_id', 'id');
        }

        /**
         * Devuelve el nombre de la clase correspondiente al documento
         *
         * @return string
         */
        public function getCurrentRelatiomClass()
        {
            if (in_array($this->id, ['01', '07', '08'], true)) {
                return Document::class;
            }
            if ($this->id === '80') {
                return SaleNote::class;
            }
            throw \Illuminate\Validation\ValidationException::withMessages([
                'document_type_id' => 'Seleccione Factura, Nota de crédito, Nota de débito o Nota de venta.',
            ]);
        }


        /**
         * @return Builder
         */
        public function scopeOnlySaleDocuments($query)
        {
            return $query->onlyActive()->select('id', 'description')->whereIn('id', self::SALE_DOCUMENT_TYPES);
        }


        /**
         *
         * Filtro para la descripción
         *
         * @param Builder $query
         * @return Builder
         */
        public function scopeFilterOnlyDescription($query)
        {
            return $query->select('id', 'description');
        }


        /**
         *
         * @return bool
         */
        public function isInvoice()
        {
            return in_array($this->id, self::INVOICE_DOCUMENTS_IDS, true);
        }

    }
