<?php

namespace App\Console\Commands;

use App\Models\Tenant\Configuration;
use App\Models\Tenant\Item;
use App\Models\Tenant\ItemImage;
use App\Models\Tenant\ItemWarehouse;
use App\Models\Tenant\Warehouse;
use App\Traits\DemoCatalogTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Item\Models\Brand;
use Modules\Item\Models\Category;

/**
 * Carga el catálogo demo (productos modelo con ficha completa y galería).
 *
 * Los productos se identifican por internal_id con prefijo IIT-, de modo que
 * el comando es idempotente y nunca choca con los productos propios del tenant.
 */
class DemoCatalogImportCommand extends Command
{
    use DemoCatalogTrait;

    /** Prefijo de internal_id que marca a los productos de este catálogo. */
    public const INTERNAL_ID_PREFIX = 'IIT-';

    protected $signature = 'demo:catalog-import
                            {--tenant= : Tenant destino: id, uuid o dominio (ej. demo.pro71.test)}
                            {--file= : Ruta del JSON de catálogo (por defecto database/data/iittala-catalog.json)}
                            {--limit=0 : Importar solo los primeros N productos}
                            {--skip-images : No descargar imágenes, solo datos}
                            {--force-images : Volver a descargar imágenes aunque ya existan}
                            {--overwrite : Sobrescribir textos y precios de productos del catálogo ya importados}
                            {--dry-run : Muestra lo que haría sin escribir en base de datos}';

    protected $description = 'Importa el catálogo de productos demo con descripciones en español, ficha técnica y galería de fotos';

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

        $products = $catalog['products'];
        $limit = (int) $this->option('limit');

        if ($limit > 0) {
            $products = array_slice($products, 0, $limit);
        }

        foreach ($websites as $website) {
            $this->useTenant($website);
            $this->line('== Tenant ' . $this->tenantLabel($website) . ' ==');
            $this->importForCurrentTenant($products);
            $this->newLine();
        }

