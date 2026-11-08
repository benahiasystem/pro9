<?php
// ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########

namespace Modules\Report\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Catalogs\DocumentType;
use App\Models\Tenant\Company;
use App\Models\Tenant\Document;
use App\Models\Tenant\Establishment;
use App\Models\Tenant\SaleNote;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Modules\Item\Models\Category;
use Modules\Report\Exports\StatusClientExport;
use Modules\Report\Http\Resources\DocumentCollection;
use Modules\Report\Http\Resources\SaleNoteCollection;
use Modules\Report\Traits\ReportTrait;

use Modules\Report\Http\Resources\StateAccountCollection;

use DB;


class ReportStateAccountController extends Controller
{
    use ReportTrait;



    public function filter() {

        $document_types = DocumentType::whereIn('id',[
                '01',// factura
                //'07', // nota de credito
                //'08',// nota de debito
                '80', // nota de venta
            ])->get();

        $persons = $this->getPersons('customers');
        $sellers = $this->getSellers();

        $establishments = Establishment::all()->transform(function($row) {
            return [
                'id' => $row->id,
                'name' => $row->description
            ];
        });
        $users = $this->getUsers();

        return compact('document_types','establishments','persons', 'sellers', 'users');
    }


    public function index() {
        return view('report::state_account.index');
    }

    public function records(Request $request)
    {
        $request->validate(['page'=>'sometimes|integer|min:1']);
        return \App\Support\Exact\ReadSnapshot::run(DB::connection('tenant'), function () use ($request) {
            $type = str_replace('"', '', (string) $request->input('document_type_id'));
            $queries = [];
            if ($type !== '80') {
                $queries[] = $this->getRecords($request->all(), Document::class)->reorder()
                    ->selectRaw("id, date_of_issue, 'document' as source_type")->toBase();
            }
            if ($type === '' || $type === 'null' || $type === '80') {
                $queries[] = $this->getRecords($request->all(), SaleNote::class)->reorder()
                    ->selectRaw("id, date_of_issue, 'sale_note' as source_type")->toBase();
            }
            $query = array_shift($queries);
            foreach ($queries as $other) $query->unionAll($other);
            $page = DB::connection('tenant')->query()->fromSub($query, 'account_rows')
                ->orderByDesc('date_of_issue')->orderByDesc('id')->orderBy('source_type')
                ->paginate(max(1, min(500, (int) config('tenant.items_per_page', 50))), ['*'], 'page', (int) $request->input('page', 1));
            $keys = $page->getCollection();
            $documents = Document::whereIn('id', $keys->where('source_type', 'document')->pluck('id'))->get()->keyBy('id');
            $notes = SaleNote::whereIn('id', $keys->where('source_type', 'sale_note')->pluck('id'))->get()->keyBy('id');
            $page->setCollection($keys->map(fn ($key) => $key->source_type === 'document'
                ? $documents->get($key->id) : $notes->get($key->id))->filter()->values());
            // Resolve the resource while the snapshot is open, including all balances.
            return (new StateAccountCollection($page))->response($request);
        });
    }

    public function excel(Request $request) {

        return \App\Support\Exact\ReadSnapshot::run(DB::connection('tenant'), function () use ($request) {
        $company = Company::first();
        $establishment = ($request->establishment_id) ? Establishment::findOrFail($request->establishment_id) : auth()->user()->establishment;

        $type = str_replace('"', '', (string) $request->input('document_type_id'));
        $records = collect();
        if ($type !== '80') $records = $records->concat($this->getRecords($request->all(), Document::class)->get());
        if ($type === '' || $type === 'null' || $type === '80') {
            $records = $records->concat($this->getRecords($request->all(), SaleNote::class)->get());
        }

        $filters = $request->all();

        //get categories
        $categories = [];
        $categories_services = [];

        if($request->include_categories == "true"){
            $categories = $this->getCategories($records, false);
            $categories_services = $this->getCategories($records, true);
        }

        $documentExport = new StatusClientExport();
        $documentExport
            ->records($records)
            ->company($company)
            ->establishment($establishment)
            ->filters($filters)
            ->categories($categories)
            ->categories_services($categories_services);
         // return $documentExport->view();
        return $documentExport->download('Reporte_estado_de_cuenta'.Carbon::now().'.xlsx');
        });
    }


    public function getCategories($records, $is_service) {

        $aux_categories = collect([]);

        foreach ($records as $document) {

            $id_categories = $document->items->filter(function($row) use($is_service){
                return (($is_service) ? (!is_null(optional($row->relation_item)->category_id) && $row->item->unit_type_id === 'SERV') : !is_null(optional($row->relation_item)->category_id)) ;
            })->pluck('relation_item.category_id');

            foreach ($id_categories as $value) {
                $aux_categories->push($value);
            }
        }

        return Category::whereIn('id', $aux_categories->unique()->toArray())->get();

    }


}
// ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
