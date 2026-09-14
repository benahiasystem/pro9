<?php

namespace Tests\Unit;

use App\Support\Venezuela\PersonLocation;
use Illuminate\Validation\ValidationException;
use Tests\Support\FiscalDatabaseTestCase;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
class VenezuelaPersonLocationTest extends FiscalDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->db->statement('CREATE TABLE departments (id TEXT, active INTEGER)');
        $this->db->statement('CREATE TABLE provinces (id TEXT, department_id TEXT, active INTEGER)');
        $this->db->statement('CREATE TABLE districts (id TEXT, province_id TEXT, active INTEGER)');
        $this->db->table('departments')->insert(['id' => '14', 'active' => 1]);
        $this->db->table('provinces')->insert(['id' => '0229', 'department_id' => '14', 'active' => 1]);
        $this->db->table('districts')->insert(['id' => '000619', 'province_id' => '0229', 'active' => 1]);
    }

    public function test_uses_catalog_relationships_instead_of_code_prefixes(): void
    {
        self::assertSame(['department_id' => '14', 'province_id' => '0229', 'district_id' => '000619'], PersonLocation::resolve($this->db, 'VE', '000619'));
    }

    public function test_optional_location_remains_empty(): void
    {
        foreach ([null, ''] as $district) {
            self::assertSame(['department_id' => null, 'province_id' => null, 'district_id' => null], PersonLocation::resolve($this->db, 'VE', $district));
        }
    }

    /** @dataProvider invalidLocations */
    public function test_rejects_invalid_location($country, $district): void
    {
        $this->expectException(ValidationException::class);
        PersonLocation::resolve($this->db, $country, $district);
    }

    public static function invalidLocations(): array
    {
        return [['VE', '999999'], ['CO', '000619'], ['VE', '619'], ['VE', 619], ['VE', true], ['VE', '00061a'], ['VE', ['000619']]];
    }

    /** @dataProvider levels */
    public function test_rejects_inactive_or_missing_parent(string $table): void
    {
        $this->db->table($table)->update(['active' => 0]);
        $this->expectException(ValidationException::class);
        PersonLocation::resolve($this->db, 'VE', '000619');
    }

    public static function levels(): array
    {
        return [['departments'], ['provinces'], ['districts']];
    }

    public function test_rejects_broken_hierarchy(): void
    {
        $this->db->table('provinces')->delete();
        $this->expectException(ValidationException::class);
        PersonLocation::resolve($this->db, 'VE', '000619');
    }

    public function test_rejects_ambiguous_catalog(): void
    {
        $this->db->table('districts')->insert(['id' => '000619', 'province_id' => '0229', 'active' => 1]);
        $this->expectException(ValidationException::class);
        PersonLocation::resolve($this->db, 'VE', '000619');
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
