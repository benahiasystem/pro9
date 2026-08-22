<?php

    namespace App\Models\Tenant;

    use App\Models\Tenant\Catalogs\CurrencyType;
    use Hyn\Tenancy\Traits\UsesTenantConnection;
    use Illuminate\Database\Eloquent\Builder;
    use Illuminate\Database\Eloquent\Collection;
    use Illuminate\Database\Eloquent\Relations\BelongsTo;
    use Illuminate\Database\Eloquent\Relations\MorphMany;
    use Modules\Expense\Models\BankLoan;
    use Modules\Expense\Models\BankLoanItem;
    use Modules\Finance\Models\GlobalPayment;

    /**
     * Class App\Models\Tenant\BankAccount
     *
     * @property int                             $id
     * @property int                             $bank_id
     * @property string                          $description
     * @property string                          $number
     * @property string|null                     $cci
     * @property string                          $currency_type_id
     * @property int                             $status
     * @property float                           $initial_balance
     * @property bool                            $show_in_documents
     * @property Bank                            $bank
     * @property CurrencyType                    $currency_type
     * @mixin ModelTenant
     * @property-read Collection|GlobalPayment[] $global_destination
     * @property-read int|null                   $global_destination_count
     * @package App\Models\Tenant
     * @method static Builder|BankAccount newModelQuery()
     * @method static Builder|BankAccount newQuery()
     * @method static Builder|BankAccount printShowInDocuments()
     * @method static Builder|BankAccount query()
     * @method static Builder|BankAccount selectIdDescription()
     */
    class BankAccount extends ModelTenant
    {
        use UsesTenantConnection;

        public $timestamps = false;
        protected $casts = [
            'bank_id' => 'int',
            'status' => 'int',
            'initial_balance' => 'float',
            'show_in_documents' => 'bool'
        ];

        protected $fillable = [
            'bank_id',
            'description',
            'number',
            'currency_type_id',
            'cci',
            'status',
            'initial_balance',
            'show_in_documents',
            'establishment_id',
        ];

        /**
         * @param Builder $query
         *
         * @return Builder
         */
        public function scopeSelectIdDescription($query)
        {
            $query->select(
                'id',
                'description'
            )->orderBy('description');
            return $query;
        }

        /**
         * @return BelongsTo
         */
        public function establishment()
        {
            return $this->belongsTo(Establishment::class);
        }

        /**
         * @return BelongsTo
         */
        public function bank()
        {
            return $this->belongsTo(Bank::class);
        }

        /**
         * @return BelongsTo
         */
        public function currency_type()
        {
            return $this->belongsTo(CurrencyType::class, 'currency_type_id');
        }

        /**
         * @return MorphMany
         */
        public function global_destination()
        {
            return $this->morphMany(GlobalPayment::class, 'destination')->with(['payment']);
        }

        /**
         * @param $value
         *
         * @return bool
         */
        public function getShowInDocumentsAttribute($value)
        {
            return $value ? true : false;
        }

        /**
         * Devuelve las cuentas bancarias que esten activas (status 1) y
         * que deban imprimirse en los documentos (show_in_documents 1)
         *
         * @return Builder
         */
        public function scopePrintShowInDocuments($query)
        {
            return $query->where('status', 1)->where('show_in_documents', 1);
        }

        /**
         * @return \Illuminate\Database\Eloquent\Relations\HasMany
         */
        public function bank_loan()
        {
            return $this->hasMany(BankLoan::class);
        }

        /**
         * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
         */
        public function bank_loan_items(){
            return $this->hasManyThrough(
                BankLoanItem::class,
                BankLoan::class,
                'bank_account_id',
                'id'

            )->whereIn('bank_loans.state_type_id', ['01', '03', '05', '07', '13']);
        }

        
        /**
         * 
         * Se agrega scope polimorfico para filtrar destino en global payment
         *
         * @param  Builder $query
         * @return Builder
         */
        public function scopeWithBankIfExist($query)
        {
            return $query->with(['bank']);
        }


        /**
         *
         * Filtro de busqueda para api (app)
         *
         * @param  Builder $query
         * @param  string|null $input
         * @return Builder
         */
        public function scopeWhereFilterRecordsApi($query, $input)
        {
            if($input === null || $input === '') return $query;

            return $query->where(function($q) use($input){
                $q->where('description', 'like', "%{$input}%")
                    ->orWhere('number', 'like', "%{$input}%")
                    ->orWhere('cci', 'like', "%{$input}%")
                    ->orWhereHas('bank', function($b) use($input){
                        $b->where('description', 'like', "%{$input}%");
                    });
            });
        }


        /**
         *
         * Obtener datos para api (app)
         *
         * @return array
         */
        public function getApiRowResource()
        {
            return [
                'id' => $this->id,
                'bank_id' => $this->bank_id,
                'bank_description' => optional($this->bank)->description,
                'description' => $this->description,
                'number' => $this->number,
                'cci' => $this->cci,
                'currency_type_id' => $this->currency_type_id,
                'currency_type_description' => optional($this->currency_type)->description,
                'status' => (int) $this->status,
                'initial_balance' => (float) $this->initial_balance,
                'show_in_documents' => (bool) $this->show_in_documents,
                'establishment_id' => $this->establishment_id,
                'establishment_description' => optional($this->establishment)->description,
            ];
        }


    }
