<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Requests\Tenant\PersonRequest;
use App\Http\Resources\Tenant\PersonCollection;
use App\Http\Resources\Tenant\PersonResource;
use App\Imports\PersonsImport;
use App\Models\Tenant\Catalogs\Country;
use App\Models\Tenant\Catalogs\Department;
use App\Models\Tenant\Catalogs\District;
use App\Models\Tenant\Catalogs\IdentityDocumentType;
use App\Models\Tenant\Catalogs\Province;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Person;
use App\Models\Tenant\PersonType;
use App\Models\Tenant\Zone;
use Exception;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;
use Carbon\Carbon;
use App\Exports\ClientExport;
use App\Models\System\Configuration;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;
use Picqer\Barcode\BarcodeGeneratorPNG;

class PersonController extends Controller
{
    public function index($type)
    {
        // $configuration = Configuration::first();
        // $api_service_token = $configuration->token_apiruc =! '' ? $configuration->token_apiruc : config('configuration.api_service_token');
        $api_service_token = \App\Models\Tenant\Configuration::getApiServiceToken();

        return view('tenant.persons.index', compact('type', 'api_service_token'));
    }

    public function columns()
    {
        return [
            'name' => 'Nombre',
            'barcode' => 'Código de barras',
            'number' => 'Número',
            'document_type' => 'Tipo de documento'
        ];
    }

    public function records($type, Request $request)
    {

        $value = $request->value;
        if ($request->column == 'document_type') {
            $records = Person::whereHas('identity_document_type', function($query) use($value){
                $query->where('description', 'like', "%{$value}%");
            });
        } else {
            $records = Person::where($request->column, 'like', "%{$request->value}%");
        }
        $records = $records->where('type', $type)
            ->whereFilterCustomerBySeller($type);

        // Filtro por habilitados/inhabilitados
        $show_disabled = $request->show_disabled ?? 'all';
        if ($show_disabled === 'enabled') {
            $records = $records->where('enabled', true);
        } elseif ($show_disabled === 'disabled') {
            $records = $records->where('enabled', false);
        }

        $records = $records->orderBy('id','desc');

        return new PersonCollection($records->paginate(config('tenant.items_per_page')));
    }

    public function create()
    {
        return view('tenant.customers.form');
    }

    public function tables()
    {
        $countries = Country::whereActive()->orderByDescription()->get();
        $identity_document_types = IdentityDocumentType::whereActive()->orderByPersonPriority()->get();
        $person_types = PersonType::get();
        $locations = func_get_locations();
        $zones = Zone::all();
        $sellers = $this->getSellers();
        $api_service_token = \App\Models\Tenant\Configuration::getApiServiceToken();

        return compact('countries', 'identity_document_types', 'locations','person_types','api_service_token'
        ,'zones','sellers');
    }

    public function record($id)
    {
        $record = new PersonResource(Person::findOrFail($id));

        return $record;
    }

