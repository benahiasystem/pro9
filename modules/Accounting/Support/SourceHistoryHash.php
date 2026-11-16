<?php

namespace Modules\Accounting\Support;

final class SourceHistoryHash
{
    public static function digest(array $row): string
    {
        if ((int) ($row['integrity_version'] ?? 0) !== 2) { throw new \InvalidArgumentException('Versión de integridad de origen no verificable.'); }
        $value = (object) ['format' => 'accounting.source-version.v2'];
        foreach (['id', 'organization_id', 'company_id', 'source_document_id', 'hash', 'origin_kind', 'disposition', 'recorded_by_name', 'created_at', 'updated_at'] as $field) { $value->{$field} = $row[$field] ?? null; }
        foreach (['revision', 'document_version', 'recorded_by'] as $field) { $value->{$field} = isset($row[$field]) ? (int) $row[$field] : null; }
        foreach (['snapshot', 'document_snapshot'] as $field) { $value->{$field} = isset($row[$field]) ? json_decode($row[$field], false, 512, JSON_THROW_ON_ERROR) : null; }
        return AuditHash::contentDigest($value);
    }
}
