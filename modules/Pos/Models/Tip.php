<?php
// ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########

namespace Modules\Pos\Models;
 
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Tenant\{
    ModelTenant,
    FiscalEnvironment,
    Document,
    SaleNote,
};
use Carbon\Carbon;


/**
 * 
 * El registro y actualización se realizan mediante TipServiceProvider
 * asociado a los eventos de los modelos relacionados
 *  
 */
class Tip extends ModelTenant
{
    
    protected $fillable = [
        'fiscal_environment',
        'date',  
        'origin_date_of_issue',  
        'origin_id',  
        'origin_type',  
        'worker_full_name',  
        'total',  
    ];
  

    protected $casts = [
        'origin_date_of_issue' => 'date',
    ];


    /**
     * @return MorphTo
     */
    public function origin()
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo
     */
    public function fiscal_environment_type()
    {
        return $this->belongsTo(FiscalEnvironment::class, 'fiscal_environment');
    }

    public function getDocumentTypeDescriptionAttribute()
    {
        return $this->origin_type === Document::class ? $this->origin->document_type->description ?? null : 'NOTA DE VENTA';
    } 

    public function getDocumentNumberFullAttribute()
    {
        return optional($this->origin)->number_full;
    } 

    /**
     * 
     * Usado para mostrar data en vista y formatos pdf/excel
     *
     * @return array
     */
    public function getRowResource()
    {
        return [

            'fiscal_environment' => $this->fiscal_environment,
            'date' => $this->date,  
            'origin_date_of_issue' => $this->origin_date_of_issue->format('Y-m-d'),  
            'origin_id' => $this->origin_id,  
            'worker_full_name' => $this->worker_full_name,  
            'total' => $this->total, 
            'document_type_description' => $this->document_type_description, 
            'document_number_full' => $this->document_number_full, 
            'state_type_description' => $this->origin->state_type->description ?? null, 
        ];
    }

}
// ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
