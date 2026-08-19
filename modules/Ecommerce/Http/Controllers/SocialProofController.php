<?php

namespace Modules\Ecommerce\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\ConfigurationEcommerce;
use App\Services\Tenant\FrequentlyBoughtTogetherService;
use Illuminate\Http\Request;

class SocialProofController extends Controller
{
    public function frequentlyBoughtTogether(Request $request, FrequentlyBoughtTogetherService $service, $itemId = null)
    {
        $limit = (int) $request->query('limit', FrequentlyBoughtTogetherService::DEFAULT_LIMIT);

        if ($request->filled('item_ids')) {
            $ids = collect(explode(',', (string) $request->query('item_ids')))
                ->map(fn ($id) => (int) trim($id))
                ->filter()
                ->values()
                ->all();

            return [
                'success' => true,
                'data' => $service->forItems($ids, $limit),
            ];
        }

        $itemId = (int) ($itemId ?: $request->query('item_id', 0));

        return [
            'success' => true,
            'data' => $service->forItem($itemId, $limit),
        ];
    }

    public function trustBadges()
    {
        $config = ConfigurationEcommerce::first();
        $preferences = $config && is_array($config->preferences) ? $config->preferences : [];

        $enabled = (bool) ($preferences['trust_badges_enabled'] ?? true);
        $badges = $preferences['trust_badges'] ?? null;

        if (! is_array($badges) || $badges === []) {
            $badges = $this->defaultTrustBadges();
        }

        return [
            'success' => true,
            'enabled' => $enabled,
            'data' => $enabled ? array_values($badges) : [],
        ];
    }

    /**
     * @return array<int, array{icon: string, text: string}>
     */
    public static function defaultTrustBadges(): array
    {
        return [
            ['icon' => 'shield', 'text' => 'Pago 100% Seguro'],
            ['icon' => 'refresh', 'text' => 'Devolución Garantizada'],
            ['icon' => 'truck', 'text' => 'Envío Rápido'],
        ];
    }
}
