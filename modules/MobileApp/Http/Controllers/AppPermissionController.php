<?php

namespace Modules\MobileApp\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Tenant\User;
use Modules\MobileApp\Http\Resources\AppPermissionResource;
use Modules\MobileApp\Models\AppModule;


class AppPermissionController extends Controller
{


    /**
     * Listado de permisos asignados por usuario.
     *
     * @return array
     */
    public function records()
    {
        $records = User::with('app_modules')->get()->map(function ($user) {
            return [
                'id' => $user->id,
                'app_modules' => $user->app_modules->map(function ($app_module) {
                    return $app_module->getPermissionsApp();
                })->values(),
            ];
        })->values();

        return compact('records');
    }


    /**
     * @return array
     */
    public function record($id)
    {
        return new AppPermissionResource(User::findOrFail($id));
    }


    /**
     * @return array
     */
    public function tables()
    {
        $app_configuration = app(AppConfigurationController::class)->record();
        $pos_document_types = collect(auth()->user()->getPosDocumentTypes())->pluck('module');

        return compact('app_configuration', 'pos_document_types');
    }


    /**
     *
     * Actualizar configuracion gráfica de la app
     *
     * @param  Request $request
     * @return array
     */
    public function store(Request $request)
    {

        $user = User::findOrFail($request->id);

        if ($user->locked || !$user->active) {
            $status = $user->locked ? 'suspendido' : 'inhabilitado';

            return [
                'success' => false,
                'message' => "Usuario {$status}: no se pueden modificar sus permisos.",
            ];
        }

        $app_modules = collect($request->app_modules)->where('checked', true)->pluck('id')->toArray();
        $user->app_modules()->sync($app_modules);

        return [
            'success' => true,
            'message' => 'Permisos actualizados correctamente',
        ];

    }


}
