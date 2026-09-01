<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\System\BusinessTurnRequest;
use App\Models\System\BusinessTurn;
use App\Models\System\Client;
use App\Models\System\Module;
use App\Models\System\Plan;
use Exception;
use Hyn\Tenancy\Environment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BusinessTurnController extends Controller
{
    public function index()
    {
        return view('system.business_turns.index');
    }

    public function records()
    {
        return BusinessTurn::selectable()->sorted()->get();
    }

    public function record($id)
    {
        return BusinessTurn::findOrFail($id);
    }

    /**
     * Mismos árboles de módulos y apps que usan los formularios de cliente y plan.
     */
    public function tables()
    {
        $modules = Module::with('levels')
            ->where('sort', '<', 14)
            ->where('value', '!=', 'production_app')
            ->orderBy('sort')
            ->get()
            ->each(function ($module) {
                return $this->prepareModules($module);
            });

        $apps = Module::with('levels')
            ->where('sort', '>', 13)
            ->where('value', '!=', 'production_app')
            ->orderBy('sort')
            ->get()
            ->each(function ($module) {
                return $this->prepareModules($module);
            });

        return compact('modules', 'apps');
    }

    public function store(BusinessTurnRequest $request)
    {
        $id = $request->input('id');

        if ($id && in_array((int) $id, BusinessTurn::RESERVED_IDS, true)) {
            return [
                'success' => false,
                'message' => 'Ese giro de negocio es una opción calculada del formulario y no se puede editar.',
            ];
        }

        $record = $id ? BusinessTurn::findOrFail($id) : new BusinessTurn();

        if ($record->locked) {
            return [
                'success' => false,
                'message' => "El giro de negocio «{$record->name}» es de solo lectura y no se puede modificar.",
            ];
        }

        if (!$record->exists) {
            $record->value = $this->generateUniqueValue($request->input('name'));
            $record->sort = $request->input('sort') ?? ((int) BusinessTurn::max('sort') + 1);
        } elseif ($request->filled('sort')) {
            $record->sort = (int) $request->input('sort');
        }

        $record->name = $request->input('name');
        $record->description = $request->input('description');
        $record->modules = array_values(array_unique(array_map('intval', $request->input('modules', []))));
        $record->levels = array_values(array_unique($request->input('levels', [])));
        $record->apps = array_values(array_unique(array_map('intval', $request->input('apps', []))));
        $record->app_levels = array_values(array_unique($request->input('app_levels', [])));
        $record->active = (bool) $request->input('active', true);
        $record->save();

        return [
            'success' => true,
            'message' => $id ? 'Giro de negocio actualizado con éxito' : 'Giro de negocio registrado con éxito',
        ];
    }

    /**
     * Activa o desactiva un giro. Aplica también a los bloqueados: sus módulos
     * son de solo lectura, pero se puede decidir si se ofrece o no en el
     * formulario de empresa y de plan.
     */
    public function changeActive(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|integer',
            'active' => 'required|boolean',
        ]);

        $record = BusinessTurn::findOrFail($data['id']);

        $record->active = (bool) $data['active'];
        $record->save();

        return [
            'success' => true,
            'message' => 'Estado del giro de negocio actualizado con éxito',
        ];
    }

    public function destroy($id)
    {
        $record = BusinessTurn::findOrFail($id);

        if ($record->is_default) {
            return [
                'success' => false,
                'message' => "El giro de negocio «{$record->name}» es predeterminado y no se puede eliminar.",
            ];
        }

        $usage = $this->findUsage((int) $record->id);

        if ($usage) {
            return [
                'success' => false,
                'message' => "No se puede eliminar: el giro de negocio está asignado a {$usage}.",
            ];
        }

        $record->delete();

        return [
            'success' => true,
            'message' => 'Giro de negocio eliminado con éxito',
        ];
    }

    /**
     * Devuelve una descripción del primer plan o cliente que usa el giro, o null
     * si no lo usa nadie.
     *
     * @param  int $business_turn_id
     *
     * @return string|null
     */
    private function findUsage(int $business_turn_id)
    {
        $plan = Plan::whereNotNull('module_permissions')
            ->get()
            ->first(function (Plan $plan) use ($business_turn_id) {
                return (int) data_get($plan->module_permissions, 'business') === $business_turn_id;
            });

        if ($plan) {
            return "el plan «{$plan->name}»";
        }

        $tenancy = app(Environment::class);

        foreach (Client::with('hostname')->get() as $client) {
            try {
                $tenancy->tenant($client->hostname->website);

                $config = DB::connection('tenant')->table('configurations')->first();
                $tenant_plan = json_decode($config->plan ?? 'null');

                if ((int) data_get($tenant_plan, 'module_permissions.business') === $business_turn_id) {
                    return "el cliente «{$client->name}»";
                }
            } catch (Exception $e) {
                continue;
            }
        }

        return null;
    }

    /**
     * @param  string|null $name
     *
     * @return string
     */
    private function generateUniqueValue($name)
    {
        $base = Str::slug((string) $name, '_');
        $base = $base !== '' ? Str::limit($base, 50, '') : 'business_turn';

        $value = $base;
        $suffix = 1;

        while (BusinessTurn::where('value', $value)->exists()) {
            $value = "{$base}_{$suffix}";
            $suffix++;
        }

        return $value;
    }

    private function prepareModules(Module $module): Module
    {
        $levels = [];
        foreach ($module->levels as $level) {
            array_push($levels, [
                'id' => "{$module->id}-{$level->id}",
                'description' => $level->description,
                'module_id' => $level->module_id,
                'is_parent' => false,
            ]);
        }
        unset($module->levels);
        $module->is_parent = true;
        $module->childrens = $levels;

        return $module;
    }
}
