<?php

namespace Modules\MobileApp\Http\Controllers\Api;

use App\Models\Tenant\Catalogs\RelatedDocumentType;
use App\Models\Tenant\Catalogs\TransferReasonType;
use App\Models\Tenant\Catalogs\TransportModeType;
use App\Models\Tenant\Catalogs\UnitType;
use App\Models\Tenant\Dispatch;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Dispatch\Http\Controllers\DispatcherController;
use Modules\Dispatch\Models\Driver;
use Modules\Dispatch\Models\OriginAddress;
use Modules\Dispatch\Models\Transport;
use Modules\MobileApp\Http\Resources\Api\DispatchCollection;

class DispatchController extends Controller
{
    /**
     * Catalogos livianos para el formulario de Ã³rdenes de entrega de la app.
     * Reune en una sola llamada lo que la app necesita ademas de lo que ya precarga
     * (establecimientos/series, personas, ubigeo, items).
     *
     * @return array
     */
    public function tables()
    {
        $transferReasonTypes = TransferReasonType::whereActive()
            ->get()
            ->map(fn($row) => [
                'id' => $row->id,
                'description' => $row->description,
            ])
            ->values();

        $transportModeTypes = TransportModeType::whereActive()
            ->get()
            ->map(fn($row) => [
                'id' => $row->id,
                'description' => $row->description,
            ])
            ->values();

        // Unidades de peso permitidas por SUNAT para el peso bruto total
        $unitTypes = UnitType::whereActive()
            ->whereIn('id', ['KGM', 'TNE'])
            ->get()
            ->map(fn($row) => [
                'id' => $row->id,
                'description' => $row->description,
            ])
            ->values();

        $relatedDocumentTypes = RelatedDocumentType::available()
            ->map(fn($row) => [
                'id' => $row->id,
                'description' => $row->description,
            ])
            ->values();

        $originAddresses = OriginAddress::query()
            ->where('is_active', true)
            ->get()
            ->map(fn($row) => [
                'id' => $row->id,
                'establishment_id' => $row->establishment_id,
                'establishment_code' => $row->establishment_code,
                'address' => $row->address,
                'location_id' => $row->location_id,
                'is_default' => (bool) $row->is_default,
            ])
            ->values();

        $dispatchers = app(DispatcherController::class)->getOptions()->values();

        $transports = Transport::query()
            ->where('is_active', true)
            ->get()
            ->map(fn($row) => [
                'id' => $row->id,
                'plate_number' => $row->plate_number,
                'model' => $row->model,
                'brand' => $row->brand,
                'tuc' => $row->tuc,
                'is_default' => (bool) $row->is_default,
            ])
            ->values();

        $drivers = Driver::query()
            ->where('is_active', true)
            ->get()
            ->map(fn($row) => [
                'id' => $row->id,
                'identity_document_type_id' => $row->identity_document_type_id,
                'number' => $row->number,
                'name' => $row->name,
                'license' => $row->license,
                'telephone' => $row->telephone,
            ])
            ->values();

        return [
            'success' => true,
            'data' => [
                'transfer_reason_types' => $transferReasonTypes,
                'transport_mode_types' => $transportModeTypes,
                'unit_types' => $unitTypes,
                'related_document_types' => $relatedDocumentTypes,
                'origin_addresses' => $originAddresses,
                'dispatchers' => $dispatchers,
                'transports' => $transports,
                'drivers' => $drivers,
            ],
        ];
    }

    /**
     * Listado de órdenes de entrega (09) para scroll infinito.
     *
     * Parametros soportados:
     * - limit: cantidad de registros (maximo 100, default 15)
     * - cursor: posicion actual (null en primera peticion)
     * - document_type_id: 09
     * - state_type_id: estado SUNAT
     * - input: busqueda parcial por serie o numero
     * - date_start / date_end: rango sobre date_of_issue (Y-m-d)
     *
     * @param  Request $request
     * @return array
     */
    public function byScroll(Request $request)
    {
        $limit = min((int) $request->input('limit', 15), 100);
        $cursor = $request->input('cursor');

        // `customer` es un snapshot JSON del documento (accessor), no una relacion
        $query = Dispatch::with(['state_type', 'transfer_reason_type'])
            ->whereIn('document_type_id', ['09'])
            ->whereTypeUser()
            ->orderBy('date_of_issue', 'desc')
            ->orderBy('id', 'desc');

        if ($request->filled('document_type_id')) {
            $query->where('document_type_id', $request->input('document_type_id'));
        }

        if ($request->filled('state_type_id')) {
            $query->where('state_type_id', $request->input('state_type_id'));
        }

        if ($request->filled('input')) {
            $input = $request->input('input');
            $query->where(function ($q) use ($input) {
                $q->where('series', 'like', '%' . $input . '%')
                    ->orWhere('number', 'like', '%' . $input . '%');
            });
        }

        if ($request->filled('date_start') && $request->filled('date_end')) {
            $query->whereBetween('date_of_issue', [
                $request->input('date_start'),
                $request->input('date_end'),
            ]);
        }

        $records = $cursor
            ? $query->cursorPaginate($limit, ['*'], 'cursor', $cursor)
            : $query->cursorPaginate($limit);

        return [
            'success' => true,
            'data' => new DispatchCollection($records),
            'pagination' => [
                'next_cursor' => optional($records->nextCursor())->encode(),
                'has_more' => $records->hasMorePages(),
            ],
        ];
    }
}
