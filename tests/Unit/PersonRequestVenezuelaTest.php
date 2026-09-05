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
    public function it_discards_empty_or_invalid_address_rows_before_saving_a_customer(): void
    {
        $request = PersonRequest::create('/', 'POST', [
            'type' => 'customers',
            'identity_document_type_id' => '6',
            'addresses' => [
                ['main' => true, 'address' => '', 'location_id' => []],
                'legacy-invalid-row',
                ['address' => 'Av. Bolívar', 'location_id' => ['01', '02', '03']],
            ],
        ]);

        $this->prepareForValidation($request);

        self::assertSame([
            [
                'address' => 'Av. Bolívar',
                'location_id' => ['01', '02', '03'],
                'country_id' => 'VE',
            ],
        ], $request->input('addresses'));
    }

    /** @test */
    public function it_treats_a_non_array_addresses_payload_as_empty(): void
    {
        $request = PersonRequest::create('/', 'POST', [
            'type' => 'customers',
            'identity_document_type_id' => '1',
            'addresses' => 'legacy-invalid-payload',
        ]);

        $this->prepareForValidation($request);

        self::assertSame([], $request->input('addresses'));
    }

    /** @test */
    public function it_preserves_the_selected_nationality_for_a_foreign_customer(): void
    {
        $request = PersonRequest::create('/', 'POST', [
            'type' => 'customers',
            'identity_document_type_id' => 'E',
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
            'identity_document_type_id' => 'E',
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
            'cedula' => ['1', 'regex:/^[0-9]{6,8}$/'],
            'juridico' => ['6', 'regex:/^[A-Z0-9-]{1,20}$/i'],
            'extranjero' => ['E', 'regex:/^[A-Z0-9-]{1,20}$/i'],
            'pasaporte' => ['7', 'regex:/^[A-Z0-9-]{1,20}$/i'],
            'comuna' => ['C', 'regex:/^[A-Z0-9-]{1,20}$/i'],
            'gubernamental' => ['G', 'regex:/^[A-Z0-9-]{1,20}$/i'],
            'firma personal' => ['R', 'regex:/^[A-Z0-9-]{1,20}$/i'],
        ];
    }

    /**
     * @test
     * @dataProvider customerIdentitySelections
     */
    public function it_preserves_the_selected_id_and_removes_its_visible_prefix_before_persistence(
        string $id,
        string $input,
        string $expectedNumber
    ): void {
        $request = PersonRequest::create('/', 'POST', [
            'type' => 'customers',
            'identity_document_type_id' => $id,
            'number' => $input,
            'addresses' => [],
        ]);

        $this->prepareForValidation($request);

        self::assertSame($id, $request->input('identity_document_type_id'));
        self::assertSame($expectedNumber, $request->input('number'));
    }

    public function customerIdentitySelections(): array
    {
        return [
            'Venezolano' => ['1', 'V-12345678', '12345678'],
            'Juridico' => ['6', 'J-123456789', '123456789'],
            'Pasaporte' => ['7', 'P-AB123', 'AB123'],
            'Extranjero' => ['E', 'E-998877', '998877'],
            'Comuna' => ['C', 'C-112233', '112233'],
            'Gubernamental' => ['G', 'G-445566', '445566'],
            'Firma Personal' => ['R', 'R-778899', '778899'],
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
