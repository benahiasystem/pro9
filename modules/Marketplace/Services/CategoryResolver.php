<?php

namespace Modules\Marketplace\Services;

use Illuminate\Support\Str;
use Modules\Marketplace\Models\Category;

/**
 * Taxonomía con cero intervención del admin.
 *
 * La app envía la categoría como texto libre; el slug es la llave natural, así
 * que "Herramientas Eléctricas", "herramientas electricas" y
 * "HERRAMIENTAS  ELÉCTRICAS" colapsan en una sola categoría.
 *
 * Limitación aceptada: un typo genera una categoría nueva. Con el volumen
 * previsto el admin lo resuelve ocultándola; no hay fusión ni fuzzy matching.
 */
class CategoryResolver
{
    /** Memo por request: un sync repite la misma categoría decenas de veces. */
    private array $memo = [];

    /**
     * Devuelve la categoría, o null si el texto viene vacío.
     * Un ítem sin categoría igual se publica; se muestra bajo "Otros".
     */
    public function resolve(?string $raw): ?Category
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return null;
        }

        $slug = Str::slug($raw);

        // Un texto que solo tiene símbolos deja el slug vacío.
        if ($slug === '') {
            return null;
        }

        if (isset($this->memo[$slug])) {
            return $this->memo[$slug];
        }

        return $this->memo[$slug] = Category::firstOrCreate(
            ['slug' => $slug],
            // El nombre visible lo fija la primera ocurrencia.
            ['name' => Str::limit(Str::title($raw), 120, '')]
        );
    }
}
