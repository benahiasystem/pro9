<?php

namespace Tests\Unit;

use Tests\TestCase;

// ######## INICIO CONTRATO INSTALACIÓN NUEVA ########
class FreshTenantInstallationContractTest extends TestCase
{
    public function test_no_existing_tenant_conversion_remains(): void
    {
        foreach (['Support/Venezuela/ExistingTenantMigrator.php', 'Support/Venezuela/IdentityDocumentCatalogMigrator.php', 'Console/Commands/MigrateExistingTenantToVenezuela.php', 'Console/Commands/RegenerateEcommerceQuotationPdfsCommand.php'] as $file) {
            self::assertFileDoesNotExist(app_path($file));
        }
        $source = file_get_contents(database_path('seeders/TenantMigrationDataSeeder.php'));
        self::assertStringNotContainsString('information_schema', $source);
        self::assertStringNotContainsString('migrateExisting', $source);
        self::assertStringNotContainsString("'PE'", $source);
    }

    public function test_initial_order_states_and_services_are_current(): void
    {
        $data = require database_path('seeders/data/tenant_initial_data.php');
        $statuses = $data['tables']['status_orders']['rows'];
        self::assertCount(13, $statuses);
        self::assertSame(range(1, 13), array_column($statuses, 'id'));
        self::assertSame('Pago pendiente', $statuses[0]['description']);
        self::assertSame('Completado', $statuses[12]['description']);
        self::assertTrue((bool) $statuses[1]['action_mark_payment']);
        self::assertTrue((bool) $statuses[4]['action_discount_stock']);
        self::assertTrue((bool) $statuses[11]['action_void_order']);
        self::assertSame(['DELIVERY-ECOM'], array_column($data['tables']['items']['rows'], 'internal_id'));
    }
}
// ######## FIN CONTRATO INSTALACIÓN NUEVA ########
