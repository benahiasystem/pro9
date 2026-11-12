<?php

namespace Modules\Accounting\Services;

use Illuminate\Support\Facades\Validator;
use Modules\Accounting\Domain\ApiProblem;

/** Client-reported local choices are evidence; normal domain services authorize the saved entity. */
final class LocalSelectionService
{
    public function __construct(private OperationService $operations, private AuditService $audit)
    {
    }

    public function validate(mixed $packet, string $kind, mixed $clientId = null): ?array
    {
        if ($packet === null) {
            return null;
        }
        if (!is_array($packet) || strlen(json_encode($packet, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)) > 2 * 1024 * 1024) {
            throw new ApiProblem('LOCAL_SELECTION_TOO_LARGE', 'El historial local supera 2 MB. Conserva su respaldo y revisa las alternativas antes de enviarlo.');
        }
        $value = Validator::make($packet, [
            'schema' => 'required|integer|in:1', 'tip_id' => 'required|uuid', 'draft_id' => 'required|uuid',
            'decisions' => 'required|array|min:1|max:100', 'decisions.*.id' => 'required|uuid|distinct',
            'decisions.*.client_id' => 'required|uuid', 'decisions.*.kind' => 'required|in:entry,document',
            'decisions.*.selected_id' => 'required|uuid', 'decisions.*.result_id' => 'required|uuid',
            'decisions.*.reason' => 'required|string|min:10|max:2000',
            'decisions.*.reported_at' => 'required|date', 'decisions.*.previous_ids' => 'present|array|max:100',
            'decisions.*.previous_ids.*' => 'required|uuid',
            'decisions.*.variants' => 'required|array|min:1|max:20',
            'decisions.*.variants.*.id' => 'required|uuid',
            'decisions.*.variants.*.revision' => 'required|integer|min:1',
            'decisions.*.variants.*.base_version' => 'nullable|integer|min:1',
            'decisions.*.variants.*.server_id' => 'nullable|uuid',
            'decisions.*.variants.*.entry' => 'required|array',
        ])->validate();
        $rows = [];
        foreach ($value['decisions'] as $row) {
            $clientId ??= $row['client_id'];
            if ($row['client_id'] !== $clientId || $row['kind'] !== $kind) {
                throw new ApiProblem('LOCAL_SELECTION_SCOPE', 'Todas las alternativas deben pertenecer al mismo borrador y tipo de registro.');
            }
            $ids = array_column($row['variants'], 'id');
            if (count($ids) !== count(array_unique($ids)) || !in_array($row['selected_id'], $ids, true) || in_array($row['result_id'], $ids, true)) {
                throw new ApiProblem('INVALID_LOCAL_SELECTION', 'La decisión debe identificar una alternativa y conservar un nuevo borrador separado.');
            }
            $row['reason'] = trim($row['reason']);
            if (mb_strlen($row['reason']) < 10) {
                throw new ApiProblem('RESOLUTION_REQUIRED', 'Explica el motivo de la elección, entre 10 y 2000 caracteres.');
            }
            $rows[$row['id']] = $this->canonical($row);
        }
        if (!isset($rows[$value['tip_id']]) || $rows[$value['tip_id']]['result_id'] !== $value['draft_id']) {
            throw new ApiProblem('INVALID_LOCAL_SELECTION', 'La decisión final no corresponde al borrador que se enviará.');
        }
        $visited = [];
        $visiting = [];
        $ordered = [];
        $visit = function (string $id) use (&$visit, &$visited, &$visiting, &$ordered, $rows) {
            if (isset($visited[$id])) {
                return;
            }
            if (!isset($rows[$id]) || isset($visiting[$id])) {
                throw new ApiProblem('INVALID_LOCAL_SELECTION_HISTORY', 'El historial tiene decisiones ausentes o circulares. Conserva el respaldo antes de revisarlo.');
            }
            $visiting[$id] = true;
            $previous = $rows[$id]['previous_ids'];
            sort($previous, SORT_STRING);
            foreach ($previous as $parent) {
                $visit($parent);
            }
            unset($visiting[$id]);
            $visited[$id] = true;
            $ordered[] = $rows[$id];
        };
        $visit($value['tip_id']);
        if (count($visited) !== count($rows)) {
            throw new ApiProblem('INVALID_LOCAL_SELECTION_HISTORY', 'La decisión final debe conservar todo el historial informado.');
        }
        return ['schema' => 1, 'tip_id' => $value['tip_id'], 'draft_id' => $value['draft_id'], 'decisions' => $ordered];
    }

    private function canonical(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }
        foreach ($value as &$item) {
            $item = $this->canonical($item);
        }
        return $value;
    }

    public function record(int $user, ?array $packet, string $kind, string $entity): array
    {
        $receipts = [];
        foreach ($packet['decisions'] ?? [] as $row) {
            $receipts[] = $this->operations->execute($user, 'offline.selection', $row['id'], ['decision' => $row, 'resource_type' => $kind, 'resource_id' => $entity], function () use ($user, $row, $kind, $entity) {
                $event = $this->audit->record($user, 'offline.alternative_selected', 'local_selection', $row['id'], [
                    'reported_decision' => $row, 'applied_resource_type' => $kind, 'applied_resource_id' => $entity,
                    'evidence_origin' => 'client_reported',
                ]);
                return ['selection_id' => $row['id'], 'audit_id' => $event['id']];
            });
        }
        return $receipts;
    }
}
