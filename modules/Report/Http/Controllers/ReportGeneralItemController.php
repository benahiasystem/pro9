<?php

namespace Modules\Report\Http\Controllers;

use App\Models\Tenant\Catalogs\DocumentType;
use App\Http\Controllers\Controller;
use App\Models\System\Client;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Modules\Report\Exports\GeneralItemExport;
use Illuminate\Http\Request;
use App\Models\Tenant\PurchaseItem;
use App\Models\Tenant\DocumentItem;
use App\Models\Tenant\SaleNoteItem;
use App\Models\Tenant\Company;
use App\Traits\JobReportTrait;
use Hyn\Tenancy\Models\Hostname;
use Illuminate\Support\Facades\Log;
use Modules\Report\Http\Resources\GeneralItemCollection;
use Modules\Report\Jobs\ProcessReportGeneralItems;
use Modules\Report\Traits\ReportTrait;


class ReportGeneralItemController extends Controller
{
    use ReportTrait, JobReportTrait;

    public function __construct()
    {
    }

    public function filter() {

        $customers = $this->getPersons('customers');
        $suppliers = $this->getPersons('suppliers');
        $items = $this->getItems('items');
        $brands = $this->getBrands();
        $web_platforms = $this->getWebPlatforms();
        $document_types = DocumentType::whereIn('id', ['01', '03', '07', '80', 'GU75', 'NE76'])->get();
        $categories = $this->getCategories();
        $users = $this->getUsers();

        return compact('document_types', 'suppliers', 'customers', 'items','web_platforms', 'brands', 'categories', 'users');
    }


    public function index(Request $request) 
    {
        $apply_conversion_to_pen = $this->applyConversiontoPen($request);
        
        return view('report::general_items.index', compact('apply_conversion_to_pen'));
    }


    public function records(Request $request)
    {

        $records = $this->getRecordsItems($request->all())->latest('id');

        // GeneralItemCollection recorre por fila el documento, el producto y su
        // plataforma: sin precarga cada acceso era una consulta, y Document e
        // Item arrastran muchas relaciones por su $with. Asi van una vez por pagina.
        $relation = [
            DocumentItem::class => 'document.document_type',
            PurchaseItem::class => 'purchase',
            SaleNoteItem::class => 'sale_note',
        ][get_class($records->getModel())];

        $records->with([
            $relation,
            'relation_item.brand',
            'relation_item.web_platform',
            'relation_item.sets.relation_item',
        ]);
        
        return new GeneralItemCollection($records->paginate(config('tenant.items_per_page')));
    }


