<?php

namespace Tests\Unit;

use App\Http\Controllers\Tenant\PersonController;
use App\Http\Requests\Tenant\PersonRequest;
use Illuminate\Support\Facades\Validator;
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

    /**
     * @test
     * @dataProvider locationExemptDocuments
     */
    public function it_clears_territorial_data_for_foreign_and_passport_customers_on_create_and_edit(
        string $documentTypeId
    ): void {
        foreach ([null, 25] as $id) {
            $request = PersonRequest::create('/', 'POST', [
                'id' => $id,
                'type' => 'customers',
                'identity_document_type_id' => $documentTypeId,
                'location_id' => ['01', '02', '03'],
                'department_id' => '01',
                'province_id' => '02',
                'district_id' => '03',
                'addresses' => [[
                    'id' => 8,
                    'main' => true,
                    'address' => 'Dirección internacional',
                    'location_id' => ['01', '02', '03'],
                    'department_id' => '01',
                    'province_id' => '02',
                    'district_id' => '03',
                ]],
            ]);

            $this->prepareForValidation($request);

            self::assertSame([], $request->input('location_id'));
            self::assertNull($request->input('department_id'));
            self::assertNull($request->input('province_id'));
            self::assertNull($request->input('district_id'));
            self::assertSame('Dirección internacional', $request->input('addresses.0.address'));
            self::assertSame([], $request->input('addresses.0.location_id'));
            self::assertNull($request->input('addresses.0.department_id'));
            self::assertNull($request->input('addresses.0.province_id'));
            self::assertNull($request->input('addresses.0.district_id'));
        }
    }

    public function locationExemptDocuments(): array
    {
        return [
            'Extranjero' => ['E'],
            'Pasaporte' => ['7'],
        ];
    }

    /** @test */
    public function the_controller_skips_location_only_for_foreign_and_passport_customers(): void
    {
        $controller = new PersonController();
        $method = new ReflectionMethod(PersonController::class, 'hasOptionalLocation');
        $method->setAccessible(true);

        self::assertTrue($method->invoke($controller, 'E'));
        self::assertTrue($method->invoke($controller, '7'));
        self::assertFalse($method->invoke($controller, '6'));
        self::assertFalse($method->invoke($controller, '1'));
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
            'juridico' => ['6', 'regex:/^[0-9]{1,20}$/'],
            'extranjero' => ['E', 'regex:/^[0-9]{1,20}$/'],
            'pasaporte' => ['7', 'regex:/^[0-9]{1,20}$/'],
            'comuna' => ['C', 'regex:/^[0-9]{1,20}$/'],
            'gubernamental' => ['G', 'regex:/^[0-9]{1,20}$/'],
            'firma personal' => ['R', 'regex:/^[0-9]{1,20}$/'],
            'sin rif' => ['0', 'regex:/^[0-9]{1,20}$/'],
        ];
    }

    /**
     * @test
     * @dataProvider customerIdentitySelections
     */
    public function it_preserves_the_selected_id_and_numeric_value_before_persistence(
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
            'Venezolano' => ['1', '12345678', '12345678'],
            'Juridico' => ['6', '123456789', '123456789'],
            'Pasaporte' => ['7', '123456', '123456'],
            'Extranjero' => ['E', '998877', '998877'],
            'Comuna' => ['C', '112233', '112233'],
            'Gubernamental' => ['G', '445566', '445566'],
            'Firma Personal' => ['R', '778899', '778899'],
        ];
    }

    /** @test */
    public function it_does_not_strip_letters_or_special_characters_before_validation(): void
    {
        $request = PersonRequest::create('/', 'POST', [
            'type' => 'customers',
            'identity_document_type_id' => '6',
            'number' => 'J-123456789',
            'addresses' => [],
        ]);

        $this->prepareForValidation($request);

        self::assertSame('J-123456789', $request->input('number'));
        self::assertSame(0, preg_match('/^[0-9]{1,20}$/', $request->input('number')));
    }

    /**
     * @test
     * @dataProvider invalidCustomerNumbers
     */
    public function it_rejects_non_numeric_values_when_creating_or_editing_customers(string $number): void
    {
        foreach ([null, 25] as $id) {
            $request = PersonRequest::create('/', 'POST', [
                'id' => $id,
                'type' => 'customers',
                'identity_document_type_id' => '6',
                'number' => $number,
            ]);

            $rules = $request->rules()['number'];
            $formatRules = array_values(array_filter($rules, 'is_string'));

            self::assertContains('regex:/^[0-9]{1,20}$/', $rules);
            self::assertTrue(Validator::make(
                ['number' => $number],
                ['number' => $formatRules]
            )->fails());
        }
    }

    public function invalidCustomerNumbers(): array
    {
        return [
            'letras' => ['ABC123'],
            'prefijo y guion' => ['J-123456789'],
            'punto' => ['123.456'],
            'espacio interno' => ['123 456'],
            'espacio exterior' => [' 123456'],
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
