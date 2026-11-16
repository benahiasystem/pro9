<?php

namespace Modules\Accounting\Support;

final class SourceResolutionHash
{
    public static function digest(array $row): string
    {
        $data = (object) ['format' => 'accounting.source-resolution.v1'];
        foreach (['id', 'organization_id', 'company_id', 'incident_id', 'source_document_id', 'source_version_id', 'action', 'reason', 'reviewed_by_name', 'created_at', 'updated_at'] as $key) { $data->{$key} = $row[$key] ?? null; }
        $data->base_revision = (int) $row['base_revision']; $data->reviewed_by = (int) $row['reviewed_by'];
        $data->evidence = json_decode($row['evidence'], false, 512, JSON_THROW_ON_ERROR);
        return AuditHash::contentDigest($data);
    }
}