    public function store(PersonRequest $request)
    {
        /* dd($request->all()); */

        if (!$request->barcode) {
            if ($request->internal_id) {
                $request->merge(['barcode' => $request->internal_id]);
            }
        }

        if ($request->state) {
            if ($request->state != "ACTIVO") {
                return [
                    'success' => false,
                    'message' => 'El estado del contribuyente no es activo, no puede registrarlo',
                ];
            }
        }

        // ######## INICIO CAMBIO GEOPOLITICO VENEZUELA
        // Conserva las excepciones de domicilio de main, con la jerarquía venezolana.
        $addresses = $request->input('addresses') ?: [];
        $requires_address = !$this->hasOptionalAddress(
            $request->input('identity_document_type_id'),
            $request->input('number')
        );

        if ($requires_address) {

            if ($this->resolveMainAddressText($request->input('address'), $addresses) === '') {
                return [
                    'success' => false,
                    'message' => 'Falta registrar la dirección principal'
                ];
            }

            $main_location_id = $this->normalizeLocationId($request->input('location_id'));

            if (!$this->isCompleteLocationId($main_location_id)) {
                $main_location_id = $this->resolveMainAddressLocationId($addresses);
            }

            if ($request->input('country_id') === 'VE' && !$this->isCompleteLocationId($main_location_id)) {
                return [
                    'success' => false,
                    'message' => 'Falta registrar Estado / Municipio / Parroquia en la dirección principal'
                ];
            }
        }

        // Restricción para direcciones secundarias de Venezuela.
        foreach (($requires_address ? $addresses : []) as $index => $row) {
            if (isset($row['country_id']) && $row['country_id'] === 'VE') {
                if (empty($row['location_id']) || !is_array($row['location_id']) || count($row['location_id']) !== 3 ||
                    !isset($row['location_id'][0]) || !isset($row['location_id'][1]) || !isset($row['location_id'][2]) ||
                    empty($row['location_id'][0]) || empty($row['location_id'][1]) || empty($row['location_id'][2])) {
                    return [
                        'success' => false,
                        'message' => 'Falta registrar Estado / Municipio / Parroquia en la dirección secundaria #' . ($index + 1)
                    ];
                }
            }
        }

        $id = $request->input('id');
        $person = Person::firstOrNew(['id' => $id]);
        $data = $request->all();
        unset($data['optional_email'], $data['id']);
        $person->fill($data);

        // La jerarquía de la persona sale de location_id, pero desde que los inputs de
        // dirección principal quedaron ocultos ese campo depende del espejo del front
        // y puede llegar vacío. En ese caso se toma el de la dirección principal para
        // no dejar en null department_id / province_id / district_id.
        $location_id = $this->normalizeLocationId($request->input('location_id'));

        if (!$this->isCompleteLocationId($location_id)) {
            $location_id = $this->resolveMainAddressLocationId($request->input('addresses'));
        }

        if ($request->input('country_id') === 'VE' && $this->isCompleteLocationId($location_id)) {
            $person->district_id = $location_id[2];
            $person->province_id = $location_id[1];
            $person->department_id = $location_id[0];
        } else {
            $person->district_id = null;
            $person->province_id = null;
            $person->department_id = null;
        }
        // ######## FIN CAMBIO GEOPOLITICO VENEZUELA

        if($request->password && $request->email ){
            $person->password = bcrypt($request->password);
        }

        $person->save();

        $addresses = $request->input('addresses') ?: [];
        $existingAddresses = $person->addresses()->get();
        $submittedIds = collect($addresses)->pluck('id')->filter()->all();

        $existingAddresses->each(function ($item) use ($submittedIds) {
            if (!in_array($item->id, $submittedIds, true)) {
                $item->delete();
            }
        });

        foreach ($addresses as $row) {
            $payload = $this->mapPersonAddressPayload($row);

            if (!empty($row['id'])) {
                $person->addresses()->updateOrCreate(['id' => $row['id']], $payload);
                continue;
            }

            $person->addresses()->create($payload);
        }

        $optional_email = $request->optional_email;
        if (!empty($optional_email)) {
            $person->setOptionalEmailArray($optional_email)->push();
        }

        $msg = '';
        if ($request->type === 'suppliers') {
            $msg = ($id) ? 'Proveedor editado con éxito' : 'Proveedor registrado con éxito';
        } else {
            $msg = ($id) ? 'Cliente editado con éxito' : 'Cliente registrado con éxito';
        }
        return [
            'success' => true,
            'message' => $msg,
            'id' => $person->id
        ];
    }

    /**
     * Deja el ubigeo como lista indexada, descartando valores vacios.
     *
     * @param  mixed  $locationId
     * @return array
     */
    private function normalizeLocationId($locationId): array
    {
        if (!is_array($locationId)) {
            return [];
        }

        return array_values(array_filter($locationId, function ($value) {
            return $value !== null && $value !== '';
        }));
    }

    /**
     * Un ubigeo solo sirve si trae departamento, provincia y distrito.
     *
     * @param  mixed  $locationId
     * @return bool
     */
    private function isCompleteLocationId($locationId): bool
    {
        return is_array($locationId) && count($locationId) === 3;
    }

    /**
     * Personas a las que no se les exige domicilio: DNI y RUC de persona natural
     * (10xxxxxxxxx).
     *
     * @param  mixed  $identityDocumentTypeId
     * @param  mixed  $number
     * @return bool
     */
    private function hasOptionalAddress($identityDocumentTypeId, $number): bool
    {
        $identityDocumentTypeId = (string) $identityDocumentTypeId;

        if ($identityDocumentTypeId === '1') {
            return true;
        }

        return $identityDocumentTypeId === '6'
            && strpos(trim((string) $number), '10') === 0;
    }

