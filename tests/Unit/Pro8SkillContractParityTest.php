<?php

namespace Tests\Unit;

use Tests\TestCase;

// ######## INICIO PARIDAD DE CONTRATOS PRO8 Y PRO9 ########
class Pro8SkillContractParityTest extends TestCase
{
    /** @test */
    public function every_pro8_venezuela_skill_has_its_pro9_counterpart(): void
    {
        // ########## INICIO CAMBIO SUNAT A SENIAT
        $availableSkills = $this->skillNames(base_path('.codex/skills'));

        foreach (array_keys($this->requiredContracts()) as $requiredSkill) {
            self::assertContains($requiredSkill, $availableSkills);
        }
        // Los skills específicos de Pro9 pueden extender los contratos heredados de Pro8.
        // ######### FIN CAMBIO SUNAT A SENIAT
    }

    /** @test */
    public function pro9_skills_document_the_current_venezuela_contracts(): void
    {
        foreach ($this->requiredContracts() as $skill => $requirements) {
            $source = (string) file_get_contents(base_path(".codex/skills/{$skill}/SKILL.md"));

            foreach ($requirements as $requirement) {
                self::assertStringContainsString($requirement, $source, "{$skill}: {$requirement}");
            }
        }

        self::assertStringContainsString(
            'VALIDACION_ITEMS.xlsx',
            (string) file_get_contents(base_path('.codex/skills/migrate-product-import-excel-format/references/contrato-validacion-previa.md'))
        );
    }

    /** @test */
    public function skills_with_reusable_pro8_resources_keep_the_required_resources(): void
    {
        foreach (['generate_tenant_migrations.php', 'generate_tenant_seed_data.php', 'validate_tenant_migrations.php', 'validate_tenant_seeders.php'] as $script) {
            self::assertFileDoesNotExist(base_path('.codex/skills/reconstruir-migraciones-tenant/scripts/'.$script));
        }
        self::assertFileExists(base_path('tests/Unit/FiscalEmissionSchemaTest.php'));

        self::assertFileExists(base_path('.codex/skills/migrate-product-import-excel-format/references/contrato-validacion-previa.md'));
    }

    /** @return list<string> */
    private function skillNames(string $path): array
    {
        $skills = glob($path.'/*', GLOB_ONLYDIR) ?: [];

        return array_map('basename', $skills);
    }

    /** @return array<string, list<string>> */
    private function requiredContracts(): array
    {
        return [
            'adaptar-sistema-venezuela' => [
                'Migraciones consolidadas',
                'Estado/Municipio/Parroquia',
                'PAGAR',
                'Culqi',
                'bundle generado',
            ],
            'gestionar-clientes-venezuela' => [
                'country_id = VE',
                'nationality_id = VE',
                'RIF',
                'Venezolano',
                'website',
                'observation',
                'PersonRequest',
            ],
            'migrar-venezuela-geopolitica' => [
                '25 estados, 335 municipios y 1138 parroquias',
                'locations:v2:{tenant}:{country}',
                'America/Caracas',
                '000619',
            ],
            'migrar-venezuela-moneda' => [
                'VES',
                'Bs.',
                'Bolívares',
                'PEN',
                'VED',
                'Culqi',
            ],
            'migrar-venezuela-telefonia' => [
                'Localization::normalizePhone',
                'wa.me',
                'QrChatBuho',
                'encodeURIComponent',
            ],
            // ########## INICIO CAMBIO AFECTACIÓN IVA
            'migrar-iva-venezuela' => [
                '0.16',
                '10 = Gravado',
                '20 = Exento',
                'affectation_igv_type_id',
                'Localization::taxRate',
            ],
            // ######### FIN CAMBIO AFECTACIÓN IVA
            'migrate-product-import-excel-format' => [
                'ItemsImport',
                'quick_validate.py',
            ],
            'reconstruir-migraciones-tenant' => [
                'claves foráneas',
                'TenantMigrationDataSeeder',
                'FiscalEmissionSchemaTest',
                'integridad referencial global',
                'comparar el resultado completo',
            ],
        ];
    }
}
// ######## FIN PARIDAD DE CONTRATOS PRO8 Y PRO9 ########
