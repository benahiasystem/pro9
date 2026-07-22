<?php

namespace Modules\Marketplace\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Marketplace\Models\Category;
use Modules\Marketplace\Services\MarketplaceCache;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::orderByDesc('items_count')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'items_count' => $c->items_count,
                'is_visible' => $c->is_visible,
            ]);

        return response()->json(['data' => $categories]);
    }

    /**
     * Solo se puede cambiar el nombre visible y la visibilidad.
     *
     * El `slug` es la llave natural con la que el sync resuelve las categorías:
     * si cambiara, la siguiente sincronización crearía una categoría nueva y
     * dejaría la vieja huérfana. Por eso no se expone.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'is_visible' => ['sometimes', 'boolean'],
        ], [], ['name' => 'nombre']);

        $category = Category::findOrFail($id);
        $category->fill($data);
        $category->save();

        MarketplaceCache::bump();

        return response()->json([
            'success' => true,
            'message' => 'Categoría actualizada.',
            'data' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'items_count' => $category->items_count,
                'is_visible' => $category->is_visible,
            ],
        ]);
    }
}
