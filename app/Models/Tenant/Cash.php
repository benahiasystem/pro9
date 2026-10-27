<?php
// ######## INICIO TASAS OCHO DECIMALES: CONVERSIONES EXACTAS ########
// ######## FIN TASAS OCHO DECIMALES: CONVERSIONES EXACTAS ########
// ######## INICIO MIGRACIÓN MONEDA VENEZUELA ########

namespace App\Models\Tenant;

use Modules\Finance\Models\GlobalPayment;
use Modules\Pos\Models\CashTransaction;

/**
 * App\Models\Tenant\Cash
 *
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Tenant\CashDocument[] $cash_documents
 * @property-read int|null $cash_documents_count
 * @property-read CashTransaction|null $cash_transaction
 * @property-read mixed $currency_type_id
 * @property-read mixed $number_full
 * @property-read \Illuminate\Database\Eloquent\Collection|GlobalPayment[] $global_destination
 * @property-read int|null $global_destination_count
 * @property-read \Illuminate\Database\Eloquent\Collection|GlobalPayment[] $global_payments
 * @property-read int|null $global_payments_count
 * @property-read \App\Models\Tenant\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|Cash newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Cash newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Cash query()
 * @method static \Illuminate\Database\Eloquent\Builder|Cash whereTypeUser()
 * @mixin \Eloquent
 */
class Cash extends ModelTenant
{
    // protected $with = ['cash_documents'];

    protected $table = 'cash';

    protected $fillable = [
        'user_id',
        'date_opening',
        'time_opening',
        'date_closed',
        'time_closed',
        'beginning_balance',
        'final_balance',
        'income',
        'state',
        'reference_number',
        'apply_restaurant'
    ];


    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function cash_documents()
    {
        return $this->hasMany(CashDocument::class);
    }

