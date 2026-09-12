<?php

namespace Tests\Unit;

use App\Http\Controllers\Tenant\UnitTypeController;
use App\Models\Tenant\Catalogs\UnitType;
use InvalidArgumentException;
use Illuminate\Validation\Rules\Exists;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

// ######## INICIO CONTRATO UNIDADES DE MEDIDA VENEZUELA ########
class VenezuelaUnitTypeContractTest extends TestCase
{
    /** @test */
    public function initial_unit_types_match_the_venezuelan_contract(): void
    {
        $expected = [
            'BOL' => 'Bolsa', 'BOT' => 'Botella', 'BTO' => 'Bulto', 'CAJ' => 'Caja',
            'CM' => 'Centímetro', 'DIA' => 'Día', 'DOC' => 'Docena', 'GAL' => 'Galón',
            'GR' => 'Gramo', 'HR' => 'Hora', 'JGO' => 'Juego', 'KG' => 'Kilogramo',
            'KM' => 'Kilómetro', 'LB' => 'Libra', 'LT' => 'Litro', 'M' => 'Metro',
            'M2' => 'Metro cuadrado', 'M3' => 'Metro cúbico', 'MG' => 'Miligramo',
            'ML' => 'Mililitro', 'MM' => 'Milímetro', 'PAR' => 'Par', 'PQT' => 'Paquete',
            'PULG' => 'Pulgada', 'SAC' => 'Saco', 'SERV' => 'Servicio',
            'TON' => 'Tonelada', 'UND' => 'Unidad',
        ];

        $payload = require database_path('seeders/data/tenant_initial_data.php');
        $rows = $payload['tables']['cat_unit_types']['rows'];

        self::assertCount(28, $rows);
        self::assertSame($expected, array_column($rows, 'description', 'id'));
        foreach ($rows as $row) {
            self::assertSame(1, $row['active'], $row['id']);
            self::assertSame($row['id'], $row['symbol'], $row['id']);
        }
        self::assertNotContains('NIU', array_column($rows, 'id'));
        self::assertNotContains('ZZ', array_column($rows, 'id'));
    }

    /** @test */
    public function domain_constants_and_reserved_units_use_und_and_serv(): void
    {
        self::assertSame('UND', UnitType::DEFAULT_UNIT_TYPE);
        self::assertSame('SERV', UnitType::SERVICE_UNIT_TYPE);
        self::assertTrue(UnitType::isReserved('UND'));
        self::assertTrue(UnitType::isReserved('serv'));
        self::assertFalse(UnitType::isReserved('KG'));

        foreach (UnitType::LEGACY_UNIT_TYPES as $legacyCode) {
            try {
                UnitType::requireActiveCode($legacyCode);
                self::fail("El código heredado {$legacyCode} fue aceptado.");
            } catch (InvalidArgumentException $exception) {
                self::assertSame('El código de unidad de medida no es válido.', $exception->getMessage());
            }
        }

        $rule = UnitType::activeValidationRule();
        self::assertInstanceOf(Exists::class, $rule);
        self::assertSame('exists:tenant.cat_unit_types,id', (string) $rule);
        self::assertCount(1, $rule->queryCallbacks());
    }

    /** @test */
    public function item_entry_requests_validate_against_the_active_catalog(): void
    {
        foreach ([
            'app/Http/Requests/Tenant/DispatchRequest.php',
            'app/Http/Requests/Tenant/ItemRequest.php',
            'modules/Item/Http/Requests/ItemRequest.php',
            'modules/MobileApp/Http/Requests/Api/ItemRequest.php',
            'modules/Order/Http/Requests/OrderFormRequest.php',
            'modules/Purchase/Http/Requests/FixedAssetItemRequest.php',
        ] as $path) {
            self::assertStringContainsString(
                'UnitType::activeValidationRule()',
                (string) file_get_contents(base_path($path)),
                $path
            );
        }

        $maintenanceRequest = (string) file_get_contents(
            base_path('app/Http/Requests/Tenant/UnitTypeRequest.php')
        );
        self::assertStringContainsString('Rule::notIn(UnitType::LEGACY_UNIT_TYPES)', $maintenanceRequest);
    }

    /** @test */
    public function reserved_units_cannot_be_deleted(): void
    {
        $controller = app(UnitTypeController::class);

        foreach (UnitType::RESERVED_UNIT_TYPES as $id) {
            $response = $controller->destroy($id);
            self::assertFalse($response['success']);
            self::assertStringContainsString('no se pueden eliminar', $response['message']);
        }
    }

    /** @test */
    public function item_import_template_uses_und(): void
    {
        $workbook = IOFactory::load(public_path('formats/items.xlsx'));
        self::assertSame('UND', $workbook->getSheet(0)->getCell('E2')->getValue());
        $workbook->disconnectWorksheets();
    }

    /** @test */
    public function unit_type_skill_documents_the_exact_contract(): void
    {
        $path = base_path('.codex/skills/mantener-unidades-medida-venezuela/SKILL.md');
        self::assertFileExists($path);
        $skill = (string) file_get_contents($path);

        foreach (['UND', 'SERV', 'NIU', 'ZZ', 'cat_unit_types', 'public/formats/items.xlsx'] as $term) {
            self::assertStringContainsString($term, $skill);
        }
    }

    /** @test */
    public function active_application_sources_do_not_use_legacy_unit_codes(): void
    {
        $roots = ['app', 'modules', 'resources/js', 'resources/views', 'database'];
        $extensions = ['php', 'vue', 'js', 'cjs', 'md', 'css'];
        $allowed = base_path('app/Models/Tenant/Catalogs/UnitType.php');

        foreach ($roots as $root) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(base_path($root), \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!$file->isFile() || !in_array($file->getExtension(), $extensions, true)) {
                    continue;
                }
                if ($file->getPathname() === $allowed) {
                    continue;
                }

                $source = (string) file_get_contents($file->getPathname());
                self::assertSame(
                    0,
                    preg_match('/\b(?:NIU|ZZ)\b/', $source),
                    $file->getPathname()
                );
            }
        }

        self::assertSame(
            0,
            preg_match('/\b(?:NIU|ZZ)\b/', (string) file_get_contents(public_path('css/pos.css')))
        );
    }
}
// ######## FIN CONTRATO UNIDADES DE MEDIDA VENEZUELA ########
