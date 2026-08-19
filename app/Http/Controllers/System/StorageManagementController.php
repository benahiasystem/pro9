<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\System\StorageCleanupConfiguration;
use App\Traits\StorageManagementTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StorageManagementController extends Controller
{
    use StorageManagementTrait;

    //No eliminar los download_tray_zip/download_tray_zip -> reportes de segunda cola, la información de cola ya ha sido eliminado por lo tanto no puede regenerarse
    const PACKAGE_DELETE = [
        'pdf',
        'sale_note',
        'purchase',
        'purchase_order',
        'quotation',
        'income',
    ];

    /**
     * Nombre y explicacion de cada carpeta borrable
     *
     * El nombre tecnico de la carpeta no le dice nada al cliente: necesita saber
     * que documentos pierde y si vuelven a generarse. Las claves son las mismas
     * de PACKAGE_DELETE, lo que no este aca cae al nombre de la carpeta
     */
    const PACKAGE_DETAILS = [
        'pdf' => [
            'label' => 'Comprobantes electrónicos',
            'description' => 'PDF de facturas, boletas y notas de crédito o débito. Se vuelven a generar al descargar o reimprimir el comprobante.',
        ],
        'sale_note' => [
            'label' => 'Notas de venta',
            'description' => 'PDF de las notas de venta emitidas. Se vuelven a generar al descargar o reimprimir la nota.',
        ],
        'purchase' => [
            'label' => 'Compras',
            'description' => 'PDF de las compras registradas. Se vuelven a generar al descargar el registro de compra.',
        ],
        'purchase_order' => [
            'label' => 'Órdenes de compra',
            'description' => 'PDF de las órdenes de compra. Se vuelven a generar al descargar o reimprimir la orden.',
        ],
        'quotation' => [
            'label' => 'Cotizaciones',
            'description' => 'PDF de las cotizaciones emitidas. Se vuelven a generar al descargar o reimprimir la cotización.',
        ],
        'income' => [
            'label' => 'Ingresos de dinero',
            'description' => 'PDF de los ingresos de dinero registrados. Se vuelven a generar al descargar el ingreso.',
        ],
    ];

    // dia del mes limitado a 28 para que la programacion corra todos los meses,
    // febrero no tiene 29, 30 ni 31
    const MAX_DAY_OF_MONTH = 28;

    const FREQUENCY_LABELS = [
        StorageCleanupConfiguration::FREQUENCY_DAILY => 'Diario',
        StorageCleanupConfiguration::FREQUENCY_WEEKLY => 'Semanal',
        StorageCleanupConfiguration::FREQUENCY_MONTHLY => 'Mensual',
    ];

    const DAY_OF_WEEK_LABELS = [
        'Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado',
    ];

    public function index()
    {
        return view('system.storage-management.index');
    }

    public function records()
    {
        // se mide la partición donde viven los archivos, no la raíz del sistema
        $base_path = storage_path();

        $total_space = disk_total_space($base_path);
        $free_space = disk_free_space($base_path);

        // ambas devuelven false si la ruta no es accesible (open_basedir, permisos)
        $measured = ($total_space !== false && $free_space !== false && $total_space > 0);

        $storages = DB::table('websites')
            ->leftJoin('hostnames', 'hostnames.website_id', '=', 'websites.id')
            ->leftJoin('clients', 'clients.hostname_id', '=', 'hostnames.id')
            ->select('websites.uuid', 'clients.name', 'clients.number', 'hostnames.fqdn')
            ->get()
            ->map(function ($website) {
                return [
                    'uuid' => $website->uuid,
                    // un website sin cliente asociado se identifica por su uuid
                    'description' => $website->name ?: $website->uuid,
                    'number' => $website->number,
                    'fqdn' => $website->fqdn,
                    'space' => $this->getDirectorySize($this->getTenantPath($website->uuid)),
                ];
            })
            ->sortByDesc('space')
            ->values();

        return [
            'measured' => $measured,
            'total_space' => $measured ? $total_space : null,
            'free_space' => $measured ? $free_space : null,
            // en apfs los volúmenes comparten espacio, restar es la única forma confiable
            'used_space' => $measured ? $total_space - $free_space : null,
            'tenants_space' => $storages->sum('space'),
            'storages' => $storages,
        ];
    }

    /**
     * Empresas disponibles para programar, con el nombre que ve el admin
     *
     * @return \Illuminate\Support\Collection indexada por website_id
     */
    private function getWebsiteOptions()
    {
        return DB::table('websites')
            ->leftJoin('hostnames', 'hostnames.website_id', '=', 'websites.id')
            ->leftJoin('clients', 'clients.hostname_id', '=', 'hostnames.id')
            ->select('websites.id', 'websites.uuid', 'clients.name', 'hostnames.fqdn')
            ->get()
            ->map(function ($website) {
                return [
                    'id' => $website->id,
                    'uuid' => $website->uuid,
                    // un website sin cliente asociado se identifica por su uuid
                    'description' => $website->name ?: $website->uuid,
                    'fqdn' => $website->fqdn,
                ];
            })
            ->keyBy('id');
    }

    /**
     * Carpetas borrables con su nombre y explicacion para el cliente
     *
     * @return \Illuminate\Support\Collection
     */
    private function getPackageOptions()
    {
        return collect(self::PACKAGE_DELETE)
            ->map(function ($package) {
                return [
                    'package' => $package,
                    'label' => self::PACKAGE_DETAILS[$package]['label'] ?? $package,
                    'description' => self::PACKAGE_DETAILS[$package]['description'] ?? '',
                ];
            });
    }

    /**
     * Nombre legible de una carpeta
     */
    private function getPackageLabel(string $package): string
    {
        return self::PACKAGE_DETAILS[$package]['label'] ?? $package;
    }

    /**
     * Opciones para el formulario de programacion
     */
    public function configurationTables()
    {
        $frequencies = collect(self::FREQUENCY_LABELS)
            ->map(function ($label, $value) {
                return ['value' => $value, 'label' => $label];
            })
            ->values();

        $days_of_week = collect(self::DAY_OF_WEEK_LABELS)
            ->map(function ($label, $value) {
                return ['value' => $value, 'label' => $label];
            })
            ->values();

        return [
            'websites' => $this->getWebsiteOptions()->values(),
            'packages' => $this->getPackageOptions()->values(),
            'frequencies' => $frequencies,
            'days_of_week' => $days_of_week,
            'max_day_of_month' => self::MAX_DAY_OF_MONTH,
        ];
    }

    /**
     * Programaciones registradas, con el nombre de la empresa resuelto
     */
    public function configurations()
    {
        $websites = $this->getWebsiteOptions();

        $records = StorageCleanupConfiguration::orderBy('id', 'desc')
            ->get()
            ->map(function ($record) use ($websites) {

                $website = $websites->get($record->website_id);

                return [
                    'id' => $record->id,
                    'website_id' => $record->website_id,
                    'description' => $website['description'] ?? '—',
                    'packages' => $record->packages,
                    // el formulario trabaja con la clave de la carpeta, la tabla muestra el nombre
                    'package_labels' => collect($record->packages ?: [])
                        ->map(function ($package) {
                            return $this->getPackageLabel($package);
                        })
                        ->values(),
                    'frequency' => $record->frequency,
                    'frequency_label' => $this->buildScheduleLabel($record),
                    // el formulario los necesita para reconstruir la fila al editar
                    'day_of_week' => $record->day_of_week,
                    'day_of_month' => $record->day_of_month,
                    'time' => $record->time,
                    'active' => $record->active,
                    'last_run_at' => optional($record->last_run_at)->format('Y-m-d H:i'),
                ];

            });

        return ['records' => $records];
    }

    /**
     * Descripcion legible de cuando corre una programacion
     */
    private function buildScheduleLabel(StorageCleanupConfiguration $record): string
    {
        switch ($record->frequency) {
            case StorageCleanupConfiguration::FREQUENCY_WEEKLY:
                return 'Cada ' . (self::DAY_OF_WEEK_LABELS[$record->day_of_week] ?? '—');
            case StorageCleanupConfiguration::FREQUENCY_MONTHLY:
                return "Cada día {$record->day_of_month} del mes";
            default:
                return 'Todos los días';
        }
    }

    /**
     * Crea o actualiza una programacion
     */
    public function storeConfiguration(Request $request)
    {
        $id = $request->input('id');

        $request->validate([
            // una sola programacion por empresa, al editar se ignora la propia fila
            'website_id' => [
                'required',
                'integer',
                'exists:websites,id',
                Rule::unique('storage_cleanup_configurations', 'website_id')->ignore($id),
            ],
            'packages' => 'required|array|min:1',
            'packages.*' => ['required', 'string', Rule::in(self::PACKAGE_DELETE)],
            'frequency' => ['required', Rule::in(StorageCleanupConfiguration::FREQUENCIES)],
            'day_of_week' => [
                Rule::requiredIf($request->input('frequency') === StorageCleanupConfiguration::FREQUENCY_WEEKLY),
                'nullable', 'integer', 'between:0,6',
            ],
            'day_of_month' => [
                Rule::requiredIf($request->input('frequency') === StorageCleanupConfiguration::FREQUENCY_MONTHLY),
                'nullable', 'integer', 'between:1,' . self::MAX_DAY_OF_MONTH,
            ],
            'time' => 'required|date_format:H:i',
            'active' => 'required|boolean',
        ], [], [
            'website_id' => 'empresa',
            'packages' => 'carpetas',
            'frequency' => 'frecuencia',
            'day_of_week' => 'día de la semana',
            'day_of_month' => 'día del mes',
            'time' => 'hora',
        ]);

        $frequency = $request->input('frequency');

        $data = [
            'website_id' => $request->input('website_id'),
            'packages' => $request->input('packages'),
            'frequency' => $frequency,
            // el dia que no corresponde a la frecuencia se limpia, si no queda
            // un valor viejo que confunde al leer la fila
            'day_of_week' => $frequency === StorageCleanupConfiguration::FREQUENCY_WEEKLY
                ? $request->input('day_of_week')
                : null,
            'day_of_month' => $frequency === StorageCleanupConfiguration::FREQUENCY_MONTHLY
                ? $request->input('day_of_month')
                : null,
            'time' => $request->input('time') . ':00',
            'active' => $request->input('active'),
        ];

        StorageCleanupConfiguration::updateOrCreate(['id' => $id], $data);

        return [
            'success' => true,
            'message' => $id ? 'Programación actualizada' : 'Programación registrada',
        ];
    }

    /**
     * Elimina una programacion
     */
    public function destroyConfiguration($id)
    {
        StorageCleanupConfiguration::findOrFail($id)->delete();

        return [
            'success' => true,
            'message' => 'Programación eliminada',
        ];
    }

    /**
     * Peso de cada carpeta que puede limpiarse en un tenant
     *
     * Alimenta el dialogo: el admin necesita ver cuanto ocupa cada paquete
     * antes de decidir que borra
     */
    public function packages(string $uuid)
    {
        $tenant_path = $this->getValidatedTenantPath($uuid);

        $packages = $this->getPackageOptions()
            ->map(function ($package) use ($tenant_path) {

                $path = $this->getPackagePath($tenant_path, $package['package']);

                return $package + [
                    'files' => $this->countFiles($path),
                    'space' => $this->getDirectorySize($path),
                ];

            })
            ->values();

        return [
            'packages' => $packages,
            'total_space' => $packages->sum('space'),
        ];
    }

    /**
     * Elimina los archivos de los paquetes indicados
     */
    public function clean(string $uuid, Request $request)
    {
        $request->validate([
            'packages' => 'required|array|min:1',
            // la lista blanca es lo unico que decide que rutas son borrables
            'packages.*' => ['required', 'string', Rule::in(self::PACKAGE_DELETE)],
        ]);

        $tenant_path = $this->getValidatedTenantPath($uuid);

        $deleted_files = 0;
        $freed_space = 0;

        foreach ($request->input('packages') as $package) {

            $result = $this->deleteFilesFromPath($this->getPackagePath($tenant_path, $package));

            $deleted_files += $result['deleted_files'];
            $freed_space += $result['freed_space'];

        }

        return [
            'success' => true,
            'message' => "Se eliminaron {$deleted_files} archivo(s), " . $this->formatBytes($freed_space) . ' liberados',
            'deleted_files' => $deleted_files,
            'freed_space' => $freed_space,
        ];
    }
}
