<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UserMenuPreferenceController extends Controller
{
    public function show(Request $request)
    {
        return response()->json($this->preferences($request));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'pinned_items' => 'sometimes|nullable|array|max:50',
            'pinned_items.*' => 'string|max:500',
            'menu_order' => 'sometimes|nullable|array|max:50',
            'menu_order.*' => 'string|max:500',
            'show_only_active_menu' => 'sometimes|nullable|boolean',
            'menu_hierarchy' => 'sometimes|array|max:250',
            'menu_hierarchy.*.key' => 'required|string|max:500',
            'menu_hierarchy.*.ancestors' => 'present|array|max:20',
            'menu_hierarchy.*.ancestors.*' => 'string|max:500',
        ]);

        $user = $request->user();
        $storedPinnedItems = is_array($user->pinned_items) ? $user->pinned_items : [];
        $storedMenuOrder = is_array($user->menu_order) ? $user->menu_order : [];
        $pinnedItems = $this->normalizeKeys(is_array($data['pinned_items'] ?? null)
            ? $data['pinned_items']
            : $storedPinnedItems);
        $requestedOrder = $this->normalizeKeys(is_array($data['menu_order'] ?? null)
            ? $data['menu_order']
            : $storedMenuOrder);

        $pinnedItems = $this->removeRedundantDescendants(
            $pinnedItems,
            is_array($data['menu_hierarchy'] ?? null) ? $data['menu_hierarchy'] : []
        );

        // El orden solo puede contener elementos que sigan fijados. Los nuevos
        // pines se agregan al final para que ambas listas siempre sean coherentes.
        $menuOrder = array_values(array_intersect($requestedOrder, $pinnedItems));
        foreach ($pinnedItems as $item) {
            if (!in_array($item, $menuOrder, true)) {
                $menuOrder[] = $item;
            }
        }

        $user->pinned_items = $pinnedItems;
        $user->menu_order = $menuOrder;
        if (array_key_exists('show_only_active_menu', $data) && !is_null($data['show_only_active_menu'])) {
            $user->show_only_active_menu = (bool) $data['show_only_active_menu'];
        }
        $user->save();

        return response()->json([
            'success' => true,
            'preferences' => $this->preferences($request),
        ]);
    }

    private function preferences(Request $request): array
    {
        $user = $request->user();
        $pinnedItems = $this->normalizeKeys($user->pinned_items ?? []);
        $menuOrder = array_values(array_intersect(
            $this->normalizeKeys($user->menu_order ?? []),
            $pinnedItems
        ));
        foreach ($pinnedItems as $item) {
            if (!in_array($item, $menuOrder, true)) {
                $menuOrder[] = $item;
            }
        }

        return [
            'pinned_items' => $pinnedItems,
            'menu_order' => $menuOrder,
            'show_only_active_menu' => (bool) $user->show_only_active_menu,
        ];
    }

    private function normalizeKeys($items): array
    {
        if (!is_array($items)) {
            return [];
        }

        $normalized = [];
        foreach ($items as $item) {
            if (!is_string($item)) {
                continue;
            }

            $item = trim($item);
            if ($item !== '' && !in_array($item, $normalized, true)) {
                $normalized[] = $item;
            }
        }

        return $normalized;
    }

    private function removeRedundantDescendants(array $selected, array $hierarchy): array
    {
        $selectedLookup = array_fill_keys($selected, true);
        $ancestorsByKey = [];

        foreach ($hierarchy as $entry) {
            if (!is_array($entry) || !isset($entry['key']) || !is_string($entry['key'])) {
                continue;
            }

            $key = trim($entry['key']);
            if ($key === '') {
                continue;
            }

            $ancestorsByKey[$key] = $this->normalizeKeys($entry['ancestors'] ?? []);
        }

        return array_values(array_filter($selected, function (string $key) use ($selectedLookup, $ancestorsByKey) {
            foreach ($ancestorsByKey[$key] ?? [] as $ancestor) {
                if (isset($selectedLookup[$ancestor])) {
                    return false;
                }
            }

            return true;
        }));
    }
}
