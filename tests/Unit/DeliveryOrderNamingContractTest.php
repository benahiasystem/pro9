<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class DeliveryOrderNamingContractTest extends TestCase
{
    public function test_delivery_order_contract_preserves_codes_and_internal_dispatch_names(): void
    {
        $root = dirname(__DIR__, 2);
        $seed = require $root . '/database/seeders/data/tenant_initial_data.php';
        $types = array_column($seed['tables']['cat_document_types']['rows'], 'description', 'id');
        $skill = file_get_contents($root . '/.codex/skills/mantener-ordenes-entrega-pro9/SKILL.md');

        $this->assertSame('ORDEN DE ENTREGA', $types['09']);
        foreach (['31', '71', '72'] as $documentTypeId) {
            $this->assertArrayNotHasKey($documentTypeId, $types);
        }
        $modules = array_column($seed['tables']['app_modules']['rows'], 'description', 'value');
        $this->assertSame('Orden de entrega', $modules['dispatches']);
        $this->assertContains('guia', array_column($seed['tables']['modules']['rows'], 'value'));
        $this->assertStringContainsString('Orden de entrega', $skill);
        $this->assertStringContainsString('dispatch', $skill);
    }

    public function test_carrier_delivery_orders_are_not_exposed(): void
    {
        $root = dirname(__DIR__, 2);
        $files = [
            'routes/web.php',
            'modules/Dispatch/Routes/api.php',
            'resources/js/tenant-components.js',
            'resources/views/tenant/layouts/partials/sidebar.blade.php',
            'app/Models/Tenant/Catalogs/DocumentType.php',
            'app/Services/SeriesCodeGenerator.php',
        ];

        foreach ($files as $file) {
            $contents = file_get_contents($root . '/' . $file);
            $this->assertDoesNotMatchRegularExpression('/dispatch[_-]?carrier|carrier_dispatches|DispatchCarrier/ui', $contents, $file);
        }
    }

    public function test_primary_user_interfaces_use_delivery_order_wording(): void
    {
        $root = dirname(__DIR__, 2);
        $files = [
            'resources/views/tenant/layouts/partials/sidebar.blade.php',
            'resources/js/views/tenant/dispatches/index.vue',
            'resources/js/views/tenant/dispatches/form.vue',
            'resources/js/views/tenant/configurations/pdf_guide_templates.vue',
            'modules/Report/Resources/assets/js/views/guide/index.vue',
            'modules/Order/Resources/views/dispatches/templates/email.blade.php',
        ];

        foreach ($files as $file) {
            $contents = file_get_contents($root . '/' . $file);
            $this->assertMatchesRegularExpression('/[ÓO]rden(?:es)? de entrega/ui', $contents, $file);
            $this->assertDoesNotMatchRegularExpression('/Gu[ií]a(?:s)? de Remisi[oó]n|G\.R\. (?:Remitente|Transportista)/ui', $contents, $file);
        }
    }

    public function test_peruvian_vehicle_transfer_controls_are_not_exposed(): void
    {
        $root = dirname(__DIR__, 2);
        $manualPlateViews = [
            'resources/js/views/tenant/dispatches/create.vue',
            'resources/js/views/tenant/dispatches/form.vue',
            'resources/js/views/tenant/dispatches/transports/form.vue',
        ];

        foreach ($manualPlateViews as $file) {
            $contents = file_get_contents($root . '/' . $file);
            $this->assertStringNotContainsString('service_type="placa"', $contents, $file);
            $this->assertStringNotContainsString('SUNARP', $contents, $file);
        }

        $this->assertStringNotContainsString(
            'SUNARP',
            file_get_contents($root . '/modules/ApiPeruDev/Resources/assets/js/components/InputService.vue')
        );
        $this->assertStringNotContainsString(
            '<!-- <div class="card-header bg-info">',
            file_get_contents($root . '/resources/js/views/tenant/dispatches/create.vue')
        );

        $retiredDataConsumers = [
            'app/Models/Tenant/Dispatch.php',
            'app/CoreFacturalo/Requests/Inputs/DispatchInput.php',
            'database/migrations/tenant/2026_08_17_000313_create_dispatches_table.php',
            'resources/js/views/tenant/dispatches/create.vue',
        ];
        $pdfTemplates = glob($root . '/app/CoreFacturalo/Templates/pdf/*/dispatch*.blade.php');

        foreach (array_merge($retiredDataConsumers, $pdfTemplates) as $file) {
            $path = str_starts_with($file, $root) ? $file : $root . '/' . $file;
            $contents = file_get_contents($path);
            $this->assertStringNotContainsString('is_transport_m1l', $contents, $file);
            $this->assertStringNotContainsString('license_plate_m1l', $contents, $file);
            $this->assertStringNotContainsString('categoría M1', $contents, $file);
        }
    }

    public function test_semitrailer_data_is_not_exposed_or_persisted(): void
    {
        $root = dirname(__DIR__, 2);
        $consumers = [
            'app/CoreFacturalo/Facturalo.php',
            'app/CoreFacturalo/Requests/Inputs/DispatchInput.php',
            'app/Models/Tenant/Dispatch.php',
            'database/migrations/tenant/2026_08_17_000313_create_dispatches_table.php',
            'modules/Order/Helpers/OrderFormHelper.php',
            'modules/Order/Resources/assets/js/views/order_forms/form.vue',
            'resources/js/views/tenant/dispatches/create.vue',
            'resources/js/views/tenant/dispatches/form.vue',
        ];
        $templates = glob($root . '/app/CoreFacturalo/Templates/pdf/*/{dispatch*,order_form_a4}.blade.php', GLOB_BRACE);

        foreach (array_merge($consumers, $templates) as $file) {
            $path = str_starts_with($file, $root) ? $file : $root . '/' . $file;
            $contents = file_get_contents($path);
            $this->assertStringNotContainsString('secondary_license_plates', $contents, $file);
            $this->assertStringNotContainsString('license_plate_2', $contents, $file);
            $this->assertStringNotContainsString('register_number_2', $contents, $file);
            $this->assertStringNotContainsString('placa semirremolque', mb_strtolower($contents), $file);
        }
    }
}
