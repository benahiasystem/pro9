<?php

namespace App\Support\Venezuela;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\Cache;

final class IdentityDocumentCatalogMigrator
{
    public function migrate(ConnectionInterface $connection): void
    {
        $schema = $connection->getSchemaBuilder();

        if (!$schema->hasTable('cat_identity_document_types')) {
            return;
        }

        foreach (IdentityDocument::TYPES as $type) {
            $connection->table('cat_identity_document_types')->updateOrInsert(
                ['id' => $type['id']],
                ['active' => $type['active'], 'description' => $type['description']]
            );
        }

        $tables = $connection->table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', $connection->getDatabaseName())
            ->where('COLUMN_NAME', 'identity_document_type_id')
            ->pluck('TABLE_NAME');

        foreach ($tables as $table) {
            $connection->table($table)->where('identity_document_type_id', '4')
                ->update(['identity_document_type_id' => 'E']);
            $connection->table($table)->whereIn('identity_document_type_id', ['A', 'B', 'C', 'D'])
                ->update(['identity_document_type_id' => '0']);

            if ($schema->hasColumn($table, 'number')) {
                $this->normalizeLegacyNumbers($connection, $table);
            }
        }

        $connection->table('cat_identity_document_types')
            ->whereNotIn('id', IdentityDocument::ids())
            ->delete();

        Cache::forget('identity_document_types');
    }

    private function normalizeLegacyNumbers(ConnectionInterface $connection, string $table): void
    {
        foreach (['V' => '1', 'E' => 'E', 'J' => '6', 'P' => '7', 'G' => 'G', 'R' => 'R'] as $prefix => $typeId) {
            $connection->table($table)
                ->where('identity_document_type_id', '6')
                ->whereRaw('UPPER(`number`) REGEXP ?', ['^'.$prefix.'[[:space:]]*-?'])
                ->update([
                    'identity_document_type_id' => $typeId,
                    'number' => new Expression("TRIM(LEADING '-' FROM TRIM(SUBSTRING(`number`, 2)))"),
                ]);
        }
    }
}
