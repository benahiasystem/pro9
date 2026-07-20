<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Services\System\MozoConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MozoController extends Controller
{
    private const COLOR_KEYS = [
        'Primary',
        'Secondary',
        'Background',
        'Text',
        'lightText',
        'darkPrimary',
        'darkLightText',
    ];

    public function index()
    {
        return view('system.mozo.index');
    }

    public function record(MozoConfigurationService $service): JsonResponse
    {
        $configuration = $service->get();

        return response()->json($configuration);
    }

    public function updateBrandName(Request $request, MozoConfigurationService $service): JsonResponse
    {
        $request->merge([
            'brandName' => trim((string) $request->input('brandName')),
        ]);

        $validated = $request->validate([
            'brandName' => ['required', 'string', 'max:100'],
        ]);

        $configuration = $service->update([
            'brandName' => $validated['brandName'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'El nombre de Mozo se actualizó correctamente.',
            'brandName' => $configuration['brandName'],
        ]);
    }

    public function updateColors(Request $request, MozoConfigurationService $service): JsonResponse
    {
        $rules = [];
        foreach (self::COLOR_KEYS as $key) {
            $rules[$key] = ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];
        }

        $validated = $request->validate($rules);
        $configuration = $service->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'La paleta de colores de Mozo se actualizó correctamente.',
            'colors' => array_intersect_key($configuration, array_flip(self::COLOR_KEYS)),
        ]);
    }

    public function update(Request $request, MozoConfigurationService $service): JsonResponse
    {
        $request->merge([
            'brandName' => trim((string) $request->input('brandName')),
        ]);

        $rules = [
            'brandName' => ['required', 'string', 'max:100'],
        ];

        foreach (self::COLOR_KEYS as $key) {
            $rules[$key] = ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];
        }

        $validated = $request->validate($rules);
        $configuration = $service->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'La configuración de Mozo se actualizó correctamente.',
            'configuration' => $configuration,
        ]);
    }
}
