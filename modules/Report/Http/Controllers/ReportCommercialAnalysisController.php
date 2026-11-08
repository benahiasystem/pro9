<?php

namespace Modules\Report\Http\Controllers;

use App\Models\Tenant\Catalogs\DocumentType;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Modules\Report\Exports\CommercialAnalysisExport;
use Illuminate\Http\Request;
use Modules\Report\Traits\ReportTrait;
use App\Models\Tenant\Establishment;
use App\Models\Tenant\Document;
use App\Models\Tenant\Person;
use App\Models\Tenant\PersonType;
use Modules\Item\Models\Category;
use App\Models\Tenant\Company;
use Carbon\Carbon;
use Modules\Report\Http\Resources\CommercialAnalysisCollection;

class ReportCommercialAnalysisController extends Controller
{
   
    public function columns()
    {
        $categories = Category::all()->pluck('name')->toArray();
        return compact('categories');
            
    }
    public function filter() {

        $document_types = [];

        $establishments = Establishment::all()->transform(function($row) {
            return [
                'id' => $row->id,
                'name' => $row->description
            ];
        });

        $categories = Category::all();
        
        return compact('document_types','establishments', 'categories');
    }
      

    public function index() {
       
        return view('report::commercial_analysis.index');
    }
   
    public function records(Request $request)
    {
        return \App\Support\Exact\ReadSnapshot::run((new Person)->getConnection(), function () use ($request) {
            $records = $this->getRecords($request->all(), Person::class);
            return (new CommercialAnalysisCollection($records->paginate(config('tenant.items_per_page'))))->response($request);
        });
    }

    
    public function getRecords($request, $model){
 
 
        $records = $this->data($request, $model);

        return $records;

    }


    private function data($request, $model)
    {
        $number = $request['number'];
        $person_type_id = $request['person_type_id'];
        $category_id = $request['category_id'];

        $data = $model::whereType('customers')
            ->where('number', 'like', '%' . $number . '%');
            
        if ($person_type_id !== 'all') {
            $data->where('person_type_id', 'like', '%' . $person_type_id . '%');
        }

        if ($category_id) {
            $data->whereHas('documents', function ($documents) use ($category_id) {
                $documents->where('document_type_id','01')->whereNotIn('state_type_id',['09','11'])
                    ->whereHas('items.m_item.category', fn ($category) => $category->where('id',$category_id));
            });
        }

        $data->latest();

        return $data;
    }
  

    public function excel(Request $request) {
        return \App\Support\Exact\ReadSnapshot::run((new Person)->getConnection(), function () use ($request) {
        $company = Company::first();
        $establishment = ($request->establishment_id) ? Establishment::findOrFail($request->establishment_id) : auth()->user()->establishment; 
        
        $records = $this->getRecords($request->all(), Person::class)->get();
        $records->loadMissing(['documents.items.relation_item.category','documents.note','country','department','province','district','identity_document_type']);

        // dd($records);

        return (new CommercialAnalysisExport)
                ->records($records)
                ->company($company)
                ->categories(Category::all()->pluck('name')->toArray())
                ->download('Reporte_Analisis_comercial_'.Carbon::now().'.xlsx');
        });
    }


    public function data_table()
    {
        
        // $customers = $this->table('customers'); 
        $person_types = PersonType::get();
        $categories = Category::get(); 

        return compact( 'person_types', 'categories');

    }

}
