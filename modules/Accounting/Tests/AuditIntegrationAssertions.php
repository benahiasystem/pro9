<?php

namespace Modules\Accounting\Tests;

use Illuminate\Support\Str;
use Modules\Accounting\Domain\CompanyContext;
use Modules\Accounting\Services\{AuditService, AuditIntegrityService};
use Modules\Accounting\Support\{TenantDB as DB, AuditHash};

trait AuditIntegrationAssertions
{
    public function test_versioned_audit_hash_survives_mysql_json_order_and_numbers_and_preserves_actor_identity(): void
    {
        $event = app(AuditService::class)->record($this->admin->id, 'test.audit', 'company', $this->a->id, ['z' => ['texto' => 'Tasa · Mérida', 'number' => 1.0, 'small' => 0.0000001, 'large' => 9.0e20], 'amount' => '9007199254740993.12', 'items' => [null, true, false, (object) [], []]]);
        $stored = (array) DB::table('accounting_audit_events')->where('id', $event['id'])->first();
        $this->assertSame($event['hash'], AuditHash::digest($stored));
        $this->assertSame('verified', app(AuditIntegrityService::class)->verify()['state']);
        $this->assertSame(2, (int) $stored['hash_version']);
        $name = $stored['actor_name'];
        DB::table('users')->where('id', $this->admin->id)->update(['name' => 'Nombre posterior del usuario ficticio']);
        $this->assertSame($name, DB::table('accounting_audit_events')->where('id', $event['id'])->value('actor_name'));
        $this->assertSame('verified', app(AuditIntegrityService::class)->verify()['state']);
        $stored['payload'] = json_encode(['amount' => '9007199254740993.13']);
        $this->assertNotSame($event['hash'], AuditHash::digest($stored));
    }

    public function test_integrity_checker_detects_content_corruption_without_exposing_another_company(): void
    {
        $head = DB::table('accounting_audit_heads')->where('company_id', $this->a->id)->first();
        $now = now()->toDateTimeString();
        $event = ['id' => (string) Str::uuid(), 'organization_id' => $this->a->organization_id, 'company_id' => $this->a->id, 'sequence' => $head->last_sequence + 1, 'user_id' => $this->admin->id, 'action' => 'test.corruption', 'resource_type' => 'company', 'resource_id' => $this->a->id, 'payload' => '{"value":"original"}', 'previous_hash' => $head->last_hash, 'created_at' => $now, 'updated_at' => $now, 'hash_version' => 2, 'actor_name' => 'Actor ficticio'];
        $event['hash'] = AuditHash::digest($event);
        $event['payload'] = '{"value":"sustituido"}';
        DB::table('accounting_audit_events')->insert($event);
        DB::table('accounting_audit_heads')->where('id', $head->id)->update(['last_hash' => $event['hash'], 'last_sequence' => $event['sequence']]);
        $result = app(AuditIntegrityService::class)->verify();
        $this->assertSame('failed', $result['state']);
        $this->assertSame('CONTENT_HASH_MISMATCH', $result['failures'][0]['code']);
        $this->assertSame(1, $result['failure_count']);
        $other = app(CompanyContext::class)->run($this->b, fn() => app(AuditIntegrityService::class)->verify());
        $this->assertSame('verified', $other['state']);
        $this->assertSame($this->b->id, $other['company_id']);
    }

    public function test_legacy_events_remain_immutable_and_are_not_falsely_certified_by_new_hashes(): void
    {
        $head = DB::table('accounting_audit_heads')->where('company_id', $this->a->id)->first();
        $now = now()->toDateTimeString();
        $event = ['id' => (string) Str::uuid(), 'organization_id' => $this->a->organization_id, 'company_id' => $this->a->id, 'sequence' => $head->last_sequence + 1, 'user_id' => null, 'action' => 'test.legacy', 'resource_type' => 'company', 'resource_id' => $this->a->id, 'payload' => '{"z":1,"a":2}', 'previous_hash' => $head->last_hash, 'created_at' => $now, 'updated_at' => $now];
        $event['hash'] = hash('sha256', json_encode($event, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        DB::table('accounting_audit_events')->insert($event);
        DB::table('accounting_audit_heads')->where('id', $head->id)->update(['last_hash' => $event['hash'], 'last_sequence' => $event['sequence']]);
        app(AuditService::class)->record($this->admin->id, 'test.after_legacy', 'company', $this->a->id);
        $result = app(AuditIntegrityService::class)->verify();
        $this->assertSame('legacy', $result['state']);
        $this->assertSame(1, $result['legacy_events']);
        $this->assertSame(0, $result['failure_count']);
        try {
            DB::table('accounting_audit_events')->where('id', $event['id'])->update(['payload' => '{}']);
            $this->fail('El historial definitivo no se edita.');
        } catch (\Illuminate\Database\QueryException $error) {
            $this->assertStringContainsString('Registro de evidencia inmutable', $error->getMessage());
        }
    }

    public function test_audit_presentation_and_new_records_omit_credentials_but_keep_exact_values_and_operation_references(): void
    {
        $event = app(AuditService::class)->record($this->admin->id, 'test.sanitized', 'company', $this->a->id, ['version' => 3, 'reason' => 'Motivo ficticio de la decisión conservada.', 'operation_key' => 'receipt-fictitious-123', 'amount' => '9007199254740993.12', 'apiToken' => 'NEVER_STORE_AUDIT_SECRET', 'nested' => ['password' => 'NEVER_STORE_AUDIT_SECRET', 'private_path' => '/private/NEVER_STORE_AUDIT_SECRET', 'reference' => 'REF-001']]);
        $payload = DB::table('accounting_audit_events')->where('id', $event['id'])->value('payload');
        $this->assertStringNotContainsString('NEVER_STORE_AUDIT_SECRET', $payload);
        $detail = app(\Modules\Accounting\Services\AuditReadService::class)->show($event['id']);
        $this->assertSame('3', $detail['version']);
        $this->assertSame('Motivo ficticio de la decisión conservada.', $detail['reason']);
        $this->assertStringContainsString('9007199254740993.12', $detail['details_json']);
        $this->assertStringContainsString('receipt-fictitious-123', $detail['details_json']);
        $this->assertStringContainsString('[Dato privado omitido]', $detail['details_json']);
        $this->assertSame('verified', app(AuditIntegrityService::class)->verify()['state']);
    }

    public function test_audit_pages_keep_the_consulted_sequence_and_filter_within_the_company(): void
    {
        $service = app(\Modules\Accounting\Services\AuditReadService::class);
        $first = app(AuditService::class)->record($this->admin->id, 'test.paged', 'company', $this->a->id, ['version' => 1]);
        $page = $service->index(['action' => 'test.paged', 'per_page' => 1]);
        app(AuditService::class)->record($this->admin->id, 'test.paged', 'company', $this->a->id, ['version' => 2]);
        $old = $service->index(['action' => 'test.paged', 'per_page' => 1, 'until_sequence' => $page['meta']['until_sequence']]);
        $this->assertSame($first['id'], $old['data'][0]['id']);
        $this->assertSame(1, $old['meta']['total']);
        $this->assertSame(2, $service->index(['action' => 'test.paged'])['meta']['total']);
        $this->assertArrayNotHasKey('payload', $old['data'][0]);
        $other = app(CompanyContext::class)->run($this->b, fn() => $service->index(['action' => 'test.paged']));
        $this->assertSame(0, $other['meta']['total']);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(CompanyContext::class)->run($this->b, fn() => $service->show($first['id']));
    }
}
