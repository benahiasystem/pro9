<?php

namespace Tests\Unit;

use App\Services\SalesCustomerIdentityPolicy;
use App\Services\SalesDocumentTypePolicy;
use App\Support\Venezuela\IdentityDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SalesCustomerIdentityPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.connections.tenant', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);

        Schema::connection('tenant')->create('cat_identity_document_types', function ($table): void {
            $table->string('id')->primary();
            $table->boolean('active');
            $table->string('description');
        });
        foreach (['countries', 'departments', 'provinces', 'districts'] as $tableName) {
            Schema::connection('tenant')->create($tableName, function ($table): void {
                $table->string('id')->primary();
                $table->string('description')->nullable();
            });
        }
        Schema::connection('tenant')->create('persons', function ($table): void {
            $table->increments('id');
            $table->string('type');
            $table->string('identity_document_type_id');
            $table->string('number');
            $table->string('name');
            $table->string('country_id')->nullable();
            $table->string('department_id')->nullable();
            $table->string('province_id')->nullable();
            $table->string('district_id')->nullable();
            $table->boolean('enabled')->default(true);
        });

        DB::connection('tenant')->table('cat_identity_document_types')->insert(array_map(
            static fn (array $type): array => ['id' => $type['id'], 'active' => $type['active'], 'description' => $type['description']],
            IdentityDocument::TYPES
        ));

        $id = 1;
        foreach (IdentityDocument::ids() as $identityTypeId) {
            DB::connection('tenant')->table('persons')->insert([
                'id' => $id,
                'type' => 'customers',
                'identity_document_type_id' => $identityTypeId,
                'number' => str_pad((string) $id, 8, '0', STR_PAD_LEFT),
                'name' => 'Cliente '.$identityTypeId,
            ]);
            $id++;
        }
        DB::connection('tenant')->table('persons')->insert([
            'id' => 99, 'type' => 'suppliers', 'identity_document_type_id' => '6',
            'number' => '12345678901', 'name' => 'Proveedor',
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('tenant');
        parent::tearDown();
    }

    /** @test */
    public function table_active_flag_is_the_authoritative_sales_identity_catalog(): void
    {
        self::assertSame(
            ['0', '1', '6', '7', 'E', 'C', 'G', 'R'],
            SalesCustomerIdentityPolicy::activeIdentityTypeIds()
        );

        DB::connection('tenant')->table('cat_identity_document_types')->where('id', '6')->update(['active' => 0]);
        $this->expectException(ValidationException::class);
        SalesCustomerIdentityPolicy::assertIdentityTypeAllowed('6');
    }

    /** @test */
    public function all_canonical_customers_are_accepted_and_unknown_or_supplier_people_are_rejected(): void
    {
        foreach (range(1, 8) as $customerId) {
            self::assertSame($customerId, SalesCustomerIdentityPolicy::assertCustomerAllowed($customerId)->id);
        }

        foreach ([99, 999] as $customerId) {
            try {
                SalesCustomerIdentityPolicy::assertCustomerAllowed($customerId);
                self::fail('El cliente no elegible debe rechazarse: '.$customerId);
            } catch (ValidationException $exception) {
                self::assertNotEmpty($exception->errors());
            }
        }
    }

    /** @test */
    public function customer_query_scope_includes_all_canonical_types_and_excludes_a_type_deactivated_in_the_table(): void
    {
        $queryIds = static fn (): array => \App\Models\Tenant\Person::query()
            ->whereType('customers')
            ->whereSalesIdentityActive()
            ->orderBy('id')
            ->pluck('id')
            ->all();

        self::assertSame(range(1, 8), $queryIds());

        DB::connection('tenant')->table('cat_identity_document_types')->where('id', 'G')->update(['active' => 0]);
        self::assertSame([1, 2, 3, 4, 5, 6, 8], $queryIds());
    }

    /** @test */
    public function each_active_identity_can_be_combined_with_every_current_sales_receipt(): void
    {
        foreach (IdentityDocument::activeIds() as $identityTypeId) {
            SalesCustomerIdentityPolicy::assertIdentityTypeAllowed($identityTypeId);
            foreach (['01', '80', 'nv', '07', '08'] as $documentTypeId) {
                if (in_array($documentTypeId, ['01', '07', '08'], true)) {
                    SalesDocumentTypePolicy::assertNewFiscalDocumentAllowed($documentTypeId);
                } elseif ($documentTypeId === '80') {
                    SalesDocumentTypePolicy::assertAllowedForFlow($documentTypeId, SalesDocumentTypePolicy::PRIMARY_DOCUMENT_TYPE_IDS);
                } else {
                    SalesDocumentTypePolicy::assertAllowedForFlow($documentTypeId, SalesDocumentTypePolicy::TECHNICAL_SERVICE_DOCUMENT_TYPE_IDS);
                }
                self::addToAssertionCount(1);
            }
        }
    }

    /** @test */
    public function every_canonical_type_is_rejected_if_its_table_record_is_deactivated(): void
    {
        foreach (IdentityDocument::ids() as $identityTypeId) {
            DB::connection('tenant')->table('cat_identity_document_types')
                ->where('id', $identityTypeId)
                ->update(['active' => 0]);

            try {
                SalesCustomerIdentityPolicy::assertIdentityTypeAllowed($identityTypeId);
                self::fail('El tipo inactivo debe rechazarse: '.$identityTypeId);
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('identity_document_type_id', $exception->errors());
            }

            DB::connection('tenant')->table('cat_identity_document_types')
                ->where('id', $identityTypeId)
                ->update(['active' => 1]);
        }

        try {
            SalesCustomerIdentityPolicy::assertIdentityTypeAllowed('unknown');
            self::fail('El tipo inexistente debe rechazarse.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('identity_document_type_id', $exception->errors());
        }
    }
}
