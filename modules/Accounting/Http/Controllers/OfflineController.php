<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Accounting\Domain\{ApiProblem, CompanyContext, Permissions};
use Modules\Accounting\Models\{Device};
use Modules\Accounting\Services\{AuditService, EntryService, OperationService};
use Modules\Accounting\Support\TenantDB as DB;
final class OfflineController extends \App\Http\Controllers\Controller
{
    public function prepare(Request $r): array
    {
        $input = $r->validate(['device_id' => 'required|uuid', 'name' => 'required|string|max:100', 'period' => 'required|date_format:Y-m', 'from' => 'required_with:to|date_format:Y-m-d', 'to' => 'required_with:from|date_format:Y-m-d']);
        return ['data' => app(\Modules\Accounting\Services\WorkspaceService::class)->prepare($r->user(), $input)];
    }
    public function sync(Request $r): array
    {
        $v = $r->validate(['device_id' => 'required|uuid', 'entity_id' => 'nullable|uuid', 'base_version' => 'nullable|integer|min:1', 'local_id' => 'required|uuid']);
        $draft = \Modules\Accounting\Support\EntryInput::validate($r);
        return ['data' => DB::transaction(function () use ($r, $v, $draft) {
            $device = Device::where('user_id', $r->user()->id)->where('device_id', $v['device_id'])->lockForUpdate()->first();
            if (!$device || $device->revoked_at || !$device->authorized_until || $device->authorized_until->isPast()) {
                throw new ApiProblem('OFFLINE_AUTHORIZATION_EXPIRED', 'Vuelve a autorizar este equipo en línea. Tus borradores locales se conservan.', 403);
            }
            app(Permissions::class)->require($r->user(), 'draft');
            return app(OperationService::class)->execute($r->user()->id, 'offline.sync', $r->header('Idempotency-Key'), [...$draft, 'entity_id' => $v['entity_id'] ?? null, 'base_version' => $v['base_version'] ?? null, 'device_id' => $v['device_id']], function () use ($r, $v, $draft) {
                $service = app(EntryService::class);
                if (!empty($v['entity_id'])) {
                    if (empty($v['base_version'])) {
                        throw new ApiProblem('VERSION_REQUIRED', 'Falta la versión del borrador preparado.', 428);
                    }
                    $entry = $service->edit($v['entity_id'], $draft, $v['base_version'], $r->user()->id);
                } else {
                    $entry = $service->create($draft, $r->user()->id);
                }
                app(AuditService::class)->record($r->user()->id, 'offline.draft_synchronized', 'journal_entry', $entry->id, ['device_id' => $v['device_id'], 'local_id' => $v['local_id']]);
                return $service->resource($entry);
            });
        }, 5)];
    }
    public function devices(Request $r): array
    {
        return ['data' => Device::where('user_id', $r->user()->id)->orderByDesc('last_seen_at')->get()];
    }
    public function revoke(Request $r, string $company, string $id): array
    {
        $device = Device::whereKey($id)->where('user_id', $r->user()->id)->firstOrFail();
        $device->revoked_at = now();
        $device->save();
        app(AuditService::class)->record($r->user()->id, 'offline.revoked', 'device', $id, []);
        return ['success' => true];
    }
}