    /**
     * Texto de la direccion principal: se toma la columna de la persona y, si llega
     * vacia, la fila main del payload de direcciones.
     *
     * @param  mixed  $address
     * @param  mixed  $addresses
     * @return string
     */
    private function resolveMainAddressText($address, $addresses): string
    {
        $address = trim((string) $address);
        if ($address !== '') {
            return $address;
        }

        $rows = array_values(array_filter(is_array($addresses) ? $addresses : [], 'is_array'));
        if (empty($rows)) {
            return '';
        }

        $main = null;
        foreach ($rows as $row) {
            if (!empty($row['main'])) {
                $main = $row;
                break;
            }
        }

        if ($main === null) {
            $main = $rows[0];
        }

        return trim((string) ($main['address'] ?? ''));
    }

    /**
     * Ubigeo de la direccion principal (main) del payload. Si esa fila no lo trae en
     * location_id se arma con sus columnas department_id / province_id / district_id.
     *
     * @param  mixed  $addresses
     * @return array
     */
    private function resolveMainAddressLocationId($addresses): array
    {
        $addresses = is_array($addresses) ? $addresses : [];

        $rows = array_values(array_filter($addresses, 'is_array'));
        if (empty($rows)) {
            return [];
        }

        $main = null;
        foreach ($rows as $row) {
            if (!empty($row['main'])) {
                $main = $row;
                break;
            }
        }

        if ($main === null) {
            $main = $rows[0];
        }

        $locationId = $this->normalizeLocationId($main['location_id'] ?? []);
        if ($this->isCompleteLocationId($locationId)) {
            return $locationId;
        }

        $departmentId = $main['department_id'] ?? null;
        $provinceId = $main['province_id'] ?? null;
        $districtId = $main['district_id'] ?? null;

        if ($departmentId && $provinceId && $districtId) {
            return [$departmentId, $provinceId, $districtId];
        }

        return [];
    }

    /**
     * Sincroniza direcciones secundarias: actualiza las existentes, crea las nuevas
     * y elimina las que ya no vienen en el request.
     *
     * El ubigeo se toma de location_id (cascader). Se ignoran department_id /
     * province_id / district_id del payload porque al editar suelen quedar
     * con los valores originales y pisan el ubigeo nuevo.
     *
     * @param  \App\Models\Tenant\Person  $person
     * @param  array  $addresses
     * @return void
     */
    private function syncPersonAddresses(Person $person, $addresses)
    {
        $addresses = is_array($addresses) ? $addresses : [];
        $keepIds = [];

        foreach ($addresses as $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = !empty($row['id']) ? $row['id'] : null;

            if (isset($row['location_id']) && is_array($row['location_id']) && count($row['location_id']) === 3) {
                $row['department_id'] = $row['location_id'][0] ?: null;
                $row['province_id'] = $row['location_id'][1] ?: null;
                $row['district_id'] = $row['location_id'][2] ?: null;
            }

            unset(
                $row['id'],
                $row['location_id'],
                $row['consigned_name'],
                $row['trade_name'],
                $row['from_sunat_establishment']
            );

            if ($id) {
                $address = $person->addresses()->updateOrCreate(['id' => $id], $row);
            } else {
                $address = $person->addresses()->create($row);
            }

            $keepIds[] = $address->id;
        }

        $query = $person->addresses();
        if (empty($keepIds)) {
            $query->delete();
        } else {
            $query->whereNotIn('id', $keepIds)->delete();
        }
    }

