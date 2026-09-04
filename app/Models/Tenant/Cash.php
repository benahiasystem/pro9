<?php

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

    /** Estados de comprobante que suman a la caja (catalogo 23 de SUNAT). */
    private const CURRENT_TOTALS_VALID_STATES = ['01', '03', '05', '07', '13'];

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
        return 'PEN';
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
        // Filtro obligatorio primero: es el unico que puede usar indice
        // (user_id tiene indice por la foreign key).
        $query->where('user_id', auth()->id());

        $input = trim((string) $input);

        // Sin busqueda no se arma el bloque de LIKE. Es el caso habitual del
        // listado y antes igual comparaba cadena por cadena en cada fila con
        // un '%%' que siempre daba verdadero.
        if ($input === '') {
            return $query;
        }

        return $query->where(function ($q) use ($input) {
            $q->where('reference_number', 'like', "%{$input}%");

            // income es decimal(12,4): el LIKE obligaba a convertir la columna a
            // texto fila por fila y anulaba su indice. Solo tiene sentido
            // compararlo cuando lo buscado es un numero.
            if (is_numeric($input)) {
                $q->orWhere('income', $input);
            }
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
        // Mientras la caja está abierta, income/final_balance persistidos están en 0
        // (solo se calculan al cerrar). Para la app calculamos los montos al vuelo.
        // Si está cerrada, reutilizamos lo persistido para no recalcular en cada fila.
        if ($this->state) {
            $current_totals = $this->getCurrentTotals();
            $current_income = $current_totals['income'];
            $current_final_balance = $current_totals['final_balance'];
        } else {
            $current_income = (float) $this->income;
            $current_final_balance = (float) $this->final_balance;
        }

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user_name' => $this->user->name,
            'date_opening' => $this->date_opening,
            'time_opening' => $this->time_opening,
            'opening' => "{$this->date_opening} {$this->time_opening}",
            'date_closed' => $this->date_closed,
            'time_closed' => $this->time_closed,
            'closed' => !$this->state ? "{$this->date_closed} {$this->time_closed}" : null,
            'beginning_balance' => (float) $this->beginning_balance,
            'final_balance' => (float) $this->final_balance,
            'income' => (float) $this->income,
            'current_income' => $current_income,
            'current_final_balance' => $current_final_balance,
            'state' => (bool) $this->state,
            'state_description' => $this->state_description,
            'reference_number' => $this->reference_number,
        ];
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
        $id = $this->id;
        $valid_states = self::CURRENT_TOTALS_VALID_STATES;

        // Cada bloque es un agregado sobre cash_documents. Antes esto era un
        // foreach que cargaba relacion por relacion: ~24 consultas por movimiento
        // (una caja con 5.000 movimientos superaba las 100.000 consultas).
        $final_balance = $this->sumSaleNotePayments($valid_states)
            + $this->sumDocumentPayments($valid_states)
            - $this->sumExpensePayments()
            - $this->sumCanceledPurchases($valid_states)
            + $this->sumQuotations();

        $final_balance += $this->sumFinanceIncomes($valid_states);

        return [
            'income' => round($final_balance, 2),
            'final_balance' => round($final_balance + $this->beginning_balance, 2),
        ];
    }


    /**
     * Base comun de los agregados: los movimientos de esta caja.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    private function currentTotalsBase()
    {
        return $this->getConnection()
                    ->table('cash_documents as cd')
                    ->where('cd.cash_id', $this->id);
    }


    /**
     * Importe en soles: si la moneda no es PEN se aplica el tipo de cambio.
     *
     * @param  string $amount
     * @param  string $currency
     * @param  string $rate
     * @return \Illuminate\Database\Query\Expression
     */
    private function amountInPen($amount, $currency, $rate)
    {
        return $this->getConnection()->raw(
            "COALESCE(SUM(CASE WHEN {$currency} = 'PEN' THEN {$amount} ELSE {$amount} * {$rate} END), 0)"
        );
    }


    /**
     * Pagos de notas de venta imputados a esta caja.
     *
     * @param  array $valid_states
     * @return float
     */
    private function sumSaleNotePayments(array $valid_states)
    {
        $id = $this->id;

        $row = $this->currentTotalsBase()
            ->join('sale_notes as sn', 'sn.id', '=', 'cd.sale_note_id')
            ->join('sale_note_payments as snp', 'snp.sale_note_id', '=', 'sn.id')
            ->whereIn('sn.state_type_id', $valid_states)
            // whereExists y no join: un pago con varias filas en
            // cash_document_payments debe contarse una sola vez.
            ->whereExists(function ($q) use ($id) {
                $q->selectRaw('1')->from('cash_document_payments as cdp')
                  ->whereColumn('cdp.sale_note_payment_id', 'snp.id')
                  ->where('cdp.cash_id', $id);
            })
            ->selectRaw($this->amountInPen('snp.payment', 'sn.currency_type_id', 'sn.exchange_rate_sale').' as total')
            ->first();

        return (float) ($row->total ?? 0);
    }


    /**
     * Pagos de comprobantes imputados a esta caja.
     *
     * Se excluyen los que tienen nota asociada, igual que hacia la version por
     * bucle: esos movimientos no suman (ver nota en la constante).
     *
     * @param  array $valid_states
     * @return float
     */
    private function sumDocumentPayments(array $valid_states)
    {
        $id = $this->id;

        $row = $this->currentTotalsBase()
            ->join('documents as d', 'd.id', '=', 'cd.document_id')
            ->join('document_payments as dp', 'dp.document_id', '=', 'd.id')
            ->whereIn('d.state_type_id', $valid_states)
            // La nota de venta tiene prioridad en la cadena de decision original.
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('sale_notes as sn2')->whereColumn('sn2.id', 'cd.sale_note_id');
            })
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('notes as n')->whereColumn('n.affected_document_id', 'd.id');
            })
            ->whereExists(function ($q) use ($id) {
                $q->selectRaw('1')->from('cash_document_payments as cdp')
                  ->whereColumn('cdp.document_payment_id', 'dp.id')
                  ->where('cdp.cash_id', $id);
            })
            ->selectRaw($this->amountInPen('dp.payment', 'd.currency_type_id', 'd.exchange_rate_sale').' as total')
            ->first();

        return (float) ($row->total ?? 0);
    }


    /**
     * Pagos de gastos de esta caja. Se restan del saldo.
     *
     * @return float
     */
    private function sumExpensePayments()
    {
        $row = $this->currentTotalsBase()
            ->join('expense_payments as ep', 'ep.id', '=', 'cd.expense_payment_id')
            ->join('expenses as e', 'e.id', '=', 'ep.expense_id')
            ->where('e.state_type_id', '05')
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('sale_notes as sn2')->whereColumn('sn2.id', 'cd.sale_note_id');
            })
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('documents as d2')->whereColumn('d2.id', 'cd.document_id');
            })
            ->selectRaw($this->amountInPen('ep.payment', 'e.currency_type_id', 'e.exchange_rate_sale').' as total')
            ->first();

        return (float) ($row->total ?? 0);
    }


    /**
     * Compras canceladas en su totalidad. Se restan del saldo.
     *
     * @param  array $valid_states
     * @return float
     */
    private function sumCanceledPurchases(array $valid_states)
    {
        $row = $this->currentTotalsBase()
            ->join('purchases as p', 'p.id', '=', 'cd.purchase_id')
            ->whereIn('p.state_type_id', $valid_states)
            ->where('p.total_canceled', 1)
            // Purchase usa SoftDeletes: la relacion del modelo excluia las borradas.
            ->whereNull('p.deleted_at')
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('sale_notes as sn2')->whereColumn('sn2.id', 'cd.sale_note_id');
            })
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('documents as d2')->whereColumn('d2.id', 'cd.document_id');
            })
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('expense_payments as ep2')->whereColumn('ep2.id', 'cd.expense_payment_id');
            })
            ->selectRaw($this->amountInPen('p.total', 'p.currency_type_id', 'p.exchange_rate_sale').' as total')
            ->first();

        return (float) ($row->total ?? 0);
    }


    /**
     * Cotizaciones que suman a caja: aceptadas, con pagos y sin cambios.
     * Equivale a Quotation::applyQuotationToCash().
     *
     * @return float
     */
    private function sumQuotations()
    {
        $row = $this->currentTotalsBase()
            ->join('quotations as q', 'q.id', '=', 'cd.quotation_id')
            ->whereIn('q.state_type_id', ['01', '05'])
            ->where(function ($q) {
                $q->where('q.changed', 0)->orWhereNull('q.changed');
            })
            ->whereExists(function ($q) {
                $q->selectRaw('1')->from('quotation_payments as qp')->whereColumn('qp.quotation_id', 'q.id');
            })
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('sale_notes as sn2')->whereColumn('sn2.id', 'cd.sale_note_id');
            })
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('documents as d2')->whereColumn('d2.id', 'cd.document_id');
            })
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('expense_payments as ep2')->whereColumn('ep2.id', 'cd.expense_payment_id');
            })
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('purchases as p2')->whereColumn('p2.id', 'cd.purchase_id');
            })
            ->selectRaw($this->amountInPen('q.total', 'q.currency_type_id', 'q.exchange_rate_sale').' as total')
            ->first();

        return (float) ($row->total ?? 0);
    }


    /**
     * Ingresos de finanzas del periodo de la caja.
     *
     * @param  array $valid_states
     * @return float
     */
    private function sumFinanceIncomes(array $valid_states)
    {
        // Si la caja sigue abierta, el tope es la fecha/hora actual.
        $date_closed = $this->date_closed ?: date('Y-m-d');
        $time_closed = $this->time_closed ?: date('H:i:s');

        return (float) \Modules\Finance\Models\Income::where('user_id', $this->user_id)
            ->whereTypeUser()
            ->whereBetween('date_of_issue', [$this->date_opening, $date_closed])
            ->whereBetween('time_of_issue', [$this->time_opening, $time_closed])
            ->whereIn('state_type_id', $valid_states)
            ->selectRaw($this->amountInPen('total', 'currency_type_id', 'exchange_rate_sale').' as total')
            ->value('total');
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
