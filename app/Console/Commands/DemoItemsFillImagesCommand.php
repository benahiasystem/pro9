<?php

namespace App\Console\Commands;

use App\Models\Tenant\Item;
use App\Models\Tenant\ItemImage;
use App\Traits\DemoCatalogTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Completa SOLO las fotos de los productos que ya existen en el tenant.
 *
 * No toca nombre, descripción, código interno, precios ni stock: esos productos
 * se usan como ejemplos de listas de precios (con y sin IGV) y su texto debe
 * quedar intacto.
 */
class DemoItemsFillImagesCommand extends Command
{
    use DemoCatalogTrait;

    /** Marcador que usa el sistema cuando un producto no tiene foto. */
    private const PLACEHOLDER = 'imagen-no-disponible.jpg';

    /**
     * Palabra en el nombre del producto existente => [categoría del catálogo,
     * término preferido dentro de esa categoría]. Las categorías son amplias,
     * así que el segundo valor afina la elección: "Habitación Cuarto simple"
     * cae en Decoración e iluminación, pero se le busca una lámpara.
     * El orden importa: gana la primera palabra que aparezca en el nombre.
     */
    private const AFFINITY = [
        'plato'      => ['Vajilla', 'plato'],
        'entrada'    => ['Vajilla', 'plato'],
        'postre'     => ['Vajilla', 'plato'],
        'arroz'      => ['Vajilla', 'bowl'],
        'chaufa'     => ['Vajilla', 'bowl'],
        'sopa'       => ['Vajilla', 'bowl'],
        'ensalada'   => ['Vajilla', 'bowl'],
        'almuerzo'   => ['Vajilla', 'bowl'],
        'menu'       => ['Vajilla', 'bowl'],
        'cereal'     => ['Vajilla', 'bowl'],
        'medicina'   => ['Vajilla', 'bowl'],
        'farmacia'   => ['Vajilla', 'bowl'],
        'taza'       => ['Vajilla', 'taza'],
        'mug'        => ['Vajilla', 'mug'],
        'cafe'       => ['Vajilla', 'taza'],
        'infusion'   => ['Vajilla', 'taza'],
        'copa'       => ['Cristalería', 'copa'],
        'vino'       => ['Cristalería', 'copa'],
        'espumante'  => ['Cristalería', 'copa'],
        'pisco'      => ['Cristalería', 'copa'],
        'vaso'       => ['Cristalería', 'vaso'],
        'cerveza'    => ['Cristalería', 'vaso'],
        'gaseosa'    => ['Cristalería', 'vaso'],
        'trago'      => ['Cristalería', 'vaso'],
        'cuchillo'   => ['Cubertería', 'cuchillo'],
        'tenedor'    => ['Cubertería', 'tenedor'],
        'cuchara'    => ['Cubertería', 'cuchara'],
        'cubierto'   => ['Cubertería', 'cuchara'],
        'pack'       => ['Cubertería', 'set'],
        'combo'      => ['Cubertería', 'set'],
        'juego'      => ['Cubertería', 'set'],
        'set'        => ['Cubertería', 'set'],
        'jarra'      => ['Artículos para servir', 'jarra'],
        'agua'       => ['Artículos para servir', 'jarra'],
        'litro'      => ['Artículos para servir', 'jarra'],
        'jugo'       => ['Artículos para servir', 'jarra'],
        'bebida'     => ['Artículos para servir', 'jarra'],
        'refresco'   => ['Artículos para servir', 'jarra'],
        'bandeja'    => ['Artículos para servir', 'bandeja'],
        'torta'      => ['Artículos para servir', 'torta'],
        'fuente'     => ['Artículos para servir', 'fuente'],
        'olla'       => ['Ollas y sartenes', 'olla'],
        'cacerola'   => ['Ollas y sartenes', 'olla'],
        'sarten'     => ['Ollas y sartenes', 'sarten'],
        'cocina'     => ['Ollas y sartenes', 'olla'],
        'jarron'     => ['Jarrones y maceteros', 'jarron'],
        'flor'       => ['Jarrones y maceteros', 'jarron'],
        'macetero'   => ['Jarrones y maceteros', 'macetero'],
        'planta'     => ['Jarrones y maceteros', 'macetero'],
        'vela'       => ['Velas y candelabros', 'vela'],
        'aroma'      => ['Velas y candelabros', 'vela'],
        'lampara'    => ['Decoración e iluminación', 'lampara'],
        'luz'        => ['Decoración e iluminación', 'lampara'],
        'foco'       => ['Decoración e iluminación', 'lampara'],
        'habitacion' => ['Decoración e iluminación', 'lampara'],
        'cuarto'     => ['Decoración e iluminación', 'lampara'],
        'hotel'      => ['Decoración e iluminación', 'lampara'],
        'hospedaje'  => ['Decoración e iluminación', 'lampara'],
        'decoracion' => ['Decoración e iluminación', 'decorativo'],
        'envio'      => ['Decoración e iluminación', 'corazones'],
        'delivery'   => ['Decoración e iluminación', 'corazones'],
        'flete'      => ['Decoración e iluminación', 'corazones'],
        'penalidad'  => ['Decoración e iluminación', 'esferas'],
        'comision'   => ['Decoración e iluminación', 'esferas'],
        'servicio'   => ['Decoración e iluminación', 'esferas'],
        'mantel'     => ['Textiles del hogar', 'mantel'],
        'servilleta' => ['Textiles del hogar', 'servilleta'],
        'manta'      => ['Textiles del hogar', 'manta'],
        'textil'     => ['Textiles del hogar', 'manta'],
        'tela'       => ['Textiles del hogar', 'manta'],
    ];

