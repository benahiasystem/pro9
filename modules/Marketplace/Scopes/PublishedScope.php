<?php

namespace Modules\Marketplace\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Modules\Marketplace\Models\Store;

/**
 * Un ítem solo es público si está activo Y su tienda está aprobada.
 *
 * Se aplica como global scope en Item para que sea imposible olvidarlo en el
 * front público. Se resuelve con un join sobre marketplace_stores en vez de
 * whereHas para evitar la subconsulta en el feed, que es la query caliente.
 */
class PublishedScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        $items = $model->getTable();

        $builder
            ->where($items . '.status', $model::STATUS_ACTIVE)
            ->whereExists(function ($query) use ($items) {
                $query->select(\Illuminate\Support\Facades\DB::raw(1))
                    ->from('marketplace_stores')
                    ->whereColumn('marketplace_stores.id', $items . '.store_id')
                    ->where('marketplace_stores.status', Store::STATUS_APPROVED)
                    // «Ocultar mi tienda»: oculta la tienda Y su catálogo, en
                    // el mismo gate global que la aprobación.
                    ->whereNull('marketplace_stores.hidden_at');
            });
    }
}
