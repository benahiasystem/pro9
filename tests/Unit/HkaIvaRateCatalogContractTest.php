<?php

namespace Tests\Unit;

use App\Support\Venezuela\Localization;
use Tests\TestCase;

// ########## INICIO CAMBIO AFECTACIÓN IVA
class HkaIvaRateCatalogContractTest extends TestCase
{
    public function test_hka_rate_catalog_has_exactly_the_six_historical_reference_rows(): void
    {
        $tables = (require database_path('seeders/data/tenant_initial_data.php'))['tables'];
        self::assertSame(['id'], $tables['cat_iva_rate_types']['key_columns']);
        self::assertSame([
            ['id' => 'R', 'description' => 'Alícuota Reducida', 'percentage' => '8.00', 'tax_kind' => 'IVA'],
            ['id' => 'G', 'description' => 'Alícuota General', 'percentage' => '16.00', 'tax_kind' => 'IVA'],
            ['id' => 'A', 'description' => 'Alícuota Adicional (suntuario)', 'percentage' => '31.00', 'tax_kind' => 'IVA'],
            ['id' => 'E', 'description' => 'Exento, Exonerado o No Gravado', 'percentage' => '0.00', 'tax_kind' => 'IVA'],
            ['id' => 'P', 'description' => 'Percibido', 'percentage' => '0.00', 'tax_kind' => 'IVA'],
            ['id' => 'IGTF', 'description' => 'Impuesto a las Grandes Transacciones Financieras', 'percentage' => '3.00', 'tax_kind' => 'IGTF'],
        ], $tables['cat_iva_rate_types']['rows']);
        self::assertCount(6, array_unique(array_column($tables['cat_iva_rate_types']['rows'], 'id')));
    }

    public function test_operative_iva_rate_comes_from_configuration_instead_of_the_hka_catalog(): void
    {
        $original = config('venezuela.tax.rate');
        try {
            config()->set('venezuela.tax.rate', 0.17);
            self::assertSame(0.17, Localization::taxRate());
            self::assertSame(17.0, Localization::taxPercentage());
            self::assertSame(['10', '20'], Localization::selectableAffectationIds());
            $tables = (require database_path('seeders/data/tenant_initial_data.php'))['tables'];
            self::assertSame('16.00', $tables['cat_iva_rate_types']['rows'][1]['percentage']);
        } finally {
            config()->set('venezuela.tax.rate', $original);
        }
    }
}
// ######### FIN CAMBIO AFECTACIÓN IVA