    protected $signature = 'demo:items-fill-images
                            {--tenant= : Tenant destino: id, uuid o dominio (ej. demo.pro71.test)}
                            {--file= : Ruta del JSON de catálogo (por defecto database/data/iittala-catalog.json)}
                            {--all : Reasignar foto también a los productos que ya tienen una}
                            {--gallery : Además de la foto principal, crear la galería de fotos adicionales}
                            {--include-catalog : Incluir también los productos importados por demo:catalog-import}
                            {--force-images : Volver a descargar las imágenes aunque ya existan}
                            {--dry-run : Muestra lo que haría sin escribir en base de datos}';

    protected $description = 'Asigna fotos a los productos existentes sin modificar su nombre, descripción ni precios';

    public function handle(): int
    {
        $websites = $this->resolveTenants($this->option('tenant'));

        if ($websites->isEmpty()) {
            $this->error('Indica el tenant destino con --tenant=demo.pro71.test (o su id/uuid).');

            return self::FAILURE;
        }

        $catalog = $this->readCatalog($this->option('file'));

        if (!$catalog) {
            return self::FAILURE;
        }

        foreach ($websites as $website) {
            $this->useTenant($website);
            $this->line('== Tenant ' . $this->tenantLabel($website) . ' ==');
            $this->fillForCurrentTenant($catalog['products']);
            $this->newLine();
        }

        return self::SUCCESS;
    }

