<?php

namespace Modules\Marketplace\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Marketplace\Models\Setting;
use Modules\Marketplace\Services\MarketplaceCache;
use Modules\Marketplace\Services\Settings;

class SettingController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Settings::all(),
            'terms_url' => Settings::termsUrl(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'is_enabled' => ['sometimes', 'boolean'],
            'title' => ['sometimes', 'required', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'community_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'hero_title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'hero_highlight' => ['sometimes', 'nullable', 'string', 'max:60'],
            'whatsapp_greeting' => ['sometimes', 'required', 'string', 'max:255'],
            'whatsapp_cart_greeting' => ['sometimes', 'required', 'string', 'max:255'],
            'currency_symbol' => ['sometimes', 'required', 'string', 'max:8'],
            'max_items_per_store' => ['sometimes', 'required', 'integer', 'min:1', 'max:5000'],
            // 0 = nunca bloquear automáticamente.
            'auto_block_reports' => ['sometimes', 'required', 'integer', 'min:0', 'max:10000'],
            // 0 = sin umbral, el ranking se muestra desde la primera recomendación.
            'ranking_threshold' => ['sometimes', 'required', 'integer', 'min:0', 'max:10000'],
            'items_per_page' => ['sometimes', 'required', 'integer', 'min:6', 'max:96'],
            'report_reasons' => ['sometimes', 'array', 'min:1'],
            'report_reasons.*' => ['required', 'string', 'max:60'],
        ]);

        // Un solo bump al final en vez de uno por clave: guardar los ajustes es
        // una sola acción del admin.
        foreach ($data as $key => $value) {
            $setting = Setting::where('key', $key)->first();

            if (! $setting) {
                continue;
            }

            $setting->value = Setting::serialize($value, $setting->type);
            $setting->save();
        }

        Settings::forget();
        MarketplaceCache::bump();

        return response()->json([
            'success' => true,
            'message' => 'Ajustes guardados.',
            'data' => Settings::all(),
        ]);
    }
}