    /**
     * document_type_id llega de varias formas: array desde el filtro multiple,
     * texto suelto desde la API o clientes viejos, y '' / 'null' / 'undefined'
     * cuando el select se vacia. Siempre devuelve una lista limpia.
     */
    public static function normalizeDocumentTypeIds($value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        return collect((array) $value)
            ->map(function ($id) {
                return trim((string) $id);
            })
            ->reject(function ($id) {
                return in_array($id, ['', 'null', 'undefined'], true);
            })
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Es el reporte de notas de venta solo si 80 es lo unico elegido: vive en
     * otra tabla y no se mezcla con comprobantes. Las vistas, el job y la API
     * solo necesitan saber esto, no la lista completa.
     */
    public static function isSaleNoteReport($value): bool
    {
        return self::normalizeDocumentTypeIds($value) === ['80'];
    }

    /**
     * Tipos que se consultan cuando no se elige ninguno: los que ofrece
     * filter(), sin la nota de venta. Compras tampoco lleva nota de credito,
     * igual que el filtro del componente.
     */
    private static function defaultDocumentTypeIds(bool $is_sale): array
    {
        $ids = ['01', '03', '07', 'GU75', 'NE76'];

        return $is_sale ? $ids : array_values(array_diff($ids, ['07']));
    }

    public function getRecordsItems($request){

        $d_start = null;
        $d_end = null;
        if (isset($request['period'])) {
            $data_of_period = $this->getDataOfPeriod($request);
            $d_start = $data_of_period['d_start'];
            $d_end = $data_of_period['d_end'];
        } else {
            $d_start = $request['date_start'];
            $d_end = $request['date_end'];
        }
        $data_type = $this->getDataType($request);

        $document_type_ids = self::normalizeDocumentTypeIds($request['document_type_id'] ?? null);

        $person_id = isset($request['person_id']) ? $request['person_id'] : null;
        $type_person = isset($request['type_person']) ? $request['type_person'] : null;
        $item_id = isset($request['item_id']) ? $request['item_id'] : null;
        $brand_id = isset($request['brand_id']) ? $request['brand_id'] : null;
        $category_id = isset($request['category_id']) ? $request['category_id'] : null;

        $user_id = $request['user_id'] ?? null;
        if ($user_id === '' || $user_id === 'null' || $user_id === 'all' || $user_id === 'TODOS') {
            $user_id = null;
        }
        $user_type = $request['user_type'] ?? null;
        if ($user_type === '' || $user_type === 'null' || $user_type === 'TODOS') {
            $user_type = null;
        }
        $web_platform_id = isset($request['web_platform_id']) ? $request['web_platform_id'] : null;

        $records = $this->dataItems($d_start, $d_end, $document_type_ids, $data_type, $person_id, $type_person, $item_id, $web_platform_id, $brand_id, $category_id, $user_id, $user_type);

        return $records;

    }


    /**
     * @param $date_start
     * @param $date_end
     * @param string[] $document_type_ids
     * @param $data_type
     * @param $person_id
     * @param $type_person
     * @param $item_id
     * @param $web_platform_id
     * @param $brand_id
     * @param $category_id
     * @param $user_id
     * @param $user_type
     *
     * @return \App\Models\Tenant\SaleNoteItem|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     */
    private function dataItems($date_start, $date_end, $document_type_ids, $data_type, $person_id, $type_person, $item_id, $web_platform_id, $brand_id, $category_id, $user_id, $user_type)
    {
        /* columna state_type_id */
        $documents_excluded = [
            '11', // Documentos anulados
            '09' // Documentos rechazados
        ];
        if (self::isSaleNoteReport($document_type_ids)) {
            $relation = 'sale_note';

            $data = SaleNoteItem::whereHas('sale_note', function($query) use($date_start, $date_end, $user_id, $documents_excluded){
                $query
                ->whereBetween('date_of_issue', [$date_start, $date_end])
                ->latest()
                ->whereTypeUser();
                if(!empty($user_id)){
                    $query->where('user_id',$user_id);
                }
                $query->whereNotIn('state_type_id', $documents_excluded);
            });

        } else {

            $model = $data_type['model'];
            $relation = $data_type['relation'];

            // Sin seleccion se consultan todos los comprobantes del filtro, no
            // solo 01 y 03. La nota de venta (80) solo se atiende sola en la
            // rama de arriba: mezclada con otros tipos se descarta aqui.
            $document_types = array_values(array_diff($document_type_ids, ['80']));

            if (empty($document_types)) {
                $document_types = self::defaultDocumentTypeIds($model === DocumentItem::class);
            }

            $data = $model::whereHas($relation, function ($query) use ($date_start, $date_end, $document_types, $model,$documents_excluded) {
                $query
                    ->whereBetween('date_of_issue', [$date_start, $date_end])
                    ->whereIn('document_type_id', $document_types)
                    ->latest()
                    ->whereTypeUser();
                if ($model == 'App\Models\Tenant\DocumentItem') {
                    $query->whereNotIn('state_type_id', $documents_excluded);
                }
            });
            if ($user_id && $user_type === 'CREADOR') {
                $data = $data->whereHas($relation.'.user', function($query) use($user_id){
                    $query->where('user_id', $user_id);
                });
            }
			if ($user_id && $user_type === 'VENDEDOR') {
				$data = $data->whereHas($relation . '.seller', function ($query) use ($user_id) {
					$query->where('seller_id', $user_id);
				});
			}
        }


        if($person_id && $type_person){

            $column = ($type_person == 'customers') ? 'customer_id':'supplier_id';

            $data =  $data->whereHas($relation, function($query) use($column, $person_id){
                                $query->where($column, $person_id);
                            });

        }

        if($item_id){
            $data =  $data->where('item_id', $item_id);
        }

        if($web_platform_id || $brand_id || $category_id){
            $data = $data->whereHas('relation_item', function($q) use($web_platform_id, $brand_id, $category_id){
				if ($web_platform_id) {
					$q->where('web_platform_id', $web_platform_id);
                }
				if ($brand_id) {
					$q->where('brand_id', $brand_id);
				}
                if ($category_id) {
					$q->where('category_id', $category_id);
				}
            });
        }

        return $data;

    }


    private function getDataType($request)
    {

        if($request['type'] == 'sale'){

            $data['model'] = DocumentItem::class;
            $data['relation'] = 'document';

        }else{

            $data['model'] = PurchaseItem::class;
            $data['relation'] = 'purchase';

        }

        return $data;
    }


    public function report(Request $request) 
    {
        $host = $request->getHost();
        $curnent_user = auth()->user();
        $user_id = $request->user_id ? $request->user_id :  $curnent_user->id;

        $record_chunk = isset($request->record_chunk) ? $request->record_chunk : null;
        $total = $this->getRecordsItems($request->all())->count();
        $tray = $this->createDownloadTray($user_id, 'Reporte', !is_null($record_chunk) ?$request->format : 'zip', 'Reporte general de venta/productos');
        $trayId = $tray->id;
        $hostname = Hostname::where('fqdn',$host)->first();
        if(empty($hostname)) {
            $company = Company::active();
            $number = $company->number;
            $client = Client::where('number', $number)->first();
            $website_id = $client->hostname->website_id;
        }else{
            $website_id = $hostname->website_id;
        }


        if (!is_null($record_chunk) && $request->format == 'pdf') {
            $batches = [];
            for ($i=0; $i < ceil($total / $record_chunk) ; $i++) { 
                $offset = $i * $record_chunk;
                $batches[] = new ProcessReportGeneralItems(
                    $trayId,
                    $website_id,
                    $request->all(),
                    $user_id,
                    $offset,
                    $record_chunk,
                    true
                );
            }

            $r_all = $request->all();
            $batch = Bus::batch($batches)
                ->name('Report General Items')
                ->catch(function (Batch $batch, $exception) use($trayId) {
                    Log::error("Error de batch Report General Items: ", [
                        "error" => $exception->getMessage()
                    ]);
                    $this->jobBatchFailed($batch->id, $trayId);
                })
                ->then(function(Batch $batch) use($trayId, $website_id, $r_all) {
                    $filename = "";
                    return $this->jobBatchFinished($batch, $trayId, $website_id, $filename, "pdf")
                ;})
                ->dispatch();


        } else {
            ProcessReportGeneralItems::dispatch($trayId, $website_id, $request->all(), $user_id, 0, $total, false);
        }

        return  [
            'success' => true,
            'message' => 'El reporte se esta procesando; puede ver el proceso en bandeja de descargas.'
        ];

    }
}
