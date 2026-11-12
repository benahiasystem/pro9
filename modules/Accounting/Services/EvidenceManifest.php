<?php

namespace Modules\Accounting\Services;

use Modules\Accounting\Domain\ApiProblem;
use Modules\Accounting\Models\{Attachment, JournalEntry, OpenItem};

final class EvidenceManifest
{
    public function references(JournalEntry $entry): array
    {
        $references = []; $queue = [$entry]; $seen = [];
        while ($queue) {
            $record = array_pop($queue);
            if (isset($seen[$record->id])) { continue; }
            $seen[$record->id] = true;
            $references['journal_entry:' . $record->id] = ['type' => 'journal_entry', 'id' => $record->id];
            if ($record->source_document_id) { $references['source_document:' . $record->source_document_id] = ['type' => 'source_document', 'id' => $record->source_document_id]; }
            if ($record->reversal_of && !isset($seen[$record->reversal_of])) { $queue[] = JournalEntry::findOrFail($record->reversal_of); }
            $effect = $record->metadata['financial_effect'] ?? [];
            foreach ($effect['open_items'] ?? [] as $item) {
                if (!empty($item['source_document_id'])) { $references['source_document:' . $item['source_document_id']] = ['type' => 'source_document', 'id' => $item['source_document_id']]; }
            }
            $ids = array_column($effect['allocations'] ?? $effect['applications'] ?? [], 'open_item_id');
            if ($ids) {
                foreach (OpenItem::whereIn('id', $ids)->get(['source_document_id', 'origin_entry_id']) as $item) {
                    if ($item->source_document_id) { $references['source_document:' . $item->source_document_id] = ['type' => 'source_document', 'id' => $item->source_document_id]; }
                    if ($item->origin_entry_id && !isset($seen[$item->origin_entry_id])) { $queue[] = JournalEntry::findOrFail($item->origin_entry_id); }
                }
            }
        }
        ksort($references, SORT_STRING);
        return array_values($references);
    }

    public function current(array $references): mixed
    {
        return Attachment::where(function ($q) use ($references) {
            if (!$references) { $q->whereRaw('1=0'); }
            foreach ($references as $reference) { $q->orWhere(fn($q) => $q->where('resource_type', $reference['type'])->where('resource_id', $reference['id'])); }
        })->whereNotExists(fn($q) => $q->selectRaw('1')->from('accounting_attachments as successor')->whereColumn('successor.organization_id', 'accounting_attachments.organization_id')->whereColumn('successor.company_id', 'accounting_attachments.company_id')->whereColumn('successor.resource_type', 'accounting_attachments.resource_type')->whereColumn('successor.resource_id', 'accounting_attachments.resource_id')->whereColumn('successor.supersedes_id', 'accounting_attachments.id'))->orderBy('id')->get();
    }

    public function snapshot(array $references, bool $verify = true): array
    {
        $items = [];
        foreach ($this->current($references) as $file) {
            if ($verify) { app(AttachmentStorage::class)->require($file); }
            $items[] = $file->only(['id', 'resource_type', 'resource_id', 'original_name', 'title', 'size', 'sha256', 'mime', 'supersedes_id']);
        }
        return ['version' => 1, 'items' => $items, 'sha256' => hash('sha256', json_encode($items, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE))];
    }

    public function entry(JournalEntry $entry, bool $verify = true): array { return $this->snapshot($this->references($entry), $verify); }
    public function document(string $id, bool $verify = true): array { return $this->snapshot([['type' => 'source_document', 'id' => $id]], $verify); }

    public function posting(JournalEntry $entry): void
    {
        $current = $this->entry($entry);
        if ($entry->status === 'approved') {
            $approved = $entry->metadata['support_manifest'] ?? null;
            if ($approved && !hash_equals($approved['sha256'], $current['sha256']) || !$approved && $current['items']) {
                throw new ApiProblem('SUPPORT_REVIEW_CHANGED', 'Los soportes cambiaron desde la aprobación. Revisa y aprueba nuevamente el comprobante antes de contabilizar.', 409);
            }
        }
    }
}
