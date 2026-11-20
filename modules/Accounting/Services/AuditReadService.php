<?php

namespace Modules\Accounting\Services;

use Modules\Accounting\Domain\{CompanyContext, ApiProblem};
use Modules\Accounting\Support\{TenantDB as DB, AuditPayload};

final class AuditReadService
{
    public static function rules(): array
    {
        return ['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d', 'action' => 'nullable|string|max:100|regex:/^[a-zA-Z0-9_.:-]+$/', 'user_id' => 'nullable|integer|min:1', 'resource_type' => 'nullable|string|max:50|regex:/^[a-zA-Z0-9_-]+$/', 'resource_id' => 'nullable|string|max:100', 'until_sequence' => 'nullable|integer|min:0', 'page' => 'nullable|integer|min:1|max:100000', 'per_page' => 'nullable|integer|min:1|max:100'];
    }

    private function query(): mixed
    {
        $c = app(CompanyContext::class);
        return DB::table('accounting_audit_events as e')->leftJoin('users as u', 'u.id', '=', 'e.user_id')
            ->where('e.organization_id', $c->organizationId())->where('e.company_id', $c->id());
    }

    private function columns(bool $detail = false): array
    {
        $columns = ['e.id', 'e.sequence', 'e.hash_version', 'e.actor_name', 'e.user_id', 'u.name as current_actor_name', 'e.action', 'e.resource_type', 'e.resource_id', 'e.created_at', 'e.hash', 'e.previous_hash'];
        if ($detail) { $columns[] = 'e.payload'; }
        return $columns;
    }

    private function resource(object $row, bool $detail = false): array
    {
        $out = ['id' => $row->id, 'company_id' => app(CompanyContext::class)->id(), 'sequence' => (string) $row->sequence, 'hash_version' => (int) $row->hash_version, 'user_id' => $row->user_id === null ? null : (int) $row->user_id,
            'actor_name' => $row->actor_name ?? $row->current_actor_name ?? ($row->user_id === null ? 'Sistema' : 'Usuario histórico'), 'actor_name_basis' => $row->actor_name !== null ? 'at_event' : 'current_or_unavailable',
            'action' => $row->action, 'resource_type' => $row->resource_type, 'resource_id' => $row->resource_id, 'created_at' => $row->created_at, 'hash' => $row->hash, 'previous_hash' => $row->previous_hash];
        foreach (['version', 'source_revision', 'reason', 'comment', 'reference'] as $field) { $out[$field] = isset($row->{$field}) && $row->{$field} !== 'null' ? (string) $row->{$field} : null; }
        if ($detail) { $out['details_json'] = AuditPayload::text($row->payload); }
        return $out;
    }

    public function index(array $input): array
    {
        return DB::readSnapshot(function () use ($input) {
            $from = $input['from'] ?? now('America/Caracas')->startOfMonth()->format('Y-m-d');
            $to = $input['to'] ?? now('America/Caracas')->endOfMonth()->format('Y-m-d');
            if ($from > $to) { throw new ApiProblem('INVALID_DATE_RANGE', 'Selecciona un intervalo de auditoría válido.'); }
            $head = $this->query()->max('e.sequence') ?? 0;
            $until = min((int) ($input['until_sequence'] ?? $head), (int) $head);
            $q = $this->query()->where('e.sequence', '<=', $until)->where('e.created_at', '>=', $from . ' 00:00:00')->where('e.created_at', '<=', $to . ' 23:59:59');
            foreach (['action', 'user_id', 'resource_type', 'resource_id'] as $field) { if (isset($input[$field]) && $input[$field] !== '') { $q->where('e.' . $field, $input[$field]); } }
            $q->select($this->columns());
            foreach (['version', 'source_revision', 'reason', 'comment', 'reference'] as $field) { $q->selectRaw('e.payload ->> ? AS "'.$field.'"', [$field]); }
            $rows = $q->orderByDesc('e.sequence')->paginate($input['per_page'] ?? 25, ['*'], 'page', $input['page'] ?? 1);
            return ['data' => $rows->getCollection()->map(fn($row) => $this->resource($row))->all(), 'meta' => ['from' => $from, 'to' => $to, 'until_sequence' => (string) $until, 'current_sequence' => (string) $head, 'total' => $rows->total(), 'page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'company_id' => app(CompanyContext::class)->id(), 'generated_at' => now()->toIso8601String()]];
        });
    }

    public function show(string $id): array
    {
        $query = $this->query()->where('e.id', $id)->select($this->columns(true));
        foreach (['version', 'source_revision', 'reason', 'comment', 'reference'] as $field) { $query->selectRaw('e.payload ->> ? AS "'.$field.'"', [$field]); }
        $row = $query->first();
        if (!$row) { throw (new \Illuminate\Database\Eloquent\ModelNotFoundException())->setModel(\Modules\Accounting\Models\AuditEvent::class, [$id]); }
        return $this->resource($row, true);
    }

    public function options(): array
    {
        return DB::readSnapshot(fn() => ['company_id' => app(CompanyContext::class)->id(), 'actions' => $this->query()->distinct()->orderBy('e.action')->pluck('e.action')->all(),
            'resource_types' => $this->query()->distinct()->orderBy('e.resource_type')->pluck('e.resource_type')->all(),
            'actors' => $this->query()->whereNotNull('e.user_id')->select('e.user_id', 'u.name')->distinct()->orderBy('u.name')->get()->map(fn($row) => ['id' => (int) $row->user_id, 'name' => $row->name ?? 'Usuario histórico'])->all()]);
    }
}
