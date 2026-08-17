<?php

// ########### INICIO CAMBIO VALIDACIÓN PREVIA IMPORTACIÓN ITEMS
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ItemImportPipelineSourceContractTest extends TestCase
{
    /** @test */
    public function validation_is_executed_before_the_existing_items_importer(): void
    {
        $source = file_get_contents(__DIR__ . '/../../app/Http/Controllers/Tenant/ItemController.php');
        $validationPosition = strpos($source, '$workbookValidator->validate');
        $importPosition = strpos($source, 'new ItemsImport()', $validationPosition);

        self::assertNotFalse($validationPosition);
        self::assertNotFalse($importPosition);
        self::assertLessThan($importPosition, $validationPosition);
        self::assertStringContainsString('if (!$validation->passes())', $source);
        self::assertStringContainsString("DB::connection('tenant')->transaction", $source);
        self::assertStringContainsString("['token' => \$token], false", $source);
        self::assertStringContainsString("exists:tenant.warehouses,id", $source);
        self::assertStringContainsString("required|file|mimes:xlsx|max:10240", $source);
    }

    /** @test */
    public function the_frontend_requests_and_downloads_the_error_workbook_automatically(): void
    {
        $source = file_get_contents(__DIR__ . '/../../resources/js/views/tenant/items/import.vue');

        self::assertStringContainsString("this.\$http.get(url, {responseType: 'blob'})", $source);
        self::assertStringContainsString('window.URL.createObjectURL(blob)', $source);
        self::assertStringContainsString('link.download = filename', $source);
        self::assertStringContainsString('link.click()', $source);
        self::assertStringContainsString('finally {', $source);
        self::assertStringContainsString('this.loading_submit = false', $source);
    }

    /** @test */
    public function the_authenticated_download_route_and_production_bundle_expose_the_validated_flow(): void
    {
        $routes = file_get_contents(__DIR__ . '/../../routes/web.php');
        self::assertStringContainsString("items/import/validation/{token}", $routes);
        self::assertStringContainsString("->whereUuid('token')", $routes);

        $manifestPath = __DIR__ . '/../../public/build/manifest.json';
        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        self::assertIsArray($manifest);
        self::assertArrayHasKey('resources/js/app.js', $manifest);

        $assetPath = __DIR__ . '/../../public/build/' . $manifest['resources/js/app.js']['file'];
        self::assertFileExists($assetPath);
        $asset = file_get_contents($assetPath);
        self::assertStringContainsString('VALIDACION_ITEMS.xlsx', $asset);
        self::assertStringContainsString('validation_failed', $asset);
        self::assertStringContainsString('responseType:"blob"', $asset);
    }
}
// ########### FIN CAMBIO VALIDACIÓN PREVIA IMPORTACIÓN ITEMS