    /**
     * @param $query
     *
     * @return null
     */
    public function scopeWhereTypeUser($query)
    {
        /** @var \App\Models\Tenant\User $user */
        $user = auth()->user();
        return ($user->type === 'seller') ? $query->where('user_id', $user->id) : null;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function global_destination()
    {
        return $this->morphMany(GlobalPayment::class, 'destination');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function global_payments()
    {
        return $this->morphToMany(GlobalPayment::class, 'destination');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function cash_transaction()
    {
        return $this->hasOne(CashTransaction::class);
    }

    /**
     * @return string
     */
    public function getCurrencyTypeIdAttribute()
    {
        return 'VES';
    }

    /**
     * @return string
     */
    public function getNumberFullAttribute()
    {

        if($this->cash_transaction){
            return "{$this->cash_transaction->description} - Caja chica POS".($this->reference_number ? ' N° '.$this->reference_number:'');
        }

        return '-';

    }

    public function scopeWhereActive($query)
    {
        return $query->where([
            ['user_id', auth()->user()->id],
            ['state', true],
        ]);
    }

    public function cash_documents_credit()
    {
        return $this->hasMany(CashDocumentCredit::class);
    }


    /**
     *
     * Obtener total de ingresos por tipo de documento
     *
     * @return array
     */
    public function getTotalsIncomeSummary()
    {

        $document_total_payments = $this->cash_documents()
                            ->whereHas('document')
                            ->get()
                            ->sum(function($row){
                                return $row->document->getTotalAllPayments();
                            });


        $sale_note_total_payments = $this->cash_documents()
                            ->whereHas('sale_note')
                            ->get()
                            ->sum(function($row){
                                return $row->sale_note->getTotalAllPayments();
                            });

        return [
            'document_total_payments' => $this->generalApplyNumberFormat($document_total_payments),
            'sale_note_total_payments' => $this->generalApplyNumberFormat($sale_note_total_payments),
        ];

    }


    /**
     *
     * Obtener comprobantes y notas de venta ordenados para reporte ingresos en caja
     *
     * @return array
     */
    public function getIncomePaymentsData()
    {

        $documents = $this->cash_documents()
                        ->join('documents', 'documents.id', '=', 'cash_documents.document_id')
                        ->orderBy('documents.document_type_id')
                        ->orderBy('documents.created_at')
                        ->get();

        $sale_notes = $this->cash_documents()
                            ->join('sale_notes', 'sale_notes.id', '=', 'cash_documents.sale_note_id')
                            ->orderBy('sale_notes.created_at')
                            ->get();

        return [
            'documents' => $documents,
            'sale_notes' => $sale_notes,
        ];

    }


    /**
     *
     * Filtrar cajas del usuario que realiza la petición
     *
     * Usado en:
     * caja - app
     *
     * @param  Builder $query
     * @param  string $input
     * @return Builder
     */
    public function scopeWhereFilterRecordsApi($query, $input)
    {

        return $query->where(function($q) use($input){
                        $q->where('income', 'like', "%{$input}%" )
                            ->orWhere('reference_number','like', "%{$input}%");
                    })
                    ->where('user_id', auth()->id())
                    ->latest();
    }


    /**
     *
     * Obtener datos para api (app)
     *
     * @return array
     */
    public function getApiRowResource()
    {
        // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
        $balances=\App\Services\Payments\CashLedger::report($this)['balances_by_currency'];
        $net=\App\Support\Exact\Decimal::represent(\App\Support\Exact\Decimal::readRequired($balances['VES'],'income')->minus(\App\Support\Exact\Decimal::readRequired($balances['VES'],'egress')));
        return ['id'=>$this->id,'user_id'=>$this->user_id,'user_name'=>$this->user->name,'date_opening'=>$this->date_opening,'time_opening'=>$this->time_opening,
            'opening'=>"{$this->date_opening} {$this->time_opening}",'date_closed'=>$this->date_closed,'time_closed'=>$this->time_closed,
            'closed'=>$this->state ? null : "{$this->date_closed} {$this->time_closed}",'report_version'=>2,'balances_by_currency'=>$balances,
            'beginning_balance'=>$balances['VES']['beginning_balance'],'final_balance'=>$balances['VES']['final_balance'],'income'=>$net,
            'current_income'=>$net,'current_final_balance'=>$balances['VES']['final_balance'],'state'=>(bool)$this->state,
            'state_description'=>$this->state_description,'reference_number'=>$this->reference_number];
        // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
    }


    /**
     *
     * Calcular montos actuales de la caja (ingresos y saldo final) al vuelo.
     *
     * Replica la lógica de cierre (CashController@close) pero sin persistir,
     * usando la fecha/hora actual como tope cuando la caja sigue abierta.
     * Se usa para exponer los montos en la app mientras la caja está aperturada.
     *
     * @return array{income: float, final_balance: float}
     */
    public function getCurrentTotals()
    {
        // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
        $balances=\App\Services\Payments\CashLedger::report($this)['balances_by_currency'];
        return ['income'=>\App\Support\Exact\Decimal::represent(\App\Support\Exact\Decimal::readRequired($balances['VES'],'income')->minus(\App\Support\Exact\Decimal::readRequired($balances['VES'],'egress'))),'final_balance'=>$balances['VES']['final_balance'],'balances_by_currency'=>$balances];
        // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
    }

    public function noteRefunds()
    {
        return DocumentNoteRefund::whereNull('reversed_at')->whereHas('global_payment',fn ($q)=>$q->where('destination_type',self::class)->where('destination_id',$this->id));
    }

    public function noteRefundTotal(): float
    {
        return $this->noteRefunds()->get()->sum(fn ($r)=>\App\Services\Fiscal\FiscalAmounts::convert($r->original_amount,$r->currency_type_id,'VES',$r->exchange_rate));
    }


    /**
     *
     * @return string
     */
    public function getStateDescriptionAttribute()
    {
        return ($this->state) ? 'Aperturada':'Cerrada';
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
        return $query;
    }


    /**
     *
     * Obtener relaciones necesarias o aplicar filtros para reporte pagos - finanzas
     *
     * @param  Builder $query
     * @return Builder
     */
    public function scopeFilterRelationsGlobalPayment($query)
    {
        return $query->with([
                        'cash_transaction'
                    ]);
    }


    /**
     *
     * Filtro para reporte general de caja v2
     *
     * @param  Builder $query
     * @return Builder
     */
    public function scopeFilterDataGeneralCashReport($query)
    {
        return $query->with([
            'global_destination' => function($query){
                return $query->generalCashReportWithPayments()->latest();
            }
        ]);
    }


    /**
     *
     * Filtro para reporte de pagos en efectivo con destino caja
     *
     * @param  Builder $query
     * @return Builder
     */
    public function scopeFilterDataCashPaymentReport($query)
    {
        return $query->with([
            'global_destination' => function($query){
                return $query->getDataCashPaymentReport()->latest();
            }
        ]);
    }


    /**
     *
     * Filtro para reporte de ingresos con destino caja - condicion de pago al contado
     *
     * @param  Builder $query
     * @return Builder
     */
    public function scopeFilterDataIncomeSummaryPayment($query)
    {
        return $query->with([
            'global_destination' => function($query){
                return $query->getDataIncomeSummaryPayment();
            }
        ]);
    }

}

// ######## FIN MIGRACIÓN MONEDA VENEZUELA ########
