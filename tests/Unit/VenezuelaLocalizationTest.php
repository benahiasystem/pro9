<?php

namespace Tests\Unit;

use App\Models\Tenant\ModelTenant;
use App\Support\Venezuela\Localization;
use InvalidArgumentException;
use Tests\TestCase;

// ########### INICIO PRUEBAS LOCALIZACIÓN VENEZUELA
class VenezuelaLocalizationTest extends TestCase
{
    /** @test */
    public function it_exposes_the_venezuelan_localization_contract(): void
    {
        self::assertSame('VE', Localization::countryId());
        self::assertSame('+58', Localization::dialCode());
        self::assertSame('VES', Localization::nationalCurrencyId());
        self::assertSame('USD', Localization::secondaryCurrencyId());
        self::assertSame('VES', ModelTenant::NATIONAL_CURRENCY_ID);
        self::assertSame('Bs.', Localization::currencySymbol('VES'));
        self::assertSame('$', Localization::currencySymbol('USD'));
        self::assertSame('Bs.', Localization::currencySymbol(null));
    }

    /**
     * @test
     * @dataProvider phoneProvider
     */
    public function it_normalizes_phone_numbers_without_duplicating_the_country_code(?string $input, ?string $expected): void
    {
        self::assertSame($expected, Localization::normalizePhone($input));
    }

    public function phoneProvider(): array
    {
        return [
            'local' => ['0412-123.45.67', '+584121234567'],
            'already venezuelan' => ['+58 412 1234567', '+584121234567'],
            'historical peru prefix' => ['+51 412 1234567', '+584121234567'],
            'empty' => ['   ', null],
            'null' => [null, null],
        ];
    }

    /** @test */
    public function it_generates_fixed_width_location_ids(): void
    {
        self::assertSame('14', Localization::locationId('department', 14));
        self::assertSame('0229', Localization::locationId('province', 229));
        self::assertSame('000619', Localization::locationId('district', 619));

        $this->expectException(InvalidArgumentException::class);
        Localization::locationId('unknown', 1);
    }

    /** @test */
    public function it_normalizes_location_names_for_database_resolution(): void
    {
        self::assertSame(
            'municipio antonio jose de sucre',
            Localization::normalizeLocationName('  MUNICIPIO   ANTÓNIO JOSÉ DE SUCRE ')
        );
        self::assertSame('chacao', Localization::normalizeLocationName('Chacao'));
    }

    /** @test */
    public function consolidated_migrations_preserve_the_venezuelan_location_structure(): void
    {
        foreach ([
            'departments' => ['CREATE TABLE `departments`', '`id` char(2)'],
            'provinces' => ['CREATE TABLE `provinces`', '`id` char(4)', '`department_id` char(2)'],
            'districts' => ['CREATE TABLE `districts`', '`id` char(6)', '`province_id` char(4)'],
        ] as $table => $fragments) {
            $source = $this->migrationSource($table);
            foreach ($fragments as $fragment) {
                self::assertStringContainsString($fragment, $source, $table);
            }
            self::assertStringNotContainsString("'id' => '150101'", $source, $table);
        }
    }

    private function migrationSource(string $table): string
    {
        $files = glob(database_path("migrations/tenant/*_create_{$table}_table.php")) ?: [];
        self::assertCount(1, $files, "Debe existir una migración consolidada para {$table}.");

        return (string) file_get_contents($files[0]);
    }
}
// ########### FIN PRUEBAS LOCALIZACIÓN VENEZUELA
