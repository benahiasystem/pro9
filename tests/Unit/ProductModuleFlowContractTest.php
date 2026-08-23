<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

// ########### INICIO CONTRATO FLUJO DE PRODUCTOS ###########
class ProductModuleFlowContractTest extends TestCase
{
    /** @test */
    public function products_and_services_use_the_same_transactional_creation_path(): void
    {
        $controller = $this->source('app/Http/Controllers/Tenant/ItemController.php');
        $item = $this->source('app/Models/Tenant/Item.php');
        $store = $this->methodBody($controller, 'public function store(ItemRequest $request)');

        self::assertStringContainsString("public const SERVICE_UNIT_TYPE = 'ZZ'", $item);
        self::assertStringContainsString('DB::beginTransaction()', $store);
        self::assertStringContainsString('$item->fill($request->all())', $store);
        self::assertStringContainsString('$item->save()', $store);
        self::assertStringContainsString('DB::commit()', $store);
        self::assertStringContainsString('DB::rollBack()', $store);
    }

    /** @test */
    public function catalog_and_shared_searches_keep_the_variation_relationship_available(): void
    {
        $item = $this->source('app/Models/Tenant/Item.php');
        $catalog = $this->source('app/Http/Controllers/Tenant/ItemController.php');
        $search = $this->source('app/Http/Controllers/SearchItemController.php');

        self::assertStringContainsString("belongsTo(Item::class, 'parent_item_id')", $item);
        self::assertStringContainsString("hasMany(Item::class, 'parent_item_id')", $item);
        self::assertStringContainsString("withCount('variations')", $catalog);
        self::assertStringContainsString("withCount('variations')", $search);
    }

    /** @test */
    public function purchases_sales_quotations_and_sale_notes_use_the_shared_product_search(): void
    {
        $search = $this->source('app/Http/Controllers/SearchItemController.php');

        foreach ([
            'getItemToPurchase',
            'getItemsToDocuments',
            'getItemsToQuotation',
            'getItemsToSaleNote',
        ] as $method) {
            self::assertStringContainsString("public static function {$method}", $search, $method);
            $body = $this->methodBody($search, "public static function {$method}");
            self::assertStringContainsString('getNotServiceItem', $body, $method.' productos');
            self::assertStringContainsString('getServiceItem', $body, $method.' servicios');
        }
    }

    /** @test */
    public function pos_vende_ya_and_restaurant_keep_their_product_visibility_queries(): void
    {
        $pos = $this->source('app/Http/Controllers/Tenant/PosController.php');
        $sellNow = $this->source('app/Http/Controllers/Tenant/Api/SellnowController.php');
        $restaurant = $this->source('modules/Restaurant/Http/Controllers/RestaurantController.php');

        self::assertStringContainsString("withCount('variations')", $pos);
        self::assertStringContainsString("whereNull('parent_item_id')", $pos);
        self::assertStringContainsString("whereNotNull('internal_id')", $sellNow);
        self::assertStringContainsString("whereIsActive()", $sellNow);
        self::assertStringContainsString("where('apply_restaurant', 1)", $restaurant);
        self::assertStringContainsString("whereNotNull('internal_id')", $restaurant);
    }

    private function methodBody(string $source, string $signature): string
    {
        $start = strpos($source, $signature);
        self::assertNotFalse($start, $signature);
        $open = strpos($source, '{', $start);
        self::assertNotFalse($open, $signature);

        $depth = 0;
        $length = strlen($source);
        for ($position = $open; $position < $length; $position++) {
            if ($source[$position] === '{') {
                $depth++;
            } elseif ($source[$position] === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($source, $open, $position - $open + 1);
                }
            }
        }

        self::fail('No se encontró el cierre de '.$signature);
    }

    private function source(string $relativePath): string
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/'.$relativePath);
        self::assertNotFalse($source, $relativePath);

        return $source;
    }
}
// ########### FIN CONTRATO FLUJO DE PRODUCTOS ###########
