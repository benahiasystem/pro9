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
            'pinned_items.*' => 'string|max:500|distinct',
            'menu_order' => 'sometimes|nullable|array|max:50',
            'menu_order.*' => 'string|max:500|distinct',
            'show_only_active_menu' => 'sometimes|nullable|boolean',
        ]);

        $user = $request->user();
        $storedPinnedItems = is_array($user->pinned_items) ? $user->pinned_items : [];
        $storedMenuOrder = is_array($user->menu_order) ? $user->menu_order : [];
        $pinnedItems = array_values(is_array($data['pinned_items'] ?? null)
            ? $data['pinned_items']
            : $storedPinnedItems);
        $requestedOrder = array_values(is_array($data['menu_order'] ?? null)
            ? $data['menu_order']
            : $storedMenuOrder);

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

        return [
            'pinned_items' => array_values($user->pinned_items ?? []),
            'menu_order' => array_values($user->menu_order ?? []),
            'show_only_active_menu' => (bool) $user->show_only_active_menu,
        ];
    }
}
