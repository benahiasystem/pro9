<?php
namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\SeriesRequest;
use App\Models\Tenant\Catalogs\DocumentType;
use App\Models\Tenant\Configuration;
use App\Models\Tenant\Series;
use App\Services\SeriesCodeGenerator;
use App\Services\SalesDocumentTypePolicy;
use Illuminate\Http\Request;
use Modules\Document\Models\SeriesConfiguration;

class SeriesController extends Controller
{
    public function create()
    {
        return view('tenant.series.form');
    }

    /**
     * Listado de series del establecimiento, enriquecido para la nueva UI:
     * categoría (tab), tipo (label), correlativo inicial, uso (in_use), dedicado/grupo.
     */
    public function records($establishmentId, $document_type = null)
    {
        \App\Services\SeriesAdministration::authorize((int) $establishmentId);
        $query = Series::where('establishment_id', $establishmentId)
            ->with(['series_configurations', 'device_group']);

        if ($this->isNrus()) {
            $query->whereIn('document_type_id', SeriesCodeGenerator::nrusDocumentTypeIds());
        }

        if (!empty($document_type)) {
            $query->where('document_type_id', $document_type);
        }

        $data = $query->get()->map(function (Series $serie) {
            $type = SeriesCodeGenerator::typeByDocumentType($serie->document_type_id);
            $correlative = $serie->series_configurations ? (int) $serie->series_configurations->number : 1;

            return [
                'id'                       => $serie->id,
                'establishment_id'         => $serie->establishment_id,
                'document_type_id'         => $serie->document_type_id,
                'number'                   => $serie->number,
                'contingency'              => (bool) $serie->contingency,
                'dedicated'                => (bool) $serie->dedicated,
                'in_use'                   => \App\Services\SeriesNumbering::used($serie),
                'series_device_group_id'   => $serie->series_device_group_id,
                'group_name'               => optional($serie->device_group)->name,
                'category'                 => $type['category'] ?? SeriesCodeGenerator::categoryForDocumentType($serie->document_type_id),
                'sort_order'               => $type['sort_order'] ?? 999999,
                'document_type_description' => $type['label'] ?? $serie->document_type_id,
                'correlative'              => $correlative,
            ];
        });

        return ['data' => $data];
    }

    /**
     * Datos para el formulario: tipos de documento, catálogo de series y estado del toggle dedicado.
     */
    public function tables()
    {
        \App\Services\SeriesAdministration::authorize();
        $document_types          = DocumentType::OnlyAvaibleDocuments()->get();
        $is_nrus                 = $this->isNrus();
        $series_types            = SeriesCodeGenerator::availableTypes($is_nrus);
        $enable_dedicated_series = (bool) optional(Configuration::first())->enable_dedicated_series;

        if ($is_nrus) {
            $document_types = $document_types
                ->whereIn('id', SeriesCodeGenerator::nrusDocumentTypeIds())
                ->values();
        }

        return compact('document_types', 'series_types', 'enable_dedicated_series', 'is_nrus');
    }

    /**
     * Crear (o editar) una serie. Acepta dedicado/contingencia y el correlativo inicial.
     */
    public function store(SeriesRequest $request)
    {
        if (is_string($request->input('number')) || $request->input('number') === null) {
            $request->merge(['number' => \App\Services\SeriesNumbering::normalizeCode($request->input('number'))]);
        }
        return \App\Services\SeriesAdministration::transaction(function () use ($request) {
            $validate_series = $this->validateSeries($request);
            if (!$validate_series['success']) return $validate_series;

            $id = $request->input('id');
            \App\Services\SeriesAdministration::authorize((int) $request->input('establishment_id'));
            $series = $id ? Series::lockForUpdate()->findOrFail($id) : new Series();
            if ($id && ((int) $series->establishment_id !== (int) $request->input('establishment_id') || \App\Services\SeriesNumbering::used($series))) {
                throw \Illuminate\Validation\ValidationException::withMessages(['series' => 'La serie utilizada no se puede modificar ni mover de sucursal.']);
            }
            $groupId = $request->input('series_device_group_id');
            if ($groupId && (! $request->boolean('dedicated') || ! \App\Models\Tenant\SeriesDeviceGroup::where('establishment_id', $request->input('establishment_id'))->where('id', $groupId)->exists())) abort(422, 'Grupo de series incompatible.');

            $series->establishment_id       = $request->input('establishment_id');
            $series->document_type_id       = $request->input('document_type_id');
            $series->number                 = $request->input('number');
            $series->contingency            = (bool) $request->input('contingency', false);
            $series->dedicated              = (bool) $request->input('dedicated', false);
            $series->series_device_group_id = $request->input('series_device_group_id');
            $series->save();

            // Correlativo inicial: solo configurable mientras la serie no tenga comprobantes.
            if (!$series->in_use) {
                $this->saveCorrelative($series, (int) $request->input('correlative', 1));
            }

            return [
                'success' => true,
                'message' => ($id) ? 'Serie editada con éxito' : 'Serie registrada con éxito',
            ];

        });
    }