        return self::SUCCESS;
    }

    private function importForCurrentTenant(array $products): void
    {
        $dryRun = (bool) $this->option('dry-run');
        $overwrite = (bool) $this->option('overwrite');
        $skipImages = (bool) $this->option('skip-images');

        if ($dryRun) {
            $this->warn('  Modo dry-run: no se escribe nada.');
        }

        $warehouses = Warehouse::get();
        $plasticBagTaxes = Configuration::value('amount_plastic_bag_taxes') ?? 0.10;

        $created = $updated = $skipped = $images = 0;

        $bar = $this->output->createProgressBar(count($products));
        $bar->start();

        foreach ($products as $product) {
            $internalId = $product['internal_id'];
            $item = Item::where('internal_id', $internalId)->first();

            if ($item && !$overwrite) {
                $skipped++;
                $bar->advance();

                continue;
            }

            if ($dryRun) {
                $item ? $updated++ : $created++;
                $bar->advance();

                continue;
            }

            $downloaded = $skipImages ? [] : $this->downloadGallery($product);
            $images += count($downloaded);

            DB::transaction(function () use ($product, $item, $warehouses, $plasticBagTaxes, $downloaded, $skipImages, &$created, &$updated) {
                $brand = Brand::firstOrCreate(['name' => $product['brand']]);
                $category = Category::firstOrCreate(['name' => $product['category']]);

                $attributes = $this->itemAttributes($product, $brand->id, $category->id, $plasticBagTaxes);

                // El almacén debe ir en el create: Modules\Inventory engancha Item::created
                // para registrar el stock inicial y, sin warehouse_id, cae en auth()->user().
                if ($warehouses->isNotEmpty()) {
                    $attributes['warehouse_id'] = $warehouses->first()->id;
                }

                if (!$skipImages && $downloaded !== []) {
                    $main = $downloaded[0];
                    $attributes['image'] = $main;
                    $attributes['image_medium'] = str_replace('.jpg', '_medium.jpg', $main);
                    $attributes['image_small'] = str_replace('.jpg', '_small.jpg', $main);
                }

                if ($item) {
                    $item->fill($attributes)->save();
                    $updated++;
                } else {
                    $item = Item::create($attributes);
                    $created++;
                }

                if (!$item->barcode) {
                    $item->barcode = str_pad($item->id, 12, '0', STR_PAD_LEFT);
                    $item->save();
                }

                $this->syncWarehouses($item, $warehouses);

                // La galería son las tomas adicionales: la primera foto ya es la principal.
                if (!$skipImages && count($downloaded) > 1) {
                    $this->syncGallery($item, array_slice($downloaded, 1));
                }
            });

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info("  Creados: {$created} · Actualizados: {$updated} · Omitidos: {$skipped} · Imágenes: {$images}");

        if ($skipped > 0 && !$overwrite) {
            $this->line('  (usa --overwrite para refrescar los ya importados)');
        }
    }

    /** @return array<string, mixed> */
    private function itemAttributes(array $product, int $brandId, int $categoryId, float $plasticBagTaxes): array
    {
        $hasIgv = (bool) $product['has_igv'];

        return [
            'item_type_id'                      => '01',
            'internal_id'                       => $product['internal_id'],
            'item_code'                         => $product['item_code'],
            'barcode'                           => $product['barcode'],
            'description'                       => $product['description'],
            'name'                              => $product['name'],
            'second_name'                       => $product['second_name'],
            'model'                             => $product['model'],
            'line'                              => $product['line'],
            'technical_specifications'          => $product['technical_specifications'],
            'brand_id'                          => $brandId,
            'category_id'                       => $categoryId,
            'unit_type_id'                      => $product['unit_type_id'],
            'currency_type_id'                  => $product['currency_type_id'],
            'sale_unit_price'                   => $product['sale_unit_price'],
            'purchase_unit_price'               => $product['purchase_unit_price'],
            'percentage_of_profit'              => $product['percentage_of_profit'],
            'has_igv'                           => $hasIgv,
            'purchase_has_igv'                  => $hasIgv,
            'sale_affectation_igv_type_id'      => '10',
            'purchase_affectation_igv_type_id'  => '10',
            'amount_plastic_bag_taxes'          => $plasticBagTaxes,
            'stock'                             => $product['stock'],
            'stock_min'                         => $product['stock_min'],
            'calculate_quantity'                => false,
            'has_isc'                           => false,
            'has_perception'                    => false,
            'apply_store'                       => true,
            'active'                            => true,
            'status'                            => true,
        ];
    }

    /**
     * Descarga la galería completa del producto.
     *
     * @return array<int, string> Nombres de archivo ya guardados en storage.
     */
    private function downloadGallery(array $product): array
    {
        $force = (bool) $this->option('force-images');
        $stored = [];

        foreach (array_values($product['images']) as $index => $url) {
            $baseName = 'iittala-' . $product['sku'] . '-' . ($index + 1);
            $fileName = $this->storeRemoteImage($url, $baseName, $force);

            if ($fileName === null) {
                $this->newLine();
                $this->warn("  No se pudo descargar {$url}");

                continue;
            }

            $stored[] = $fileName;
        }

        return $stored;
    }

    /** @param array<int, string> $fileNames */
    private function syncGallery(Item $item, array $fileNames): void
    {
        ItemImage::where('item_id', $item->id)->delete();

        foreach ($fileNames as $fileName) {
            ItemImage::create(['item_id' => $item->id, 'image' => $fileName]);
        }
    }

    /**
     * El stock inicial del almacén principal lo registra Modules\Inventory al
     * crear el item (con su kardex). Aquí solo se completan los almacenes
     * restantes en cero para que el producto aparezca en todos ellos.
     */
    private function syncWarehouses(Item $item, $warehouses): void
    {
        foreach ($warehouses as $warehouse) {
            ItemWarehouse::firstOrCreate(
                ['item_id' => $item->id, 'warehouse_id' => $warehouse->id],
                ['stock' => 0]
            );
        }
    }
}
