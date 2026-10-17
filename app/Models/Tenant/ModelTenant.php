<?php

// ######## INICIO MIGRACIÓN MONEDA VENEZUELA ########

    namespace App\Models\Tenant;

    use Hyn\Tenancy\Traits\UsesTenantConnection;
    use Illuminate\Database\Eloquent\Builder;
    use Illuminate\Database\Eloquent\Model;
    use Modules\Inventory\Models\Warehouse;


    /**
     * Class ModelTenant
     *
     * @package App\Models\Tenant
     * @mixin Model
     * @mixin \Eloquent
     * @mixin \Illuminate\Database\Query\Builder as Builder
     * @mixin \Illuminate\Database\Eloquent\Collection
     * @method static Builder|ModelTenant newModelQuery()
     * @method static Builder|ModelTenant newQuery()
     * @method static Builder|ModelTenant query()
     */
    class ModelTenant extends Model
    {
        use UsesTenantConnection;

        // ######## INICIO CALCULOS EXACTOS PRO9 ########
        protected static function booted()
        {
            static::saved(fn ($model) => \App\Support\Exact\CommercialCalculation::afterLine($model));
            static::deleted(fn ($model) => \App\Support\Exact\CommercialCalculation::afterLine($model));
            static::saved(fn ($model) => \App\Support\Exact\Settlement::afterPayment($model));
            static::deleted(fn ($model) => \App\Support\Exact\Settlement::afterPayment($model));
        }

        public function setAttribute($key, $value)
        {
            if ($value instanceof \Brick\Math\BigRational) {
                $snapshot = $this->calculation_snapshot ?? \App\Support\Exact\Decimal::snapshot([]);
                $snapshot['values'][$key] = \App\Support\Exact\Decimal::fraction($value);
                parent::setAttribute('calculation_snapshot', $snapshot);
                $value = \App\Support\Exact\Decimal::represent($value);
            }
            return parent::setAttribute($key, $value);
        }

        public function isFillable($key)
        {
            if (in_array($key, ['quantity_input','price_input'], true) && \App\Support\Exact\CommercialCalculation::supportsLine($this)) return true;
            return parent::isFillable($key);
        }

        protected function fillableFromArray(array $attributes)
        {
            $fillable=parent::fillableFromArray($attributes);
            if (\App\Support\Exact\CommercialCalculation::supportsLine($this)) {
                foreach (['quantity_input','price_input'] as $field) {
                    if (array_key_exists($field,$attributes)) $fillable[$field]=$attributes[$field];
                }
            }
            return $fillable;
        }

        protected function castAttribute($key, $value)
        {
            if ($value !== null && in_array($key, \App\Support\Exact\Persistence::columns($this), true)) {
                return \App\Support\Exact\Decimal::represent($value);
            }
            return parent::castAttribute($key, $value);
        }
        // ######## FIN CALCULOS EXACTOS PRO9 ########

        // ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########
        // ######## INICIO TASAS OCHO DECIMALES ########
        public function getCasts()
        {
            $casts = parent::getCasts();
            // ######## INICIO CALCULOS EXACTOS PRO9 ########
            $casts['calculation_snapshot'] = 'array';
            foreach (\App\Support\Exact\Persistence::columns($this) as $column) {
                $casts[$column] = 'string';
            }
            // ######## FIN CALCULOS EXACTOS PRO9 ########
            foreach (['exchange_rate_sale', 'exchange_rate'] as $column) {
                if (in_array($column, $this->getFillable(), true) && !$this->hasGetMutator($column) && (!isset($casts[$column]) || $casts[$column] === 'float' || str_starts_with($casts[$column], 'decimal:'))) {
                    $casts[$column] = 'decimal:8';
                }
            }
            return $casts;
        }
        // ######## FIN TASAS OCHO DECIMALES ########

        public function save(array $options = [])
        {
            if (\App\Support\Exact\Persistence::enabled($this) && $this->getConnection()->transactionLevel() === 0) {
                return $this->getConnection()->transaction(fn () => $this->saveWithExactCalculation($options));
            }
            return $this->saveWithExactCalculation($options);
        }

        public function delete()
        {
            if (\App\Support\Exact\Persistence::enabled($this) && $this->getConnection()->transactionLevel() === 0) {
                return $this->getConnection()->transaction(fn () => parent::delete());
            }
            return parent::delete();
        }

        private function saveWithExactCalculation(array $options = [])
        {
            // ######## INICIO CALCULOS EXACTOS PRO9 ########
            \App\Support\Exact\Persistence::prepare($this);
            // ######## FIN CALCULOS EXACTOS PRO9 ########
            // ######## INICIO TASAS OCHO DECIMALES ########
            foreach (['exchange_rate_sale', 'exchange_rate'] as $column) {
                if (array_key_exists($column, $this->attributes) && ($this->getCasts()[$column] ?? '') === 'decimal:8') {
                    $value = $this->attributes[$column];
                    if ($value !== null) $this->attributes[$column] = is_numeric($value) && $value == 0
                        ? '0.00000000' : \App\Services\ExchangeRates\ExchangeRateMath::rate($value, $column);
                }
            }
            // ######## FIN TASAS OCHO DECIMALES ########
            if ($this->exists || !in_array($this->getTable(), config('fiscal_emission.operation_tables', []), true)) {
                return parent::save($options);
            }
            if (array_key_exists('series', $this->getAttributes())) {
                $this->series = \App\Services\SeriesNumbering::normalizeCode($this->series);
            }
            return $this->getConnection()->transaction(function () use ($options) {
                $company = Company::query()->lockForUpdate()->first();
                // Seeders may create catalog opening stock before the company is inserted.
                if ($company) {
                    if ($this->getTable() === 'documents') {
                        if (!array_key_exists($company->fiscal_emission_mode ?? '', \App\Services\FiscalEmissionSettings::MODES)) {
                            throw \Illuminate\Validation\ValidationException::withMessages([
                                'fiscal_emission_mode' => 'Seleccione la modalidad de emisión fiscal en la configuración de la empresa antes de registrar facturas.',
                            ]);
                        }
                        $this->setAttribute('fiscal_emission_mode', $company->fiscal_emission_mode);
                        // ######## INICIO PERSISTENCIA FISCAL VENEZUELA ########
                        if ($this instanceof Document) \App\Services\Fiscal\FiscalDocumentPersistence::initialize($this, $company);
                        // ######## FIN PERSISTENCIA FISCAL VENEZUELA ########
                    }
                    if ($this->isFillable('fiscal_environment')) {
                        $this->setAttribute('fiscal_environment', $company->fiscal_environment);
                    }
                    $company->fiscal_environment_locked = true;
                    $company->save();
                }
                if ($this->getTable() === 'dispatches') {
                    $this->number = \App\Services\SeriesNumbering::next($this, $this->document_type_id, $this->series, $this->number, $this->establishment_id);
                    $this->filename = \App\CoreFacturalo\Requests\Inputs\Functions::filename($company, $this->document_type_id, $this->series, $this->number, $this->establishment_id);
                }
                return parent::save($options);
            });
        }
        // ######## FIN MODALIDAD DE EMISIÓN FISCAL ########

        public const RESERVED_SYMBOLS_FILTER = ['-', '+', '<', '>', '@', '(', ')', '~'];

        public const VOIDED_REJECTED_IDS = ['09', '11'];

        public const STATE_TYPES_ACCEPTED = ['01', '03', '05', '07', '13'];

        public const STATE_TYPE_REGISTERED = '01';

        public const FISCAL_ENVIRONMENT_PRODUCTION = 'production';

        public const NATIONAL_CURRENCY_ID = 'VES';

        public const DOLAR_CURRENCY_ID = 'USD';

        public const INVOICE_DOCUMENTS_IDS = ['01'];

        /**
         * Devuelve un esqueleto del array de data extra. Previene error de no enconrarse la funcion en otros modelos
         *
         * @return array
         */
        public function getPrintExtraData()
        {

            return [
                'colors' => null,
                'CatItemUnitsPerPackage' => null,
                'CatItemMoldProperty' => null,
                'CatItemProductFamily' => null,
                'CatItemMoldCavity' => null,
                'CatItemPackageMeasurement' => null,
                'CatItemStatus' => null,
                'CatItemUnitBusiness' => null,
                'CatItemSize' => null,
                'image' => null,
                'image_medium' => null,
                'image_small' => null,
            ];
        }

        /**
         * Evalua si el attr se encuentra en el modelo.
         *
         * @param $attr
         *
         * @return bool
         *@example
         *<code>
         *         if($row->hasAttribute('item')) {
         *         // do something
         * }
         * </code>
         */
        public function hasAttribute($attr)
        {
            return array_key_exists($attr, $this->attributes);
        }


        /**
         * @param  float $value
         * @param  float $exchange_rate_sale
         * @return float
         */
        public function generalConvertValueToPen($value, $exchange_rate_sale)
        {
            return \App\Support\Exact\Decimal::represent(\App\Support\Exact\Decimal::convert($value, 'USD', 'VES', $exchange_rate_sale));
        }


        /**
         *
         * Aplicar formato a fechas
         *
         * @param  $date
         * @param  string $format
         * @return string
         */
        public function generalFormatDate($date, $format = 'Y-m-d')
        {
            return $date->format($format);
        }


        /**
         *
         * Reemplazar simbolos reservados por espacio, para busqueda avanzada
         *
         * @param  string $value
         * @return string
         */
        public function replaceReservedSymbols($value)
        {
            return str_replace(self::RESERVED_SYMBOLS_FILTER, ' ', $value);
        }


        /**
         *
         * Obtener arreglo con los valores a buscar - busqueda avanzada
         *
         * @param  string $value
         * @return array
         */
        public function getSearchValues($value)
        {
            $search_term = $this->replaceReservedSymbols($value);

            return preg_split('/\s+/', $search_term, -1, PREG_SPLIT_NO_EMPTY);
        }


        /**
         *
         * Aplicar formato
         *
         * @param  $value
         * @param  int $decimals
         * @return string
         */
        public function generalApplyNumberFormat($value, $decimals = 2)
        {
            return \App\Support\Exact\Decimal::represent($value, $decimals, true);
        }

        // ######## INICIO CALCULOS EXACTOS PRO9 ########
        public function exactValue(string $field): \Brick\Math\BigRational
        {
            return \App\Support\Exact\Decimal::read($this, $field);
        }

        public function formatPdfUnitPrice($configuration = null): string
        {
            $configuration ??= Configuration::getDataDecimalQuantity();
            return \App\Support\Exact\PdfPrice::format($this->exactValue('unit_price'), $configuration);
        }

        public function setExactValues(array $values): self
        {
            $snapshot = $this->calculation_snapshot ?? \App\Support\Exact\Decimal::snapshot([]);
            foreach ($values as $field => $value) {
                $snapshot['values'][$field] = \App\Support\Exact\Decimal::fraction($value);
                $this->setAttribute($field, \App\Support\Exact\Decimal::represent($value));
            }
            $this->calculation_snapshot = $snapshot;
            return $this;
        }
        // ######## FIN CALCULOS EXACTOS PRO9 ########


        /**
         *
         * Obtener el id del almacen relacionado al establecimiento asignado al usuario
         *
         * @return int
         */
        public function getCurrentWarehouseId()
        {
            return Warehouse::select('id')->where('establishment_id', auth()->user()->establishment_id)->first()->id;
        }


        /**
         *
         * Obtener relaciones necesarias o aplicar filtros a documentos, para reporte pagos - finanzas
         *
         * Se define scope global para no afectar a modelos que no aplican filtros al reporte
         * si se requiere usar filtros, sobreescribir el metodo en el modelo afectado
         *
         *
         * @param  Builder $query
         * @return Builder
         */
        public function scopeFilterRelationsGlobalPayment($query)
        {
            return $query;
        }


        /**
         *
         * Filtro para no incluir relaciones en consultas de tablas asociadas a pagos
         *
         * @param \Illuminate\Database\Eloquent\Builder $query
         * @return \Illuminate\Database\Eloquent\Builder
         */
        public function scopeGeneralPaymentsWithOutRelations($query)
        {
            return $query->withOut(['payment_method_type', 'card_brand']);
        }


        /**
         *
         * Url imagen
         *
         * @param  string $folder
         * @param  string $filename
         * @return string
         */
        public function getPathPublicUploads($folder, $filename)
        {
            return asset('storage' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $filename);
        }


        /**
         *
         * Path imagen
         *
         * @param  string $folder
         * @param  string $filename
         * @return string
         */
        public function getGeneralFilePublicPath($folder, $filename)
        {
            return public_path('storage' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $filename);
        }


        /**
         *
         * Descripcion de los items
         *
         * @param  mixed $model
         * @return string
         */
        public function getGeneralHtmlItemsDescription($model)
        {
            $data = $model->items->pluck('description')->toArray();
            $full_description = "";

            foreach ($data as $value)
            {
                $full_description .= "- {$value}<br>";
            }

            return $full_description;
        }

        /**
         *
         * Filtros where like general para buscar campos en listados
         *
         * @param  Builder $query
         * @param  string $column
         * @param  string $value
         * @return Builder
         */
        public function scopeGeneralWhereLikeColumn($query, $column, $value)
        {
            if(empty($value)) return $query;

            return $query->where($column, 'like', "%{$value}%");
        }

        /**
         *
         * Filtros OR where like general para buscar campos en listados
         *
         * @param  Builder $query
         * @param  string $column
         * @param  string $value
         * @return Builder
         */
        public function scopeGeneralOrWhereLikeColumn($query, $column, $value)
        {
            if(empty($value)) return $query;

            return $query->orWhere($column, 'like', "%{$value}%");
        }

    }

// ######## FIN MIGRACIÓN MONEDA VENEZUELA ########
