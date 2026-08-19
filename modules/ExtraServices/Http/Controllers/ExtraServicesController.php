<?php

namespace Modules\ExtraServices\Http\Controllers;

use Modules\ExtraServices\Models\ExtraServices;
use Modules\ExtraServices\Http\Requests\ExtraServicesRequest;
use Modules\ExtraServices\Http\Resources\ExtraServicesResource;
use Modules\ExtraServices\Services\ApidocsService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Contracts\Support\Renderable;

class ExtraServicesController extends Controller
{
    public function __construct(ApidocsService $apidocsService)
    {
        $this->apidocsService = $apidocsService;
    }
    /**
     * Renderiza la vista principal del módulo de servicios extra.
     * @return Renderable
     */
    public function index()
    {
        return view('extraservices::index');
    }

    /**
     * Obtiene la primera fila de configuración del módulo.
     * @return \Illuminate\Http\JsonResponse    
     */
    public function records()
    {
        $configuration = ExtraServices::firstOrCreate([]);
        return new ExtraServicesResource($configuration);
    }

    /**
     * Almacena o actualiza la configuración del módulo.
     * @param ExtraServicesRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(ExtraServicesRequest $request){
        $data = $request->validated();

        $message_service = [];
        if ($request->input('isActiveApidocs') === true) {
            if (!$this->apidocsService->isActiveService()){
                $data['isActiveApidocs'] = false;
                $message_service['apidocs'] = 'No cuenta con el servicio de apidocs activo.';
            }
        }

        $configuration = ExtraServices::updateOrCreate([], $data);
        return response()->json([
            'success' => true,
            'message' => 'Configuración guardada.',
            'message_service' => $message_service,
            'data' => new ExtraServicesResource($configuration)
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}