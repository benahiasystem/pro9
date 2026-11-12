<?php

namespace Modules\Accounting\Services;

use Modules\Accounting\Domain\CompanyContext;
use Modules\Accounting\Support\{TenantDB as DB, AuditHash};

final class AuditIntegrityService
{
    public function verify(): array
    {
        return DB::readSnapshot(function () {
            $c = app(CompanyContext::class);
            $query = fn() => DB::table('accounting_audit_events')->where('organization_id', $c->organizationId())->where('company_id', $c->id());
            $head = DB::table('accounting_audit_heads')->where('organization_id', $c->organizationId())->where('company_id', $c->id())->first();
            $limit = (int) ($head->last_sequence ?? 0);
            $expected = 1; $lastHash = ''; $lastSequence = 0; $count = 0; $legacy = 0; $verified = 0; $failureCount = 0; $failures = [];
            $fail = function (string $code, int $sequence) use (&$failureCount, &$failures) {
                ++$failureCount;
                if (count($failures) < 20) { $failures[] = ['code' => $code, 'sequence' => $sequence]; }
            };
            do {
                $batch = $query()->where('sequence', '>', $lastSequence)->where('sequence', '<=', $limit)->orderBy('sequence')->limit(500)->get();
                foreach ($batch as $row) {
                    $sequence = (int) $row->sequence;
                    if ($sequence !== $expected) { $fail('SEQUENCE_GAP', $sequence); }
                    if (!hash_equals($lastHash, $row->previous_hash)) { $fail('PREVIOUS_HASH_MISMATCH', $sequence); }
                    if ((int) $row->hash_version === 1) { ++$legacy; }
                    elseif ((int) $row->hash_version !== 2) { $fail('UNKNOWN_HASH_VERSION', $sequence); }
                    else {
                        try {
                            if (hash_equals($row->hash, AuditHash::digest((array) $row))) { ++$verified; }
                            else { $fail('CONTENT_HASH_MISMATCH', $sequence); }
                        } catch (\Throwable) { $fail('CONTENT_NOT_VERIFIABLE', $sequence); }
                    }
                    ++$count; $expected = $sequence + 1; $lastSequence = $sequence; $lastHash = $row->hash;
                }
            } while ($batch->count() === 500);
            if ($lastSequence !== $limit || !hash_equals($lastHash, $head->last_hash ?? '') || (int) ($query()->max('sequence') ?? 0) !== $limit) { $fail('HEAD_MISMATCH', $limit); }
            return ['version' => 2, 'company_id' => $c->id(), 'checked_at' => now()->toIso8601String(), 'checked_events' => $count, 'verified_events' => $verified,
                'legacy_events' => $legacy, 'checked_to_sequence' => $limit, 'head_hash' => $lastHash, 'failure_count' => $failureCount, 'failures' => $failures,
                'state' => $failureCount ? 'failed' : ($legacy ? 'legacy' : 'verified'),
                'scope_note' => $legacy ? 'Se verifican la secuencia, los enlaces y las huellas versión 2. Los eventos anteriores conservan su huella, pero su serialización JSON original no permite acreditar todo su contenido con este verificador. No se reescriben.' : 'Se verifican todos los eventos hasta la secuencia indicada, su contenido, orden y enlace con la cabecera conservada. Esta comprobación no equivale a una firma electrónica certificada.'];
        });
    }
}
