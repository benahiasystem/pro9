<?php

namespace Tests\Unit;

use App\Support\Venezuela\Localization;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

// ######## INICIO PRUEBAS CONTRATO GEOPOLITICO VENEZUELA
class VenezuelaGeopoliticalContractTest extends TestCase
{
    /** @test */
    public function it_exposes_the_venezuelan_geopolitical_contract(): void
    {
        self::assertSame('VE', Localization::countryId());
        self::assertSame('14', Localization::locationId('department', 14));
        self::assertSame('0229', Localization::locationId('province', 229));
        self::assertSame('000619', Localization::locationId('district', 619));
        self::assertSame('municipio antonio jose de sucre', Localization::normalizeLocationName(
            '  MUNICIPIO   ANTÓNIO JOSÉ DE SUCRE '
        ));

        $this->expectException(InvalidArgumentException::class);
        Localization::locationId('unknown', 1);
    }

    /** @test */
    public function consolidated_migrations_preserve_the_venezuelan_hierarchy(): void
    {
        $expectations = [
            'countries' => ['`id` char(2)'],
            'departments' => ['`id` char(2)'],
            'provinces' => ['`id` char(4)', '`department_id` char(2)'],
            'districts' => ['`id` char(6)', '`province_id` char(4)'],
        ];

        foreach ($expectations as $table => $fragments) {
            $source = $this->migrationSource($table);
            foreach ($fragments as $fragment) {
                self::assertStringContainsString($fragment, $source, $table);
            }
        }

        self::assertStringContainsString("DEFAULT 'VE'", $this->migrationSource('origin_addresses'));

        $foreignKeys = $this->foreignKeyMigrationSource();
        self::assertStringContainsString('ALTER TABLE `provinces` ADD CONSTRAINT', $foreignKeys);
        self::assertStringContainsString('ALTER TABLE `districts` ADD CONSTRAINT', $foreignKeys);
    }

    /** @test */
    public function initial_data_contains_only_the_complete_venezuelan_hierarchy(): void
    {
        $seeder = (string) file_get_contents(
            database_path('seeders/TenantMigrationDataSeeder.php')
        );
        $payload = require database_path('seeders/data/venezuela_geopolitical_data.php');
        $tables = $payload['tables'];
        $countries = collect($tables['countries']['rows'])->keyBy('id');
        $departments = collect($tables['departments']['rows'])->keyBy('id');
        $provinces = collect($tables['provinces']['rows'])->keyBy('id');
        $districts = collect($tables['districts']['rows'])->keyBy('id');

        self::assertCount(25, $departments);
        self::assertCount(335, $provinces);
        self::assertCount(1138, $districts);
        self::assertStringContainsString('venezuela_geopolitical_data.php', $seeder);
        self::assertStringContainsString('array_replace($payload[\'tables\'], $geography[\'tables\'])', $seeder);
        self::assertTrue($countries->has('VE'));
        self::assertFalse($countries->has('PE'));
        self::assertSame('Miranda', $departments['14']['description']);
        self::assertSame('14', $provinces['0229']['department_id']);
        self::assertSame('Chacao', $provinces['0229']['description']);
        self::assertSame('0229', $districts['000619']['province_id']);
        self::assertSame('Chacao', $districts['000619']['description']);
        $macarapana = collect($tables['districts']['rows'])->firstWhere('id', '000781');
        self::assertSame('MACARAPANA', mb_strtoupper($macarapana['description'], 'UTF-8'));

        foreach ($provinces as $province) {
            self::assertTrue($departments->has($province['department_id']));
        }
        foreach ($districts as $district) {
            self::assertTrue($provinces->has($district['province_id']));
        }
    }

    /** @test */
    public function runtime_locations_use_an_isolated_versioned_cache_and_clean_parish_labels(): void
    {
        $helper = (string) file_get_contents(app_path('helper.php'));

        self::assertStringContainsString('Department::query()', $helper);
        self::assertStringContainsString("->with('provinces', 'provinces.districts')", $helper);
        self::assertStringContainsString('locations:v2:{$tenantDatabase}:{$countryId}', $helper);
        self::assertStringContainsString(
            "\$countryId !== config('venezuela.country_id', 'VE')",
            $helper
        );
        self::assertStringContainsString("'label' => func_str_to_upper_utf8(\$district->description)", $helper);
        self::assertStringNotContainsString('\$district->id . " - " . \$district->description', $helper);
        self::assertStringNotContainsString('file_get_contents', $helper);
        self::assertStringNotContainsString('database_path(', $helper);
        self::assertFileDoesNotExist(database_path('data/venezuela_locations.php'));
    }

