<?php

namespace Tests\Unit;

use App\Http\Requests\Tenant\PersonRequest;
use ReflectionMethod;
use Tests\TestCase;

// ########### INICIO CAMBIO CLIENTES VENEZUELA
class PersonRequestVenezuelaTest extends TestCase
{
    /** @test */
    public function it_normalizes_venezuelan_customers_and_their_addresses(): void
    {
        $request = PersonRequest::create('/', 'POST', [
            'type' => 'customers',
            'identity_document_type_id' => '6',
            'country_id' => 'PE',
            'nationality_id' => 'PE',
            'addresses' => [['country_id' => 'PE', 'address' => 'Dirección de prueba']],
        ]);

        $this->prepareForValidation($request);

        self::assertSame('VE', $request->input('country_id'));
        self::assertSame('VE', $request->input('nationality_id'));
        self::assertSame('VE', $request->input('addresses.0.country_id'));
    }

    /** @test */
    public function it_treats_missing_addresses_as_an_empty_collection(): void
    {
        $request = PersonRequest::create('/', 'POST', [
            'type' => 'customers',
            'identity_document_type_id' => '1',
        ]);

        $this->prepareForValidation($request);

        self::assertSame([], $request->input('addresses'));
    }

    /** @test */
    public function it_preserves_the_selected_nationality_for_a_foreign_customer(): void
    {
        $request = PersonRequest::create('/', 'POST', [
            'type' => 'customers',
            'identity_document_type_id' => '4',
            'country_id' => 'PE',
            'nationality_id' => 'CO',
            'addresses' => [],
        ]);

        $this->prepareForValidation($request);

        self::assertSame('VE', $request->input('country_id'));
        self::assertSame('CO', $request->input('nationality_id'));
    }

    /** @test */
    public function it_does_not_change_supplier_nationality_data(): void
    {
        $request = PersonRequest::create('/', 'POST', [
            'type' => 'suppliers',
            'identity_document_type_id' => '4',
            'country_id' => 'CO',
            'nationality_id' => 'CO',
        ]);

        $this->prepareForValidation($request);

        self::assertSame('CO', $request->input('country_id'));
        self::assertSame('CO', $request->input('nationality_id'));
    }

    /**
     * @test
     * @dataProvider venezuelanDocumentRules
     */
    public function it_defines_the_expected_customer_document_format(
        string $documentTypeId,
        string $expectedRule
    ): void {
        $request = PersonRequest::create('/', 'POST', [
            'type' => 'customers',
            'identity_document_type_id' => $documentTypeId,
        ]);

        self::assertContains($expectedRule, $request->rules()['number']);
    }

    public function venezuelanDocumentRules(): array
    {
        return [
            'RIF' => ['6', 'regex:/^[VEJGP][0-9]{9}$/i'],
            'cedula' => ['1', 'regex:/^[0-9]{6,8}$/'],
            'extranjero' => ['4', 'regex:/^[A-Z0-9-]{1,20}$/i'],
        ];
    }

    private function prepareForValidation(PersonRequest $request): void
    {
        $method = new ReflectionMethod(PersonRequest::class, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);
    }
}
// ########### FIN CAMBIO CLIENTES VENEZUELA