    /**
     * Normaliza el payload de una dirección secundaria antes de persistirla.
     */
    private function mapPersonAddressPayload(array $row): array
    {
        $locationId = $row['location_id'] ?? [];
        if (!is_array($locationId)) {
            $locationId = [];
        }

        $locationId = array_values(array_filter($locationId, function ($value) {
            return $value !== null && $value !== '';
        }));

        $departmentId = $row['department_id'] ?? null;
        $provinceId = $row['province_id'] ?? null;
        $districtId = $row['district_id'] ?? null;

        if (count($locationId) === 3) {
            $departmentId = $locationId[0];
            $provinceId = $locationId[1];
            $districtId = $locationId[2];
        } elseif ($departmentId && $provinceId && $districtId) {
            $locationId = [$departmentId, $provinceId, $districtId];
        } else {
            $departmentId = null;
            $provinceId = null;
            $districtId = null;
        }

        return [
            // ######## INICIO CAMBIO GEOPOLITICO VENEZUELA
            'country_id'           => $row['country_id'] ?? 'VE',
            // ######## FIN CAMBIO GEOPOLITICO VENEZUELA
            'address'              => $row['address'] ?? null,
            'phone'                => $row['phone'] ?? null,
            'email'                => $row['email'] ?? null,
            'main'                 => (bool) ($row['main'] ?? false),
            'establishment_code'   => $row['establishment_code'] ?? null,
            'has_consigned'        => (bool) ($row['has_consigned'] ?? false),
            'consigned_id'         => $row['consigned_id'] ?? null,
            'department_id'        => $departmentId,
            'province_id'          => $provinceId,
            'district_id'          => $districtId,
        ];
    }

    public function destroy($id)
    {
        try {

            $person = Person::findOrFail($id);
            $person_type = ($person->type == 'customers') ? 'Cliente' : 'Proveedor';

            if ($person->isVariousClients()) {
                return [
                    'success' => false,
                    'message' => 'El cliente Clientes - Varios es un registro por defecto del sistema, no se puede eliminar'
                ];
            }

            $person->delete();

            return [
                'success' => true,
                'message' => $person_type . ' eliminado con éxito'
            ];

        } catch (Exception $e) {

            return ($e->getCode() == '23000') ? ['success' => false, 'message' => "El {$person_type} esta siendo usado por otros registros, no puede eliminar"] : ['success' => false, 'message' => "Error inesperado, no se pudo eliminar el {$person_type}"];

        }

    }

