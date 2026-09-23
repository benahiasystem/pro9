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

        // ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
        $fact = \App\Services\Fiscal\FiscalApiDispatchService::register($request->all());
        $document = $fact->getDocument();
        $db = $document->getConnection();
        $reservation = $db->table('fiscal_number_reservations')->where('dispatch_id', $document->id)->first();
        $reservation = (new \App\Services\Fiscal\FiscalEmissionService($db))->process((int) $reservation->id);
        $fact->createPdf();
        // ######## FIN NUMERACIÓN FISCAL VENEZUELA ########

        return [
            'success' => true,
            'data' => [
                'id' => $document->id,
                'number' => $document->number_full,
                'fiscal_identity' => $document->fiscal_identity,
                'replayed' => !$fact->wasNewFiscalRegistration(),
                'fiscal' => ['status' => $reservation->status, 'control_number' => $reservation->control_number],
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
        $this->authorizedQuery();
        $establishments = Establishment::where('id', auth()->user()->establishment_id)->get();
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
        $request->validate(['input' => ['nullable', 'string', 'max:128'], 'series' => ['nullable', 'string', 'max:32'], 'number' => ['nullable', 'regex:/^[0-9]+$/D'], 'control_number' => ['nullable', 'string', 'max:32']]);
        $input = $request->input;
        $records = $this->authorizedQuery()->where('document_type_id', '09');
        if ($request->filled('control_number') || $request->filled('series') || $request->filled('number')) {
            $records->whereFiscalIdentifiers($request->series, $request->number, $request->control_number);
        } elseif ($input !== null && $input !== '') {
            if (preg_match('/^[0-9]{2}-[0-9]+$/D', $input)) $records->whereFiscalIdentifiers(null, null, $input);
            elseif (ctype_digit((string) $input)) $records->whereFiscalIdentifiers(null, $input);
            else $records->whereFiscalIdentifiers($input);
        }
        $records->latest();
        return new DispatchCollection($records->paginate(config('tenant.items_per_page')));
    }


    /**
     * Devuelve una orden de entrega por su id.
     *
     * Restringe por sucursal y por propietario para vendedores e integradores.
     */
    public function record($id)
    {
        $record = $this->authorizedQuery()->find($id);

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
    private function authorizedQuery()
    {
        $actor = auth()->user();
        if (!$actor instanceof \App\Models\Tenant\User || !in_array($actor->type, ['admin', 'seller', 'integrator'], true) || !$actor->establishment_id) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('La API de órdenes requiere un usuario autorizado con sucursal.');
        }
        $query = Dispatch::query()->where('establishment_id', $actor->establishment_id);
        if ($actor->type !== 'admin') $query->where('user_id', $actor->id);
        return $query;
    }

}
// ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
