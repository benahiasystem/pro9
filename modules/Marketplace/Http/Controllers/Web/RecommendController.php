<?php

namespace Modules\Marketplace\Http\Controllers\Web;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Marketplace\Http\Middleware\EnsureMarketplaceVisitor;
use Modules\Marketplace\Http\Requests\RecommendRequest;
use Modules\Marketplace\Models\Item;
use Modules\Marketplace\Models\Recommendation;
use Modules\Marketplace\Services\CatalogCounters;

/**
 * Recomendaciones del público: el contrapeso positivo de las denuncias.
 *
 * Un visitante puede recomendar cada producto una vez y quitarlo cuando quiera.
 * No hay cupo oculto ni descartes en silencio: la pantalla sabe en todo momento
 * qué ha recomendado —se lo dice el servidor al cargar— así que el pulgar
 * siempre refleja el estado real.
 */
class RecommendController extends Controller
{
    public function store(RecommendRequest $request): JsonResponse
    {
        // Con el scope de publicación: no se puede recomendar algo que no está
        // publicado, ni un producto de una tienda que no está aprobada.
        $item = Item::find($request->input('item_id'));
        $visitor = EnsureMarketplaceVisitor::id($request);

        if (! $item || $visitor === null) {
            return response()->json(['message' => 'Este producto ya no está disponible.'], 404);
        }

        $request->boolean('recomendar')
            ? $this->add($item, $visitor, $request->ip())
            : $this->remove($item, $visitor);

        // Los dos contadores en una pasada. Cuesta dos consultas por clic, pero
        // los deja siempre exactos: no hay incrementos que puedan desviarse.
        CatalogCounters::refreshRecommendations($item);

        // A propósito NO se invalida el cache del feed. Un contador
        // desactualizado unos minutos no rompe nada —a diferencia del de
        // categorías, donde un número que no cuadra lleva a una búsqueda
        // vacía— y purgar el módulo entero en cada pulgar dejaría el cache
        // inservible en las horas de más tráfico.
        return response()->json(['success' => true]);
    }

    /**
     * insertOrIgnore y no firstOrCreate: en Laravel 9 firstOrCreate es un SELECT
     * seguido de INSERT sin atrapar la violación de la clave única (eso llegó
     * con createOrFirst en L10). Dos POST paralelos del mismo visitante+producto
     * pasarían ambos el SELECT y el segundo INSERT chocaría con
     * mkt_reco_once_per_visitor → 500. insertOrIgnore descarta el duplicado en
     * la propia sentencia, que es justo el "no pasa nada" que queremos.
     *
     * created_at a mano: insertOrIgnore no dispara los timestamps del modelo.
     */
    private function add(Item $item, string $visitor, ?string $ip): void
    {
        Recommendation::query()->insertOrIgnore([
            'visitor_id' => $visitor,
            'item_id' => $item->id,
            'store_id' => $item->store_id,
            'ip' => $ip,
            'created_at' => now(),
        ]);
    }

    private function remove(Item $item, string $visitor): void
    {
        Recommendation::where('visitor_id', $visitor)
            ->where('item_id', $item->id)
            ->delete();
    }
}
