<?php

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
use Modules\Report\Exports\DocumentExport;
use Modules\Report\Http\Resources\DocumentCollection;
use Modules\Report\Http\Resources\SaleNoteCollection;
use Modules\Report\Traits\ReportTrait;
use App\Http\Controllers\Tenant\EmailController;
use Modules\Report\Mail\DocumentEmail;
use Mpdf\Mpdf;
use Modules\Report\Jobs\ProcessDocumentReport;
use App\Models\Tenant\DownloadTray;
use App\Tenancy\Models\Hostname;
use App\Models\System\Client;

use Maatwebsite\Excel\Excel as BaseExcel;
use Maatwebsite\Excel\Facades\Excel;
use App\Traits\JobReportTrait;
use Modules\Report\Exports\DocumentExportStandard;
use App\Support\Exact\ReadSnapshot;


class ReportDocumentController extends Controller
{
    use ReportTrait, JobReportTrait;



    public function filter() {

        $document_types = DocumentType::whereIn('id',[
                '01',// factura
                '07', // nota de credito
                '08',// nota de debito
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
        return view('report::documents.index');
    }

    public function records(Request $request)
    {
        return ReadSnapshot::run((new Document)->getConnection(), function () use ($request) {
        $documentTypeId = "01";
        if ($request->has('document_type_id')) {
            $documentTypeId = str_replace('"', '', $request->document_type_id);
        }
        $documentType = DocumentType::find($documentTypeId);
        if (null === $documentType) {
            $documentType = new DocumentType();
        }

        $classType = $documentType->getCurrentRelatiomClass();

        $records = $this->getRecords($request->all(), $classType);

        if ($classType == SaleNote::class) {
            return (new SaleNoteCollection($records->paginate(config('tenant.items_per_page'))))->response($request);
        }
        return (new DocumentCollection($records->paginate(config('tenant.items_per_page'))))->response($request);
        });


    }


    public function pdf(Request $request)
    {
        return ReadSnapshot::run((new Document)->getConnection(), function () use ($request) {
        set_time_limit (1800); // Maximo 30 minutos
        $company = Company::first();
        $establishment = ($request->establishment_id) ? Establishment::findOrFail($request->establishment_id) : auth()->user()->establishment;
        $documentTypeId = "01";
        if ($request->has('document_type_id')) {
            $documentTypeId = str_replace('"', '', $request->document_type_id);
        }
        $documentType = DocumentType::find($documentTypeId);
        if (null === $documentType) {
            $documentType = new DocumentType();
        }

        $classType = $documentType->getCurrentRelatiomClass();
        $records = $this->getRecords($request->all(), $classType);
        $records= $records->get();

        $filters = $request->all();

        $pdf = PDF::loadView('report::documents.report_pdf_standard', compact("records", "company", "establishment", "filters"))
            ->setPaper('a4', 'landscape');

        $filename = 'Reporte_Ventas_'.date('YmdHis');

        return $pdf->download($filename.'.pdf');
        });
    }


    public function pdfSimple(Request $request) {
        return ReadSnapshot::run((new Document)->getConnection(), function () use ($request) {
        set_time_limit (1800); // Maximo 30 minutos
        $company = Company::first();
        $establishment = ($request->establishment_id) ? Establishment::findOrFail($request->establishment_id) : auth()->user()->establishment;
        $documentTypeId = "01";
        if ($request->has('document_type_id')) {
            $documentTypeId = str_replace('"', '', $request->document_type_id);
        }
        $documentType = DocumentType::find($documentTypeId);
        if (null === $documentType) {
            $documentType = new DocumentType();
        }

        $classType = $documentType->getCurrentRelatiomClass();
        $records = $this->getRecords($request->all(), $classType);
        $records= $records->get();

        $filters = $request->all();

        $pdf = PDF::loadView('report::documents.report_pdf_simple', compact("records", "company", "establishment", "filters"))
            ->setPaper('a4', 'landscape');

        $filename = 'Reporte_Ventas_Simple'.date('YmdHis');

        return $pdf->download($filename.'.pdf');
        });
    }


    public function excel(Request $request)
    {
        return ReadSnapshot::run((new Document)->getConnection(), function () use ($request) {
        $company = Company::first();
        $establishment = ($request->establishment_id) ? Establishment::findOrFail($request->establishment_id) : auth()->user()->establishment;

        $documentTypeId = "01";
        if ($request->has('document_type_id')) {
            $documentTypeId = str_replace('"', '', $request->document_type_id);
        }
        $documentType = DocumentType::find($documentTypeId);
        if (null === $documentType) {
            $documentType = new DocumentType();
        }

        $classType = $documentType->getCurrentRelatiomClass();
        $records = $this->getRecords($request->all(), $classType);
        $records= $records->get();
        $filters = $request->all();

        //get categories
        $categories = [];
        $categories_services = [];

        if($request->include_categories == "true"){
            $categories = $this->getCategories($records, false);
            $categories_services = $this->getCategories($records, true);
        }

        $documentExport = new DocumentExportStandard();
        $documentExport
            ->records($records)
            ->company($company)
            ->establishment($establishment)
            ->filters($filters)
            ->categories($categories)
            ->categories_services($categories_services);
         // return $documentExport->view();
        return $documentExport->download('Reporte_Ventas_'.Carbon::now().'.xlsx');
        });

    }


    /**
     *
     * Generar reportes en cola
     *
     * @param  Request $request
     * @return array
     */
    public function export(Request $request)
    {
        $host = $request->getHost();
        $columns=json_decode($request->columns);

        $website = $this->getTenantWebsite();
        $user = $this->getCurrentUser();
        $tray = $this->createDownloadTray($user->id, 'REPORT', $request->input('format'), 'Reporte Documentos - Ventas');

        $filters = $request->all();
        $this->setFiltersForJobReport($filters, $user, $request);

        ProcessDocumentReport::dispatch($tray->id, $website->id, $filters, $columns);

        return $this->getJobResponse();

        /*
        $tray = DownloadTray::create([
            'user_id' => auth()->user()->id,
            'module' => 'DOCUMENTS',
            'format' => $request->input('format'),
            'date_init' => date('Y-m-d H:i:s'),
            'type' => 'Reporte Ventas Documentos'
        ]);

        $trayId = $tray->id;

        $company = Company::first();
        $establishment = ($request->establishment_id) ? Establishment::findOrFail($request->establishment_id) : auth()->user()->establishment;

        $documentTypeId = "01";
        if ($request->has('document_type_id')) {
            $documentTypeId = str_replace('"', '', $request->document_type_id);
        }
        $documentType = DocumentType::find($documentTypeId);
        if (null === $documentType) {
            $documentType = new DocumentType();
        }

        $classType = $documentType->getCurrentRelatiomClass();

        $records = $this->getRecords($request->all(), $classType);
        $records= $records->get();

        //get categories
        $categories = [];
        $categories_services = [];

        if($request->include_categories == "true"){
            $categories = $this->getCategories($records, false);
            $categories_services = $this->getCategories($records, true);
        }
        */

        /*
        return  [
            'success' => true,
            'message' => 'El reporte se esta procesando; puede ver el proceso en bandeja de descargas.'
        ];
        */
    }


    /**
     *
     * Asignar datos para filtros en query
     *
     * @param  mixed $filters
     * @param  mixed $user
     * @param  mixed $request
     * @return void
     */
    private function setFiltersForJobReport(&$filters, $user, $request)
    {
        $filters['establishment_id_for_format'] = $filters['establishment_id'] ?? $user->establishment_id;
        $filters['class_type_records'] = $this->getFilterClassType($request);
        $filters['session_user_id'] = $user->id;
    }


    /**
     *
     * Retorna modelo (transaccion) dependiendo del tipo de documento seleccionado
     *
     * @param  Request $request
     * @return string
     */
    private function getFilterClassType($request)
    {
        $documentTypeId = "01";
        if ($request->has('document_type_id')) {
            $documentTypeId = str_replace('"', '', $request->document_type_id);
        }
        $documentType = DocumentType::find($documentTypeId);
        if (null === $documentType) {
            $documentType = new DocumentType();
        }

        return $documentType->getCurrentRelatiomClass();
    }


    public function getCategories($records, $is_service) {

        $aux_categories = collect([]);

        foreach ($records as $document) {

            $id_categories = $document->items->filter(function($row) use($is_service){
                return data_get($row,'relation_item.category_id') !== null
                    && (!$is_service || data_get($row,'item.unit_type_id') === 'SERV');
            })->pluck('relation_item.category_id');

            foreach ($id_categories as $value) {
                $aux_categories->push($value);
            }
        }

        return Category::whereIn('id', $aux_categories->unique()->toArray())->get();

    }

    public function email(Request $request) {
        $request->validate(
            ['email' => 'required']
        );
        $data=$request->data;
        $columns=$request->columns;
        $company = Company::active();
        $email = $request->input('email');

        [$pdf, $excel] = ReadSnapshot::run((new Document)->getConnection(), function () use ($data, $columns) {
            return [$this->getPdf($data,$columns), $this->getExcel($data,$columns)];
        });
        $mailable = new DocumentEmail($company, $pdf, $excel);
        $sendIt = EmailController::SendMail($email, $mailable);

        return [
            'success' => $sendIt,
            'message' => $sendIt ? 'El correo fue enviado satisfactoriamente.' : 'No se pudo enviar el correo. Revise la configuración y el registro de envíos.'
        ];
    }

    private function getPdf($request,$columns, $format = 'ticket', $mm = null)
    {
        $reques=json_decode(json_encode($request, JSON_FORCE_OBJECT));
        set_time_limit (1800); // Maximo 30 minutos
        $columns=json_decode(json_encode($columns));
        $company = Company::first();
        $establishment = ($reques->establishment_id) ? Establishment::findOrFail($reques->establishment_id) : auth()->user()->establishment;
        $documentTypeId = "01";
        if ($reques->document_type_id) {
            $documentTypeId = str_replace('"', '', $reques->document_type_id);
        }
        $documentType = DocumentType::find($documentTypeId);
        if (null === $documentType) {
            $documentType = new DocumentType();
        }

        $classType = $documentType->getCurrentRelatiomClass();
        $records = $this->getRecords($request, $classType);
        $records= $records->get();

        $filters = $request;
        // dd($data);

        $quantity_rows = 30;//$cash->cash_documents()->count();

        $width = 78;
        if($mm != null) {
            $width = $mm - 2;
        }

        $view = view('report::documents.report_pdf', compact("records", "company", "establishment", "filters", "columns"));
        $html = $view->render();
        $pdf = new Mpdf([
                            'mode' => 'utf-8',
                            'format' => \Modules\Report\Services\DocumentReportPaper::landscape($columns),
                        ]);
        $pdf->WriteHTML($html);

        return $pdf->output('', 'S');
    }


    private function getExcel($request,$columns)
    {
        $reques=json_decode(json_encode($request, JSON_FORCE_OBJECT));
        set_time_limit (1800); // Maximo 30 minutos
        $columns=json_decode(json_encode($columns));
        $company = Company::first();
        $establishment = ($reques->establishment_id) ? Establishment::findOrFail($reques->establishment_id) : auth()->user()->establishment;
        $documentTypeId = "01";
        if ($reques->document_type_id) {
            $documentTypeId = str_replace('"', '', $reques->document_type_id);
        }
        $documentType = DocumentType::find($documentTypeId);
        if (null === $documentType) {
            $documentType = new DocumentType();
        }

        $classType = $documentType->getCurrentRelatiomClass();
        $records = $this->getRecords($request, $classType);
        $records= $records->get();

        $filters = $request;

        $categories = [];
        $categories_services = [];

        if($reques->include_categories == "true"){
            $categories = $this->getCategories($records, false);
            $categories_services = $this->getCategories($records, true);
        }
        // dd($data);
        $documentExport = new DocumentExport();
        $documentExport
            ->records($records)
            ->company($company)
            ->establishment($establishment)
            ->filters($filters)
            ->categories($categories)
            ->categories_services($categories_services)
            ->columns($columns);
        $attachment = Excel::raw($documentExport,
            BaseExcel::XLSX
        );

        return $attachment;
    }


}
