<?php

namespace Modules\ExtraServices\Http\Controllers;

use Modules\ExtraServices\Models\ExtraService;
use Modules\ExtraServices\Services\ApiDocsService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Contracts\Support\Renderable;

class ExtraServicesController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index()
    {
        return view('extraservices::index');
    }

    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create()
    {
        return view('extraservices::create');
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show($id)
    {
        return view('extraservices::show');
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function edit($id)
    {
        return view('extraservices::edit');
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Renderable
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy($id)
    {
        //
    }


    /**
     * Activar un servicio específico.
     * @param Request $request
     * @return Response
     */
    public function activateService(Request $request)
    {
        $serviceName = $request->input('service');

        $activate = false;

        if ($serviceName === 'apidocs') {
            $apiDocsService = new ApiDocsService();
            $activate = $apiDocsService->isApiDocsActive();
            $service = ExtraService::where('service', $serviceName)->first();
        } else {
            return response()->json([
                'success' => true,
                'message' => 'Servicio no reconocido o no encontrado.',
            ]);
        }

        if ($activate) {
            $service->is_active = true;
            $service->save();
            return response()->json([
                'success' => true,
                'message' => 'Se activó el servicio correctamente.',
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo activar el servicio.',
            ]);
        }
    }

    public function inactivateService(Request $request)
    {
        $serviceName = $request->input('service');

        $service = ExtraService::where('service', $serviceName)->first();

        if ($service) {
            $service->is_active = false;
            $service->save();

            return response()->json([
                'success' => true,
                'message' => "Se desactivó el servicio correctamente.",
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => "Servicio no reconocido o no encontrado.",
            ]);
        }
    }
}
