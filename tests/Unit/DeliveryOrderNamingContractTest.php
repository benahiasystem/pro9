<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class DeliveryOrderNamingContractTest extends TestCase
{
    public function test_delivery_order_contract_preserves_codes_and_internal_dispatch_names(): void
    {
        $root = dirname(__DIR__, 2);
        $migration = file_get_contents($root . '/database/migrations/tenant/2026_09_04_000003_rename_dispatch_document_types_to_delivery_orders.php');
        $moduleMigration = file_get_contents($root . '/database/migrations/2026_09_04_000003_rename_dispatch_module_to_delivery_orders.php');
        $skill = file_get_contents($root . '/.codex/skills/mantener-ordenes-entrega-pro9/SKILL.md');

        foreach (['09', '31', '71', '72'] as $documentTypeId) {
            $this->assertStringContainsString("'{$documentTypeId}'", $migration);
        }

        $this->assertStringContainsString("whereRaw('BINARY `id` = ?'", $migration);
        $this->assertStringContainsString("'dispatches'", $moduleMigration);
        $this->assertStringContainsString("'guia'", $moduleMigration);
        $this->assertStringContainsString('Orden de entrega', $skill);
        $this->assertStringContainsString('compatibilidad', mb_strtolower($skill));
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
}
