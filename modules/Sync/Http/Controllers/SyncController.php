<?php

namespace Modules\Sync\Http\Controllers;

use App\CoreFacturalo\Facturalo;
use App\CoreFacturalo\Helpers\Storage\StorageDocument;
use App\CoreFacturalo\Requests\Api\Transform\DocumentTransform;
use App\CoreFacturalo\Requests\Api\Validation\DocumentValidation;
use App\CoreFacturalo\Requests\Inputs\DocumentInput;
use App\Models\Tenant\Cash;
use App\Models\Tenant\Company;
use App\Models\Tenant\Document;
use App\Models\Tenant\Establishment;
use App\Models\Tenant\Item;
use App\Models\Tenant\Person;
use App\Models\Tenant\Series;
use App\Models\Tenant\SeriesDeviceGroup;
use App\Models\Tenant\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Modules\Document\Models\SeriesConfiguration;
use Modules\Sync\Models\OfflineMachine;
use Modules\Sync\Models\SyncEvent;

/**
 * Conexión Offline VendeYa: enrolamiento de máquinas con series dedicadas,
 * snapshot del catálogo y recepción del lote cronológico de eventos
 * (aperturas/cierres de caja, ventas y anulaciones) con firma en el servidor.
 */
class SyncController extends Controller
{
    use StorageDocument;
    /**
     * GET /api/sync/enroll-options  (auth:api — solo admin)
     * Catálogos para el Setup: establecimientos del tenant y series dedicadas
     * aún libres (sin máquina asignada). Las series se crean/administran en el
     * facturador; aquí solo se listan para seleccionar.
     */
    public function enrollOptions()
    {
        $user = auth()->user();
        if (($user->type ?? null) !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Solo un administrador puede enrolar máquinas',
            ], 403);
        }

        $establishments = Establishment::select('id', 'code', 'description')
            ->orderBy('id')
            ->get();

        $series = Series::where('dedicated', true)
            ->whereNull('series_device_group_id')
            ->whereIn('document_type_id', ['01', '03', '07', '08'])
            ->orderBy('document_type_id')
            ->orderBy('number')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'establishment_id' => $s->establishment_id,
                'document_type_id' => $s->document_type_id,
                'serie' => $s->number,
            ]);

        return response()->json([
            'success' => true,
            'establishments' => $establishments,
            'series' => $series,
        ]);
    }

    /**
     * POST /api/sync/enroll  (auth:api — credenciales del tenant, solo admin)
     * Registra la máquina y le asigna series dedicadas EXISTENTES (elegidas
     * de enroll-options). El token viaja SOLO en esta respuesta.
     */
    public function enroll(Request $request)
    {
        $user = auth()->user();
        if (($user->type ?? null) !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Solo un administrador puede enrolar máquinas',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'machine_name' => ['required', 'string', 'max:100'],
            'establishment_id' => ['required', 'integer', 'exists:tenant.establishments,id'],
            'series_ids' => ['required', 'array', 'min:1'],
            'series_ids.*' => ['required', 'integer'],
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $plainToken = Str::random(60);

        try {
            $result = DB::connection('tenant')->transaction(function () use ($request, $user, $plainToken) {
                $uuid = Str::uuid()->toString();

                $selected = Series::whereIn('id', $request->series_ids)
                    ->lockForUpdate()
                    ->get();

                if ($selected->count() !== count(array_unique($request->series_ids))) {
                    throw new \RuntimeException('Alguna de las series seleccionadas ya no existe');
                }

                foreach ($selected as $serie) {
                    if (!$serie->dedicated) {
                        throw new \RuntimeException("La serie {$serie->number} no es dedicada — asígnela como dedicada en el facturador");
                    }
                    if ($serie->series_device_group_id) {
                        throw new \RuntimeException("La serie {$serie->number} ya está asignada a otra máquina");
                    }
                    if ((int) $serie->establishment_id !== (int) $request->establishment_id) {
                        throw new \RuntimeException("La serie {$serie->number} pertenece a otro establecimiento");
                    }
                }

                $group = SeriesDeviceGroup::create([
                    'establishment_id' => $request->establishment_id,
                    'name' => 'VendeYa - ' . $request->machine_name,
                ]);
                $group->bindToDevice($uuid, $user->id);

                $series = [];
                foreach ($selected as $serie) {
                    $serie->series_device_group_id = $group->id;
                    $serie->save();

                    $series[] = [
                        'id' => $serie->id,
                        'document_type_id' => $serie->document_type_id,
                        'serie' => $serie->number,
                        'next_number' => \Modules\Sync\Services\BatchProcessor::nextNumberFor($serie->document_type_id, $serie->number),
                    ];
                }

                $machine = OfflineMachine::create([
                    'uuid' => $uuid,
                    'name' => $request->machine_name,
                    'token_hash' => OfflineMachine::hashToken($plainToken),
                    'user_id' => $user->id,
                    'establishment_id' => $request->establishment_id,
                    'series_device_group_id' => $group->id,
                    'status' => 'active',
                ]);

                return compact('machine', 'series');
            });
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $establishment = Establishment::find($request->establishment_id);

        $users = User::where('establishment_id', $request->establishment_id)
            ->whereIn('type', ['seller', 'admin'])
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->type,
                'pin' => $u->restaurant_pin,
            ]);

        return response()->json([
            'success' => true,
            'machine' => [
                'uuid' => $result['machine']->uuid,
                'name' => $result['machine']->name,
                'token' => $plainToken, // única vez
            ],
            'establishment' => $this->establishmentContext($establishment),
            'company' => $this->companyContext(),
            'series' => $result['series'],
            'users' => $users,
        ]);
    }

    /**
     * GET /api/sync/snapshot  (auth.machine — token de la máquina)
     * Caché local: items y clientes del tenant, más las series de la máquina
     * y los usuarios del establecimiento (permite reanudar un setup a medias
     * solo con el token guardado).
     */
    public function snapshot(Request $request)
    {
        /** @var OfflineMachine $machine */
        $machine = $request->attributes->get('offline_machine');

        $series = Series::where('series_device_group_id', $machine->series_device_group_id)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'document_type_id' => $s->document_type_id,
                'serie' => $s->number,
                'next_number' => \Modules\Sync\Services\BatchProcessor::nextNumberFor($s->document_type_id, $s->number),
            ]);

        $users = User::where('establishment_id', $machine->establishment_id)
            ->whereIn('type', ['seller', 'admin'])
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->type,
                'pin' => $u->restaurant_pin,
            ]);

        $items = Item::query()
            ->whereNotIn('unit_type_id', ['ZZ'])
            ->get()
            ->map(fn ($i) => [
                'id' => $i->id,
                'internal_id' => $i->internal_id,
                'item_code' => $i->item_code,
                'item_code_gs1' => $i->item_code_gs1,
                'description' => $i->description,
                'unit_type_id' => $i->unit_type_id,
                'currency_type_id' => $i->currency_type_id,
                'sale_unit_price' => $i->sale_unit_price,
                'sale_affectation_igv_type_id' => $i->sale_affectation_igv_type_id,
                'has_igv' => (bool) $i->has_igv,
                'stock' => $i->stock,
                'is_set' => (bool) $i->is_set,
                'amount_plastic_bag_taxes' => $i->amount_plastic_bag_taxes,
            ]);

        $customers = Person::whereType('customers')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'identity_document_type_id' => $p->identity_document_type_id,
                'number' => $p->number,
                'name' => $p->name,
                'address' => $p->address,
                'district_id' => $p->district_id,
                'country_id' => $p->country_id,
                'email' => $p->email,
                'telephone' => $p->telephone,
            ]);

        return response()->json([
            'success' => true,
            'machine' => ['uuid' => $machine->uuid, 'name' => $machine->name],
            'company' => $this->companyContext(),
            'establishment' => $this->establishmentContext(
                Establishment::find($machine->establishment_id)
            ),
            'series' => $series,
            'users' => $users,
            'items' => $items,
            'customers' => $customers,
        ]);
    }

    /**
     * Datos de la empresa tal como los consumen las plantillas UBL en la
     * máquina (contexto del sidecar).
     */
    private function companyContext(): array
    {
        $company = Company::query()->first();

        return [
            'number' => $company->number,
            'ruc' => $company->number, // compatibilidad con máquinas ya enroladas
            'name' => $company->name,
            'trade_name' => $company->trade_name,
        ];
    }

    /**
     * Establecimiento completo para el contexto del sidecar: misma estructura
     * que EstablishmentInput (la que persiste en el documento y leen las
     * plantillas UBL para la dirección fiscal del emisor).
     */
    private function establishmentContext(?Establishment $establishment): ?array
    {
        if (!$establishment) {
            return null;
        }

        return [
            'id' => $establishment->id,
            'description' => $establishment->description,
            'country_id' => $establishment->country_id,
            'country' => [
                'id' => $establishment->country_id,
                'description' => optional($establishment->country)->description,
            ],
            'department_id' => $establishment->department_id,
            'department' => [
                'id' => $establishment->department_id,
                'description' => optional($establishment->department)->description,
            ],
            'province_id' => $establishment->province_id,
            'province' => [
                'id' => $establishment->province_id,
                'description' => optional($establishment->province)->description,
            ],
            'district_id' => $establishment->district_id,
            'district' => [
                'id' => $establishment->district_id,
                'description' => optional($establishment->district)->description,
            ],
            'urbanization' => $establishment->urbanization,
            'address' => $establishment->address,
            'email' => $establishment->email,
            'telephone' => $establishment->telephone,
            'code' => $establishment->code,
            'trade_address' => $establishment->trade_address,
            'web_address' => $establishment->web_address,
            'aditional_information' => $establishment->aditional_information,
        ];
    }

    /**
     * POST /api/sync/heartbeat  (auth.machine)
     */
    public function heartbeat(Request $request)
    {
        /** @var OfflineMachine $machine */
        $machine = $request->attributes->get('offline_machine');

        return response()->json([
            'success' => true,
            'server_time' => now()->toIso8601String(),
            'machine' => [
                'uuid' => $machine->uuid,
                'name' => $machine->name,
                'status' => $machine->status,
            ],
        ]);
    }

    /**
     * POST /api/sync/batch  (auth.machine — token de la máquina)
     * Recibe el lote cronológico de eventos y lo procesa EN ORDEN:
     * cash_open → sale… → void… → cash_close. Idempotente por external_id
     * (un reintento devuelve el resultado ya registrado, jamás duplica).
     *
     * Para cada venta: se re-renderiza el XML con las plantillas del facturador
     * y se compara BYTE A BYTE con el generado por la máquina (test de contrato
     * vivo); si coincide, se firma aquí con el certificado del tenant y el hash
     * resultante debe ser exactamente el impreso en el ticket. El envío a SUNAT
     * queda en manos de los comandos existentes (online:send-all).
     */
    public function batch(Request $request)
    {
        /** @var OfflineMachine $machine */
        $machine = $request->attributes->get('offline_machine');

        $validator = Validator::make($request->all(), [
            'events' => ['required', 'array', 'min:1'],
            'events.*.seq' => ['required', 'integer'],
            'events.*.type' => ['required', 'in:cash_open,sale,void,cash_close'],
            'events.*.external_id' => ['required', 'uuid'],
            'events.*.occurred_at' => ['required', 'date'],
            'events.*.user_id' => ['required', 'integer'],
            // present (no required): un cash_close legítimo viaja con payload {}
            // y "required" de Laravel rechaza arreglos vacíos.
            'events.*.payload' => ['present', 'array'],
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $events = collect($request->input('events'))->sortBy('seq')->values();
        $results = [];
        $processor = new \Modules\Sync\Services\BatchProcessor();

        // Las ventas del lote no deben chocar con el bloqueo de emisión online.
        app()->instance('sync.batch.bypass', true);

        try {
            foreach ($events as $event) {
                $existing = SyncEvent::where('external_id', $event['external_id'])->first();
                if ($existing) {
                    $results[] = array_merge($existing->toResult(), ['status' => 'duplicated']);
                    continue;
                }

                $results[] = $processor->processEvent($machine, $event);
            }
        } finally {
            app()->forgetInstance('sync.batch.bypass');
        }

        return response()->json([
            'success' => true,
            'machine' => ['uuid' => $machine->uuid, 'name' => $machine->name],
            'results' => $results,
        ]);
    }
}
