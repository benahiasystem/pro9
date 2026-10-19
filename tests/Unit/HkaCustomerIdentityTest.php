<?php
namespace Tests\Unit;

use App\Services\Fiscal\{FiscalDocumentPersistence, HkaPayloadBuilder};
use App\Support\Venezuela\IdentityDocument;
use Tests\TestCase;

class HkaCustomerIdentityTest extends TestCase
{
    /** @dataProvider domesticIdentities */
    public function test_domestic_numeric_identity_uses_the_central_catalog(string $type, string $expected): void
    {
        $customer = ['identity_document_type_id'=>$type, 'number'=>'123456789', 'name'=>'Cliente congelado'];
        $result = FiscalDocumentPersistence::withHkaIdentity($customer);
        self::assertSame($expected, $result['hka_identity_code']);
        self::assertSame(IdentityDocument::code($type), $result['hka_identity_code']);
        self::assertSame($customer, array_diff_key($result, ['hka_identity_code'=>true]));
        $snapshot = json_decode(file_get_contents(__DIR__.'/../Fixtures/Hka/invoice.json'), true);
        $snapshot['customer'] = array_replace($snapshot['customer'], $result);
        // R remains subject to the pinned HKA schema; this correction cannot broaden its contract.
        if ($type === 'R') $this->expectException(\Illuminate\Validation\ValidationException::class);
        $buyer = (new HkaPayloadBuilder)->build($snapshot)['documentoElectronico']['encabezado']['comprador'];
        self::assertSame($expected, $buyer['tipoIdentificacion']);
        self::assertSame('123456789', $buyer['numeroIdentificacion']);
    }

    public static function domesticIdentities(): array
    {
        return [['1','V'], ['6','J'], ['7','P'], ['E','E'], ['C','C'], ['G','G'], ['R','R']];
    }

    public function test_frozen_rif_and_external_identity_keep_precedence_without_changing_the_number(): void
    {
        $customer = ['identity_document_type_id'=>'1', 'number'=>'12345678', 'fiscal_profile'=>['rif'=>'R-12345678-9']];
        $result = FiscalDocumentPersistence::withHkaIdentity($customer);
        self::assertSame('R', $result['hka_identity_code']);
        self::assertSame($customer['number'], $result['number']);
        self::assertSame($customer['fiscal_profile'], $result['fiscal_profile']);
        $foreign = FiscalDocumentPersistence::withHkaIdentity(['identity_document_type_id'=>'ND', 'number'=>'AB123',
            'external_identity_type'=>'NIT', 'fiscal_profile'=>['rif'=>'J-12345678-9']]);
        self::assertSame('NIT', $foreign['hka_identity_code']);
        self::assertSame([], $foreign['fiscal_profile']);
        self::assertSame('AB123', $foreign['number']);
    }

    public function test_legacy_prefixes_are_preserved_and_unknown_identities_are_not_invented(): void
    {
        self::assertSame('J', FiscalDocumentPersistence::withHkaIdentity(['identity_document_type_id'=>'6', 'number'=>'J-12345678-9'])['hka_identity_code']);
        foreach (['0', 'UNKNOWN'] as $type) {
            self::assertNull(FiscalDocumentPersistence::withHkaIdentity(['identity_document_type_id'=>$type, 'number'=>'123456789'])['hka_identity_code']);
        }
    }
}