    /** @test */
    public function duplicated_location_cascaders_show_only_the_parish_name(): void
    {
        $sources = [
            app_path('Http/Controllers/Tenant/PersonController.php'),
            base_path('modules/Suscription/Http/Controllers/PaymentReceiptSuscriptionController.php'),
            base_path('modules/Suscription/Http/Controllers/SuscriptionController.php'),
            base_path('modules/FullSuscription/Http/Controllers/PaymentReceiptFullSuscriptionController.php'),
            base_path('modules/FullSuscription/Http/Controllers/FullSuscriptionController.php'),
        ];

        foreach ($sources as $path) {
            $source = (string) file_get_contents($path);
            self::assertStringContainsString("'label' => \$district->description", $source, $path);
            self::assertStringNotContainsString('\$district->id . " - " . \$district->description', $source, $path);
        }
    }

    /** @test */
    public function active_sources_have_no_peruvian_geopolitical_defaults(): void
    {
        $violations = [];
        $roots = [app_path(), base_path('modules'), resource_path('js'), config_path()];
        $patterns = [
            '/[\'\"]PE[\'\"]/',
            '/\b150101\b/',
            '/\b010101\b/',
            '/America\/Lima/',
        ];

        foreach ($roots as $root) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (!$file->isFile() || !in_array($file->getExtension(), ['php', 'js', 'vue'], true)) {
                    continue;
                }

                $path = $file->getPathname();
                // ######## INICIO EXCLUSIÓN DEL PUENTE HISTÓRICO PE A VE ########
                if (
                    str_contains($path, '/views_bk/')
                    || str_contains($path, '_bk.')
                    || str_ends_with($path, '/Support/Venezuela/ExistingTenantMigrator.php')
                ) {
                    continue;
                }
                // ######## FIN EXCLUSIÓN DEL PUENTE HISTÓRICO PE A VE ########

                $source = (string) file_get_contents($path);
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $source) === 1) {
                        $violations[] = $path;
                        break;
                    }
                }
            }
        }

        self::assertSame([], array_values(array_unique($violations)));
        self::assertSame('America/Caracas', config('app.timezone'));
        self::assertSame('000619', config('tenant.ubigeo_default_invoice_import'));
    }

    /** @test */
    public function location_resolvers_require_a_unique_match_inside_the_parent(): void
    {
        $department = (string) file_get_contents(app_path('Models/Tenant/Catalogs/Department.php'));
        $province = (string) file_get_contents(app_path('Models/Tenant/Catalogs/Province.php'));
        $district = (string) file_get_contents(app_path('Models/Tenant/Catalogs/District.php'));

        self::assertStringContainsString("\$matches->count() !== 1", $department);
        self::assertStringContainsString("->where('department_id', \$departmentId)", $province);
        self::assertStringContainsString("\$matches->count() !== 1", $province);
        self::assertStringContainsString("->where('province_id', \$provinceId)", $district);
        self::assertStringContainsString("\$matches->count() !== 1", $district);
    }

    private function migrationSource(string $table): string
    {
        $files = glob(database_path("migrations/tenant/*_create_{$table}_table.php")) ?: [];
        self::assertCount(1, $files, "Debe existir una migración consolidada para {$table}.");

        return (string) file_get_contents($files[0]);
    }

    private function foreignKeyMigrationSource(): string
    {
        $files = glob(database_path('migrations/tenant/*_add_tenant_foreign_keys.php')) ?: [];
        self::assertCount(1, $files, 'Debe existir una única migración de claves foráneas.');

        return (string) file_get_contents($files[0]);
    }
}
// ######## FIN PRUEBAS CONTRATO GEOPOLITICO VENEZUELA
