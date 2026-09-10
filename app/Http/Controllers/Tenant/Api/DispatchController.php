<?php
// ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########

namespace App\Http\Controllers\Tenant\Api;

use App\CoreFacturalo\Facturalo;
use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\DispatchCollection;
use App\Models\Tenant\Dispatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant\Establishment;
use Modules\Dispatch\Models\Driver;
use Modules\Dispatch\Models\Transport;
use App\Models\Tenant\Catalogs\{
    IdentityDocumentType,
    TransferReasonType,
    TransportModeType,
    UnitType
};
use Modules\Dispatch\Models\OriginAddress;

class DispatchController extends Controller
{
    public function __construct()
    {
        $this->middleware('input.request:dispatch,api', ['only' => ['store']]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'delivery.address' => 'required|max:100',
            'origin.address' => 'required|max:100',
        ]);

        $fact = DB::connection('tenant')->transaction(function () use ($request) {
            $facturalo = new Facturalo();
            $facturalo->save($request->all());
            $document = $facturalo->getDocument();
            $facturalo->createPdf();
            return $facturalo;
        });

        $document = $fact->getDocument();

        return [
            'success' => true,
            'data' => [
                'number' => $document->number_full,
                'filename' => $document->filename,
                'external_id' => $document->external_id,
            ],
        ];
    }



    /**
    * Tables
    * @param  Request $request
    * @return \Illuminate\Http\Response
    */
    public function tables(Request $request) {
        $transferReasonTypes = TransferReasonType::whereActive()->get();
        $transportModeTypes = TransportModeType::whereActive()->get();
        $unitTypes = UnitType::whereActive()->get();
        $establishments = Establishment::all();
        $origin_addresses = OriginAddress::all();
        $dispatchers = app(\Modules\Dispatch\Http\Controllers\DispatcherController::class)->getOptions();
        $transports = Transport::query()
            ->where('is_active', true)
            ->get()
            ->transform(function ($row) {
                return [
                    'id' => $row->id,
                    'plate_number' => $row->plate_number,
                    'model' => $row->model,
                    'brand' => $row->brand,
                    'is_default' => $row->is_default,
                    'tuc' => $row->tuc,
                ];
            });
        $drivers = Driver::query()
            ->where('is_active', true)
            ->get()
            ->transform(function ($row) {
                return [
                    'id' => $row->id,
                    'identity_document_type_id' => $row->identity_document_type_id,
                    'number' => $row->number,
                    'name' => $row->name,
                    'license' => $row->license,
                    'telephone' => $row->telephone,
                ];
            });

        return compact('establishments', 'origin_addresses' , 'transportModeTypes', 'transferReasonTypes', 'unitTypes', 'dispatchers','transports','drivers');
    }

    public function records(Request $request)
    {
        $input = $request->input;
        $records = Dispatch::query()
            ->where('document_type_id', '09')
            ->when($input, function ($query) use ($input) {
                return $query
                    ->where('series', 'like', '%' . $input . '%')
                    ->orWhere('number', 'like', '%' . $input . '%');
            })
            ->latest();
        return new DispatchCollection($records->paginate(config('tenant.items_per_page')));
    }


    /**
     * Devuelve una orden de entrega por su id.
     *
     * whereTypeUser() evita que un vendedor pueda leer registros de otro usuario
     * pasando ids ajenos; para los demas perfiles no restringe nada.
     */
    public function record($id)
    {
        $record = Dispatch::whereTypeUser()->find($id);

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró la orden de entrega solicitada.',
            ], 404);
        }

        // Mismo envoltorio "data" que document/find y sale-note/find.
        return response()->json([
            'data' => $record->getApiResourceFind(),
        ]);
    }
}
// ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