    /**
     * Actualizar el correlativo inicial de una serie existente (bloqueado si ya está en uso).
     */
    public function updateCorrelative(Request $request, $id)
    {
        return \App\Services\SeriesAdministration::transaction(function () use ($request, $id) {
            $series = Series::lockForUpdate()->findOrFail($id);

            if (\App\Services\SeriesNumbering::used($series)) {
                return ['success' => false, 'message' => 'La serie ya tiene comprobantes: no se puede cambiar el correlativo.'];
            }

            $request->validate(['correlative' => 'required|integer|min:1|max:2147483647']);
            $this->saveCorrelative($series, (int) $request->input('correlative', 1));

            return ['success' => true, 'message' => 'Correlativo actualizado'];

        });
    }

    /**
     * Persistir/limpiar el correlativo inicial (SeriesConfiguration). number=1 => sin configuración.
     */
    private function saveCorrelative(Series $series, int $correlative)
    {
        $correlative = max(1, $correlative);
        $config = SeriesConfiguration::firstOrNew(['series_id' => $series->id]);

        if ($correlative > 1) {
            $config->series           = $series->number;
            $config->number           = $correlative;
            $config->document_type_id = $series->document_type_id;
            $config->save();
        } elseif ($config->exists) {
            $config->delete();
        }
    }

    /**
     * Siguiente código libre para un prefijo (modo Auto), escaneando todas las sucursales.
     */
    public function nextCode(Request $request)
    {
        \App\Services\SeriesAdministration::authorize();
        $prefix = (string) $request->input('prefix', '');

        return ['number' => $prefix === '' ? '' : app(SeriesCodeGenerator::class)->nextCode($prefix)];
    }

    /**
     * Activar/desactivar el modo "Dedicado" del tenant (muestra el tab Dedicado).
     */
    public function toggleDedicated(Request $request)
    {
        return \App\Services\SeriesAdministration::transaction(function () use ($request) {
            \App\Services\SeriesAdministration::authorize();
            $enable = (bool) $request->input('enable', false);
            $config = Configuration::first();
            $config->enable_dedicated_series = $enable;
            $config->save();

            return [
                'success'                 => true,
                'enable_dedicated_series' => $enable,
                'message'                 => $enable ? 'Modo dedicado activado' : 'Modo dedicado desactivado',
            ];

        });
    }

    /**
     * Validar duplicidad de la serie (mismo tipo de documento y número), excluyéndose a sí misma.
     */
    public function validateSeries(SeriesRequest $request)
    {
        // ########## INICIO CAMBIO QUITAR BOLETAS A CRÉDITO
        if (SalesDocumentTypePolicy::isProhibitedNewSeries(
            (string) $request->document_type_id,
            (string) $request->number
        )) {
            return [
                'success' => false,
                'message' => 'No se permiten nuevas series de Boleta ni series BC/BD asociadas a Boleta.',
            ];
        }
        // ######### FIN CAMBIO QUITAR BOLETAS A CRÉDITO

        if ($this->isNrus() && ! in_array($request->document_type_id, SeriesCodeGenerator::nrusDocumentTypeIds(), true)) {
            // ########## INICIO CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
            return ['success' => false, 'message' => 'Para empresas NRUS sólo está disponible la serie de Nota de venta.'];
            // ######### FIN CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
        }

        $query = Series::where([
            ['document_type_id', $request->document_type_id],
            ['number', $request->number],
        ]);
        if ($request->number === '') {
            $query->where('establishment_id', $request->input('establishment_id'));
        }

        if ($request->input('id')) {
            $query->where('id', '!=', $request->input('id'));
        }

        if ($query->first()) {
            return ['success' => false, 'message' => 'La serie ya ha sido registrada'];
        }

        return ['success' => true, 'message' => null];
    }

    /**
     * Indica si el tenant actual es NRUS.
     *
     * @return bool
     */
    private function isNrus(): bool
    {
        return (bool) optional(Configuration::first())->isNrus();
    }

    /**
     * Eliminar serie. Bloqueado si ya tiene comprobantes emitidos (in_use).
     */
    public function destroy($id)
    {
        return \App\Services\SeriesAdministration::transaction(function () use ($id) {
            $item = Series::lockForUpdate()->findOrFail($id);

            if (\App\Services\SeriesNumbering::used($item)) {
                return ['success' => false, 'message' => 'No se puede eliminar: la serie ya tiene comprobantes emitidos.'];
            }

            SeriesConfiguration::where('series_id', $item->id)->delete();
            $item->delete();

            return ['success' => true, 'message' => 'Serie eliminada con éxito'];

        });
    }
}
