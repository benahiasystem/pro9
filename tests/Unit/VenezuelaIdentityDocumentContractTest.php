<?php

namespace Tests\Unit;

use App\Models\Tenant\Catalogs\IdentityDocumentType;
use App\Support\Venezuela\IdentityDocument;
use Tests\TestCase;

class VenezuelaIdentityDocumentContractTest extends TestCase
{
    /** @test */
    public function catalog_and_tenant_seed_keep_the_exact_contract_and_order(): void
    {
        $expected = [
            ['id' => '0', 'active' => 1, 'description' => 'Doc.sin.rif'],
            ['id' => '1', 'active' => 1, 'description' => 'Venezolano'],
            ['id' => '6', 'active' => 1, 'description' => 'Juridico'],
            ['id' => '7', 'active' => 1, 'description' => 'Pasaporte'],
            ['id' => 'E', 'active' => 0, 'description' => 'Extranjero'],
            ['id' => 'C', 'active' => 0, 'description' => 'Comuna'],
            ['id' => 'G', 'active' => 0, 'description' => 'Gubernamental'],
            ['id' => 'R', 'active' => 0, 'description' => 'Firma Personal'],
        ];

        self::assertSame($expected, array_map(static function (array $type): array {
            return array_intersect_key($type, array_flip(['id', 'active', 'description']));
        }, IdentityDocument::TYPES));

        $payload = require database_path('seeders/data/tenant_initial_data.php');
        self::assertSame($expected, $payload['tables']['cat_identity_document_types']['rows']);
    }

    /** @test */
    public function every_customer_type_has_a_clean_label_and_formats_the_number_with_its_prefix(): void
    {
        $expected = [
            '1' => ['V', 'Venezolano'],
            '6' => ['J', 'Juridico'],
            '7' => ['P', 'Pasaporte'],
            'E' => ['E', 'Extranjero'],
            'C' => ['C', 'Comuna'],
            'G' => ['G', 'Gubernamental'],
            'R' => ['R', 'Firma Personal'],
        ];

        foreach ($expected as $id => [$code, $label]) {
            self::assertSame($code, IdentityDocument::code($id));
            self::assertSame($label, IdentityDocument::selectionLabel($id));
            self::assertSame($code.'-123456789', IdentityDocument::format($id, '123456789'));
        }

        self::assertNull(IdentityDocument::code('0'));
        self::assertSame('123456789', IdentityDocument::format('0', '123456789'));
    }

    /** @test */
    public function selected_prefix_is_removed_before_number_is_persisted(): void
    {
        self::assertSame('123456789', IdentityDocument::normalizeNumber('6', 'J-123456789'));
        self::assertSame('123456789', IdentityDocument::normalizeNumber('1', 'v123456789'));
        self::assertSame('AB123', IdentityDocument::normalizeNumber('7', 'P-AB123'));
        self::assertSame('J-123456789', IdentityDocument::format('6', 'J-123456789'));
    }

    /** @test */
    public function catalog_serialization_keeps_type_descriptions_free_of_document_letters(): void
    {
        $type = new IdentityDocumentType([
            'id' => 'E',
            'active' => 0,
            'description' => 'Extranjero',
        ]);

        self::assertSame('Extranjero', $type->description);
        self::assertSame('E', $type->code);
        self::assertSame('Extranjero', $type->selection_label);
    }

    /** @test */
    public function customer_form_and_resources_use_the_centralized_contract(): void
    {
        $form = (string) file_get_contents(resource_path('js/views/tenant/persons/form.vue'));
        $person = (string) file_get_contents(app_path('Models/Tenant/Person.php'));
        $request = (string) file_get_contents(app_path('Http/Requests/Tenant/PersonRequest.php'));

        self::assertStringContainsString('option.selection_label || option.description', $form);
        self::assertStringContainsString("identity_document_type_id === 'E'", $form);
        self::assertStringContainsString("'formatted_number' => \$this->formatted_number", $person);
        self::assertStringContainsString("'number' => (string) \$this->input('number')", $request);
        self::assertStringNotContainsString("'number' => IdentityDocument::normalizeNumber", $request);
        self::assertStringContainsString('Rule::in(IdentityDocument::ids())', $request);
        self::assertStringContainsString("if (this.type !== 'customers')", $form);
        self::assertStringContainsString('const patternNumber = /^[0-9]+$/', $form);
    }

    /** @test */
    public function active_sources_do_not_append_document_letters_to_type_descriptions(): void
    {
        $forbiddenLabels = [
            'Venezolano V',
            'Juridico J',
            'Pasaporte P',
            'Extranjero E',
            'Comuna C',
            'Gubernamental G',
            'Firma Personal R',
        ];
        $violations = [];

        foreach ([app_path(), base_path('modules'), resource_path()] as $root) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

            foreach ($files as $file) {
                if (!$file->isFile() || !in_array($file->getExtension(), ['php', 'js', 'vue'], true)) {
                    continue;
                }

                $source = (string) file_get_contents($file->getPathname());
                foreach ($forbiddenLabels as $label) {
                    if (strpos($source, $label) !== false) {
                        $violations[] = $file->getPathname().': '.$label;
                    }
                }
            }
        }

        self::assertSame([], $violations);
    }

    /** @test */
    public function the_existing_skill_records_every_identity_document_invariant(): void
    {
        $skill = (string) file_get_contents(base_path('.codex/skills/gestionar-clientes-venezuela/SKILL.md'));

        foreach (['Venezolano', 'Extranjero', 'Pasaporte', 'Juridico', 'Comuna', 'Gubernamental', 'Firma Personal'] as $description) {
            self::assertStringContainsString($description, $skill);
        }

        self::assertStringContainsString('persons.identity_document_type_id', $skill);
        self::assertStringContainsString('persons.number', $skill);
        self::assertStringContainsString('database/seeders/data/tenant_initial_data.php', $skill);
        self::assertStringContainsString('En todo el sistema', $skill);
        self::assertStringContainsString('No agregar, quitar, renombrar, reordenar ni cambiar', $skill);
    }

    /** @test */
    public function visible_blade_documents_do_not_print_raw_person_numbers(): void
    {
        foreach ([base_path('app'), base_path('modules'), resource_path()] as $root) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

            foreach ($files as $file) {
                $path = $file->getPathname();

                if (!$file->isFile() || substr($path, -10) !== '.blade.php' || strpos($path, '/Templates/xml/') !== false) {
                    continue;
                }

                foreach (file($path) as $lineNumber => $line) {
                    if (preg_match('/\$(customer|supplier|person)->number/', $line)) {
                        self::assertStringContainsString(
                            'format_identity_document',
                            $line,
                            $path.':'.($lineNumber + 1).' imprime un número directo sin prefijo.'
                        );
                    }

                    if (preg_match('/->(customer|supplier|person)->number/', $line)) {
                        self::assertStringContainsString(
                            'format_person_identity_document',
                            $line,
                            $path.':'.($lineNumber + 1).' imprime un número relacionado sin prefijo.'
                        );
                    }
                }
            }
        }
    }
}