    public function import(Request $request)
    {
        if ($request->hasFile('file')) {
            try {
                $import = new PersonsImport();
                $import->import($request->file('file'), null, Excel::XLSX);
                $data = $import->getData();
                return [
                    'success' => true,
                    'message' => __('app.actions.upload.success'),
                    'data' => $data
                ];
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'message' => $e->getMessage()
                ];
            }
        }
        return [
            'success' => false,
            'message' => __('app.actions.upload.error'),
        ];
    }

    public function getLocationCascade()
    {
        $locations = [];
        $departments = Department::where('active', true)->get();
        foreach ($departments as $department) {
            $children_provinces = [];
            foreach ($department->provinces as $province) {
                $children_districts = [];
                foreach ($province->districts as $district) {
                    $children_districts[] = [
                        'value' => $district->id,
                        // ######## INICIO CAMBIO GEOPOLITICO VENEZUELA
                        'label' => $district->description
                        // ######## FIN CAMBIO GEOPOLITICO VENEZUELA
                    ];
                }
                $children_provinces[] = [
                    'value' => $province->id,
                    'label' => $province->description,
                    'children' => $children_districts
                ];
            }
            $locations[] = [
                'value' => $department->id,
                'label' => $department->description,
                'children' => $children_provinces
            ];
        }

        return $locations;
    }


    public function enabled($type, $id)
    {

        $person = Person::findOrFail($id);
        $person->enabled = $type;
        $person->save();

        $type_message = ($type) ? 'habilitado' : 'inhabilitado';

        return [
            'success' => true,
            'message' => "Cliente {$type_message} con éxito"
        ];

    }

    public function export($type, Request $request)
    {

        $d_start = null;
        $d_end = null;
        $period = $request->period;

        switch ($period) {
            case 'month':
                $d_start = Carbon::parse($request->month_start . '-01')->format('Y-m-d');
                $d_end = Carbon::parse($request->month_start . '-01')->endOfMonth()->format('Y-m-d');
                break;
            case 'between_months':
                $d_start = Carbon::parse($request->month_start . '-01')->format('Y-m-d');
                $d_end = Carbon::parse($request->month_end . '-01')->endOfMonth()->format('Y-m-d');
                break;
        }

        if ($period == 'all') {
            $records = Person::where('type', $type)->get();
        } elseif ($period == 'seller') {
            $records = Person::where(['type' => $type, 'seller_id' => $request->seller_id,])->get();
        } else {
            $records = Person::where('type', $type)->whereBetween('created_at', [$d_start, $d_end])->get();
        }

        $filename = ($type == 'customers') ? 'Reporte_Clientes_' : 'Reporte_Proveedores_';

        return (new ClientExport)
            ->records($records)
            ->type($type)
            ->download($filename . Carbon::now() . '.xlsx');

    }

    public function clientsForGenerateCPE()
    {
        $typeFile = request('type');
        $filter = request('name');
        $persons = Person::without(['identity_document_type', 'country', 'department', 'province', 'district'])
            ->select('id', 'name', 'identity_document_type_id', 'number')
            ->where('type', 'customers')
            ->orderBy('name');
        if ($filter && $typeFile) {
            if ($typeFile === 'document') {
                $persons = $persons->where('number', 'like', "{$filter}%");
            }
            if ($typeFile === 'name') {
                $persons = $persons->where('name', 'like', "%{$filter}%");
            }
        }
        $persons = $persons->take(10)
            ->get();
        return response()->json([
            'success' => true,
            'data' => $persons,
        ], 200);
    }

    public function printBarCode(Request $request)
    {
        ini_set("pcre.backtrack_limit", "50000000");
        $id = $request->id;

        $record = Person::find($id);


        $pdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => [
                104.1,
                24
            ],
            'margin_top' => 2,
            'margin_right' => 2,
            'margin_bottom' => 0,
            'margin_left' => 2
        ]);
        $html = view('tenant.persons.exports.persons-barcode-id', compact('record'))->render();

        $pdf->WriteHTML($html, HTMLParserMode::HTML_BODY);

        $pdf->output('etiquetas_clientes_' . now()->format('Y_m_d') . '.pdf', 'I');

    }

    public function generateBarcode($id)
    {

        $person = Person::findOrFail($id);

        $colour = [150, 150, 150];

        $generator = new BarcodeGeneratorPNG();

        $temp = tempnam(sys_get_temp_dir(), 'person_barcode');

        file_put_contents($temp, $generator->getBarcode($person->barcode, $generator::TYPE_CODE_128, 5, 70, $colour));

        $headers = [
            'Content-Type' => 'application/png',
        ];

        return response()->download($temp, "{$person->barcode}.png", $headers);

    }

    public function getPersonByBarcode($request)
    {
        /* dd($request); */
        $value = $request;

        $customers = Person::with('addresses')->whereType('customers')
            ->where('id', $value)->get()->transform(function ($row) {
                /** @var  Person $row */
                return $row->getCollectionData();
                /* Movido al modelo */
                return [
                    'id' => $row->id,
                    'description' => $row->number . ' - ' . $row->name,
                    'name' => $row->name,
                    'number' => $row->number,
                    'identity_document_type_id' => $row->identity_document_type_id,
                    'identity_document_type_code' => $row->identity_document_type->code,
                    'addresses' => $row->addresses,
                    'address' => $row->address
                ];
            });

        return compact('customers');
    }


    /**
     *
     * Obtener puntos acumulados por cliente
     *
     * @param int $id
     * @return float
     */
    public function getAccumulatedPoints($id)
    {
        return Person::getOnlyAccumulatedPoints($id);
    }

    /**
     *
     * Busqueda de registros por coincidencia o id, data inicial,  para componente
     *
     * @param  string $type
     * @param  Request $request
     * @return array
     */
    public function searchData($type, Request $request)
    {
        $id = $request->id ?? null;
        $input = $request->input ?? null;
        $records = Person::query();

        if($id)
        {
            $records->where('id', $id)->take(self::TAKE_FOR_SEARCH_ID);
        }
        else if($input)
        {
            $records->whereFilterSearchData($request)
                    ->optionalFiltersSearchData($type)
                    ->take($this->getConfigMaxItemsSelect());
        }
        else
        {
            $records->optionalFiltersSearchData($type)
                    ->take($this->getConfigMinItemsSelect());
        }

        return $records->orderBy('name')
                        ->get()
                        ->transform(function($row) {
                            return $row->getSearchDataResource();
                        });
    }

}
