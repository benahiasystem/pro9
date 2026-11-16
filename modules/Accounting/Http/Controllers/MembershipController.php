<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Accounting\Domain\{ApiProblem, CompanyContext, Permissions};
use Modules\Accounting\Services\AuditService;
use Modules\Accounting\Support\TenantDB as DB;
final class MembershipController extends \App\Http\Controllers\Controller
{
    public function index(): array
    {
        $context = app(CompanyContext::class);
        $rows = DB::table('accounting_memberships as m')->join('users as u', 'u.id', '=', 'm.user_id')->where('m.organization_id', $context->organizationId())->where('m.company_id', $context->id())->select('m.id', 'm.user_id', 'm.role', 'm.roles', 'm.active', 'm.version', 'u.name', 'u.email')->orderBy('u.name')->get();
        return ['data' => $rows->map(fn($row) => [...(array) $row, 'roles' => app(Permissions::class)->roles($row), 'permissions' => app(Permissions::class)->forMembership($row)]), 'available_roles' => Permissions::all()];
    }
    public function candidates(Request $request): array
    {
        $query = $request->validate(['q' => 'nullable|string|max:100']);
        $users = DB::table('users')->where('active', true)->where('locked', false);
        if (!empty($query['q'])) {
            $users->where(fn($q) => $q->where('name', 'like', '%' . $query['q'] . '%')->orWhere('email', 'like', '%' . $query['q'] . '%'));
        }
        return ['data' => $users->orderBy('name')->limit(100)->get(['id', 'name', 'email'])];
    }
    public function save(Request $request): array
    {
        $input = $request->validate(['user_id' => 'required|integer|min:1', 'roles' => 'required|array|min:1|max:5', 'roles.*' => 'required|string|distinct|in:' . implode(',', array_keys(Permissions::all())), 'active' => 'required|boolean', 'reason' => 'required|string|min:10|max:1000']);
        return DB::transaction(function () use ($request, $input) {
            $context = app(CompanyContext::class);
            DB::table('accounting_companies')->where('id', $context->id())->lockForUpdate()->first();
            $user = DB::table('users')->where('id', $input['user_id'])->where('active', true)->where('locked', false)->first(['id']);
            if (!$user) {
                throw new ApiProblem('USER_NOT_AVAILABLE', 'El usuario debe existir y estar activo en SAB.');
            }
            $old = DB::table('accounting_memberships')->where('company_id', $context->id())->where('user_id', $user->id)->lockForUpdate()->first();
            if ($old && (string) $old->version !== trim((string) $request->header('If-Match'), '"')) {
                throw new ApiProblem('VERSION_CONFLICT', 'Los permisos cambiaron. Recarga la lista antes de guardar.', 412);
            }
            $roles = array_values(array_intersect(array_keys(Permissions::all()), $input['roles']));
            if ($old && $old->active && in_array('admin', app(Permissions::class)->roles($old), true) && (!$input['active'] || !in_array('admin', $roles, true))) {
                $others = DB::table('accounting_memberships')->where('company_id', $context->id())->where('user_id', '!=', $user->id)->where('active', true)->get();
                if (!$others->contains(fn($member) => in_array('admin', app(Permissions::class)->roles($member), true))) {
                    throw new ApiProblem('LAST_COMPANY_ADMIN', 'Asigna otro administrador antes de retirar este acceso.');
                }
            }
            $data = ['role' => $roles[0], 'roles' => json_encode($roles), 'active' => $input['active'], 'version' => ($old->version ?? 0) + 1, 'updated_at' => now()];
            if ($old) {
                DB::table('accounting_memberships')->where('id', $old->id)->update($data);
            } else {
                DB::table('accounting_memberships')->insert([...$data, 'id' => (string) Str::uuid(), 'organization_id' => $context->organizationId(), 'company_id' => $context->id(), 'user_id' => $user->id, 'created_at' => now()]);
            }
            DB::table('accounting_devices')->where('company_id', $context->id())->where('user_id', $user->id)->update(['authorized_until' => now(), 'updated_at' => now()]);
            app(AuditService::class)->record($request->user()->id, 'access.membership_updated', 'user', (string) $user->id, ['previous_roles' => $old ? app(Permissions::class)->roles($old) : [], 'roles' => $roles, 'active' => $input['active'], 'reason' => $input['reason'], 'version' => $data['version']]);
            return ['success' => true, 'version' => $data['version']];
        }, 5);
    }
}
