<?php
namespace App\Http\Controllers\Tenant;
 
use App\Http\Resources\Tenant\DocumentCollection;
use App\Http\Resources\Tenant\DocumentResource; 
use App\Models\Tenant\Document; 
use Illuminate\Http\Request; 
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\Controller;
use App\Traits\OfflineTrait;

class ContingencyController extends Controller
{
    use OfflineTrait;

    public function __construct()
    {
        // $this->middleware('input.request:document,web', ['only' => ['store']]);
    }

    public function index()
    {
        $is_client = $this->getIsClient();

        return view('tenant.contingencies.index', compact('is_client'));
    }

    public function columns()
    {
        return [
            'number' => 'Número',
            'date_of_issue' => 'Fecha de emisión'
        ];
    }

    public function records(Request $request)
    {
        // ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
        $column = $request->input('column', 'number');
        if (!is_string($column) || !array_key_exists($column, $this->columns())) {
            throw \Illuminate\Validation\ValidationException::withMessages(['column'=>'Seleccione un filtro válido.']);
        }
        $records = Document::where('documents.'.$column, 'like', '%'.$request->input('value', '').'%')
            ->whereExists(function ($query) {
                $query->selectRaw('1')->from('series as contingency_series')
                    ->where('contingency_series.contingency', true)
                    ->whereColumn('contingency_series.number', 'documents.series')
                    ->whereColumn('contingency_series.establishment_id', 'documents.establishment_id')
                    ->whereColumn('contingency_series.document_type_id', 'documents.document_type_id');
            })->whereTypeUser()->latest();
        // ######## FIN NUMERACIÓN FISCAL VENEZUELA ########

        return new DocumentCollection($records->paginate(config('tenant.items_per_page')));
    } 
 
    public function create()
    {
        $is_contingency = 1;

        return view('tenant.documents.form', compact('is_contingency'));

    } 
      
}