    /** @param array<int, array<string, mixed>> $products */
    private function fillForCurrentTenant(array $products): void
    {
        $dryRun = (bool) $this->option('dry-run');
        $withGallery = (bool) $this->option('gallery');

        if ($dryRun) {
            $this->warn('  Modo dry-run: no se escribe nada.');
        }

        $items = $this->targetItems();

        if ($items->isEmpty()) {
            $this->info('  No hay productos pendientes de foto.');

            return;
        }

        $this->info("  Productos a completar: {$items->count()}");

        // Las variaciones heredan la foto de su producto padre.
        $parents = $items->whereNull('parent_item_id');
        $variations = $items->whereNotNull('parent_item_id');

        $assignedByItem = [];
        $usedSkus = [];
        $updated = $inherited = $failed = 0;

        $bar = $this->output->createProgressBar($items->count());
        $bar->start();

        foreach ($parents as $item) {
            $product = $this->pickProduct($item, $products, $usedSkus);
            $usedSkus[] = $product['sku'];

            if ($dryRun) {
                $assignedByItem[$item->id] = $product;
                $updated++;
                $bar->advance();

                continue;
            }

            $fileNames = $this->storeGallery($product, $withGallery);

            if ($fileNames === []) {
                $failed++;
                $bar->advance();

                continue;
            }

            $this->applyImages($item, $fileNames, $withGallery);
            $assignedByItem[$item->id] = $product;
            $updated++;
            $bar->advance();
        }

        foreach ($variations as $item) {
            $parent = Item::find($item->parent_item_id);

            if (!$parent || $parent->image === self::PLACEHOLDER) {
                $bar->advance();

                continue;
            }

            if (!$dryRun) {
                $item->image = $parent->image;
                $item->image_medium = $parent->image_medium;
                $item->image_small = $parent->image_small;
                $item->save();
            }

            $inherited++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info("  Fotos asignadas: {$updated} · Variaciones que heredaron la foto del padre: {$inherited}" . ($failed ? " · Fallidas: {$failed}" : ''));

        if ($this->getOutput()->isVerbose()) {
            foreach ($assignedByItem as $itemId => $product) {
                $this->line("   #{$itemId} <- {$product['description']}");
            }
        }
    }

    /** @return \Illuminate\Support\Collection<int, Item> */
    private function targetItems()
    {
        $query = Item::query()->orderBy('id');

        if (!$this->option('all')) {
            $query->where(function ($q) {
                $q->where('image', self::PLACEHOLDER)->orWhereNull('image')->orWhere('image', '');
            });
        }

        if (!$this->option('include-catalog')) {
            $query->where(function ($q) {
                $q->whereNull('internal_id')
                    ->orWhere('internal_id', 'not like', DemoCatalogImportCommand::INTERNAL_ID_PREFIX . '%');
            });
        }

        return $query->get();
    }

    /**
     * Elige el producto del catálogo cuya categoría encaje con el nombre del
     * producto existente; si no hay coincidencia, reparte de forma pareja.
     *
     * @param array<int, array<string, mixed>> $products
     * @param array<int, string>               $usedSkus
     *
     * @return array<string, mixed>
     */
    private function pickProduct(Item $item, array $products, array $usedSkus): array
    {
        $haystack = ' ' . Str::lower(Str::ascii((string) $item->description . ' ' . (string) $item->name)) . ' ';

        foreach (self::AFFINITY as $keyword => [$category, $preferred]) {
            if (!str_contains($haystack, Str::ascii($keyword))) {
                continue;
            }

            $matches = array_values(array_filter(
                $products,
                fn($p) => $p['category'] === $category && !in_array($p['sku'], $usedSkus, true)
            ));

            if ($matches === []) {
                continue;
            }

            foreach ($matches as $match) {
                if (str_contains(Str::lower(Str::ascii($match['description'])), Str::ascii($preferred))) {
                    return $match;
                }
            }

            return $matches[0];
        }

        $available = array_values(array_filter($products, fn($p) => !in_array($p['sku'], $usedSkus, true)));

        // Si ya se usaron todas las fotos, se vuelve a empezar por el catálogo completo.
        $pool = $available !== [] ? $available : $products;

        return $pool[count($usedSkus) % count($pool)];
    }

    /**
     * @return array<int, string> Archivos guardados; el primero es la foto principal.
     */
    private function storeGallery(array $product, bool $withGallery): array
    {
        $force = (bool) $this->option('force-images');
        $urls = $withGallery ? array_values($product['images']) : [$product['images'][0]];
        $stored = [];

        foreach ($urls as $index => $url) {
            $fileName = $this->storeRemoteImage($url, 'iittala-' . $product['sku'] . '-' . ($index + 1), $force);

            if ($fileName !== null) {
                $stored[] = $fileName;
            }
        }

        return $stored;
    }

    /**
     * Escribe únicamente las tres columnas de imagen (y la galería si se pidió).
     *
     * @param array<int, string> $fileNames
     */
    private function applyImages(Item $item, array $fileNames, bool $withGallery): void
    {
        $main = $fileNames[0];

        $item->image = $main;
        $item->image_medium = str_replace('.jpg', '_medium.jpg', $main);
        $item->image_small = str_replace('.jpg', '_small.jpg', $main);
        $item->save();

        if ($withGallery && count($fileNames) > 1) {
            ItemImage::where('item_id', $item->id)->delete();

            foreach (array_slice($fileNames, 1) as $fileName) {
                ItemImage::create(['item_id' => $item->id, 'image' => $fileName]);
            }
        }
    }
}
