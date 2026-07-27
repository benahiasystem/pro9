<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Services\System\MozoConfigurationService;
use App\Services\System\MozoLogoService;
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

    public function record(MozoConfigurationService $service, MozoLogoService $logoService): JsonResponse
    {
        $configuration = $service->get();

        $configuration['useSystemLogo'] = (bool) ($configuration['useSystemLogo'] ?? true);
        $configuration['hasCustomLogo'] = $logoService->hasCustomLogo();
        $configuration['hasSystemLogo'] = $logoService->hasSystemLogo();
        $configuration['logoUrl'] = $logoService->previewUrl($configuration['logoVersion'] ?? null);

        return response()->json($configuration);
    }

    public function updateLogo(
        Request $request,
        MozoConfigurationService $service,
        MozoLogoService $logoService
    ): JsonResponse {
        $request->merge([
            'useSystemLogo' => filter_var($request->input('useSystemLogo'), FILTER_VALIDATE_BOOLEAN),
        ]);

        $request->validate([
            'useSystemLogo' => ['required', 'boolean'],
            'logo' => [
                'nullable',
                'file',
                'max:2048',
                function ($attribute, $value, $fail) {
                    $extension = strtolower((string) $value->getClientOriginalExtension());
                    if (!in_array($extension, ['svg', 'png', 'jpg', 'jpeg'], true)) {
                        $fail('El logo debe ser un archivo SVG, PNG o JPG.');
                    }
                },
            ],
        ], [
            'logo.max' => 'El logo no puede superar los 2MB.',
        ]);

        $useSystemLogo = (bool) $request->input('useSystemLogo');

        if ($useSystemLogo) {
            $logoService->applySystemLogo();
        } else {
            if ($request->hasFile('logo')) {
                $logoService->storeCustomLogo($request->file('logo'));
            }

            if ($logoService->hasCustomLogo()) {
                $logoService->applyCustomLogo();
            } else {
                $logoService->restoreDefaultLogo();
            }
        }

        $version = time();

        $service->update([
            'useSystemLogo' => $useSystemLogo,
            'logoVersion' => $version,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'El logo de Mozo se actualizó correctamente.',
            'useSystemLogo' => $useSystemLogo,
            'hasCustomLogo' => $logoService->hasCustomLogo(),
            'hasSystemLogo' => $logoService->hasSystemLogo(),
            'logoUrl' => $logoService->previewUrl($version),
        ]);
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

    public function destroyLogo(
        MozoConfigurationService $service,
        MozoLogoService $logoService
    ): JsonResponse {
        $logoService->deleteCustomLogo();
        $version = time();

        $service->update([
            'useSystemLogo' => false,
            'logoVersion' => $version,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'El logo personalizado de Mozo se eliminó y se restauró el logo predeterminado.',
            'useSystemLogo' => false,
            'hasCustomLogo' => false,
            'hasSystemLogo' => $logoService->hasSystemLogo(),
            'logoUrl' => $logoService->previewUrl($version),
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
