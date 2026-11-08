<?php

    namespace Modules\Report\Http\Controllers;

    use App\Http\Controllers\Controller;
    use App\Models\Tenant\Company;
    use App\Models\Tenant\Establishment;
    use Carbon\Carbon;
    use Illuminate\Contracts\View\Factory;
    use Illuminate\Database\Query\Builder;
    use Illuminate\Foundation\Application;
    use Illuminate\Http\RedirectResponse;
    use Illuminate\Http\Request;
    use Illuminate\Http\Response;
    use Illuminate\View\View;
    use Modules\BusinessTurn\Models\BusinessTurn;
    use Modules\BusinessTurn\Models\DocumentHotel;
    use Modules\Report\Exports\DocumentHotelExport;
    use Modules\Report\Http\Resources\DocumentHotelCollection;
    use Symfony\Component\HttpFoundation\BinaryFileResponse;

    class ReportDocumentHotelController extends Controller
    {
        // use ReportTrait;


        /**
         * @return array
         */
        public function filter()
        {

            $document_types = [];

            $establishments = Establishment::all()->transform(function (Establishment $row) {
                return [
                    'id' => $row->id,
                    'name' => $row->description
                ];
            });

            return compact('document_types', 'establishments');
        }


        /**
         * @return Factory|Application|RedirectResponse|View
         */
        public function index()
        {
            $record = BusinessTurn::where('value', 'hotel')->where('active', true)->first();

            if (!$record) {
                return redirect()->route('tenant.reports.sale_notes.index');
            }


            return view('report::document_hotels.index');
        }

        /**
         * @param Request $request
         *
         * @return DocumentHotelCollection
         */
        public function records(Request $request)
        {
            return \App\Support\Exact\ReadSnapshot::run((new DocumentHotel)->getConnection(), function () use ($request) {
                $records = $this->getRecords($request->all());
                return (new DocumentHotelCollection($records->paginate(config('tenant.items_per_page'))))->response($request);
            });
        }

        /**
         * @param $request
         *
         * @return \Illuminate\Database\Eloquent\Builder|Builder|DocumentHotel
         */
        public function getRecords($request)
        {

            $date_start = $request['date_start'] ?? null;
            $date_end = $request['date_end'] ?? null;

            return $this->data($date_start, $date_end);

        }

        /**
         * @param $date_start
         * @param $date_end
         *
         * @return \Illuminate\Database\Eloquent\Builder|Builder|DocumentHotel
         */
        private function data($date_start, $date_end)
        {
            $query = DocumentHotel::with(['document', 'identity_document_type'])->searchByDate($date_start, $date_end);
            $user = auth()->user();
            abort_unless($user instanceof \App\Models\Tenant\User, 403);
            if ($user->type !== 'admin') $query->whereHas('document', function ($documents) use ($user) {
                $documents->where(function ($scope) use ($user) {
                    $scope->where('user_id', $user->id)->orWhere('seller_id', $user->id);
                });
            });
            return $query->latest()->orderByDesc('id');

        }

        /**
         * @param Request $request
         *
         * @return Response|BinaryFileResponse
         */
        public function excel(Request $request)
        {
            return \App\Support\Exact\ReadSnapshot::run((new DocumentHotel)->getConnection(), function () use ($request) {
            $company = Company::first();

            $records = $this->getRecords($request->all())->get();

            $documentHotelExport = new DocumentHotelExport();
            $documentHotelExport
                ->records($records)
                ->company($company);

            return $documentHotelExport->download('Reporte_Hoteles_' . Carbon::now() . '.xlsx');
            });

        }

        public function pdf(Request $request)
        {
            return \App\Support\Exact\ReadSnapshot::run((new DocumentHotel)->getConnection(), function () use ($request) {
                $company = Company::first();
                $records = $this->getRecords($request->all())->get();
                return \Barryvdh\DomPDF\Facade\Pdf::loadView('report::document_hotels.report_pdf', compact('records','company'))
                    ->setPaper('a4','landscape')->download('Reporte_Hoteles_'.date('YmdHis').'.pdf');
            });
        }

    }
