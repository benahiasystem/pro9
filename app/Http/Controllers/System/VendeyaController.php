<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Services\System\VendeyaConfigurationService;
use App\Services\System\VendeyaLogoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendeyaController extends Controller
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

    public function record(VendeyaConfigurationService $service, VendeyaLogoService $logoService): JsonResponse
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
        VendeyaConfigurationService $service,
        VendeyaLogoService $logoService
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
            'message' => 'El logo de Vendeya se actualizó correctamente.',
            'useSystemLogo' => $useSystemLogo,
            'hasCustomLogo' => $logoService->hasCustomLogo(),
            'hasSystemLogo' => $logoService->hasSystemLogo(),
            'logoUrl' => $logoService->previewUrl($version),
        ]);
    }

    public function updateBrandName(Request $request, VendeyaConfigurationService $service): JsonResponse
    {
        $validated = $this->validateBrandName($request);
        $configuration = $service->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'El nombre de Vendeya se actualizó correctamente.',
            'brandName' => $configuration['brandName'],
        ]);
    }

    public function destroyLogo(
        VendeyaConfigurationService $service,
        VendeyaLogoService $logoService
    ): JsonResponse {
        $logoService->deleteCustomLogo();
        $version = time();

        $service->update([
            'useSystemLogo' => false,
            'logoVersion' => $version,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'El logo personalizado de Vendeya se eliminó y se restauró el logo predeterminado.',
            'useSystemLogo' => false,
            'hasCustomLogo' => false,
            'hasSystemLogo' => $logoService->hasSystemLogo(),
            'logoUrl' => $logoService->previewUrl($version),
        ]);
    }

    public function updateColors(Request $request, VendeyaConfigurationService $service): JsonResponse
    {
        $validated = $request->validate($this->colorRules());
        $configuration = $service->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'La paleta de colores de Vendeya se actualizó correctamente.',
            'colors' => array_intersect_key($configuration, array_flip(self::COLOR_KEYS)),
        ]);
    }

    public function update(Request $request, VendeyaConfigurationService $service): JsonResponse
    {
        $request->merge(['brandName' => trim((string) $request->input('brandName'))]);
        $rules = array_merge([
            'brandName' => ['required', 'string', 'max:100'],
        ], $this->colorRules());

        $configuration = $service->update($request->validate($rules));

        return response()->json([
            'success' => true,
            'message' => 'La configuración de Vendeya se actualizó correctamente.',
            'configuration' => $configuration,
        ]);
    }

    private function validateBrandName(Request $request): array
    {
        $request->merge(['brandName' => trim((string) $request->input('brandName'))]);

        return $request->validate([
            'brandName' => ['required', 'string', 'max:100'],
        ]);
    }

    private function colorRules(): array
    {
        $rules = [];
        foreach (self::COLOR_KEYS as $key) {
            $rules[$key] = ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];
        }

        return $rules;
    }
}
