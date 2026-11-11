<?php

namespace Modules\Accounting\Domain;

use App\Models\Tenant\User;
use Modules\Accounting\Support\TenantDB as DB;
final class Permissions
{
    private const ROLES = ['viewer' => ['read', 'reports', 'local'], 'auditor' => ['read', 'reports', 'local', 'audit'], 'auxiliary' => ['read', 'draft', 'documents', 'attachments', 'import', 'local', 'reports'], 'accountant' => ['read', 'draft', 'documents', 'attachments', 'import', 'local', 'reports', 'audit', 'review', 'approve', 'post', 'reverse', 'close', 'reopen', 'catalogs', 'rules', 'rates', 'settlements'], 'admin' => ['read', 'reports', 'audit', 'local', 'catalogs', 'users', 'company', 'integration', 'backup']];
    public function roles(object $membership): array
    {
        $roles = $membership->roles ?? null;
        if (is_string($roles)) {
            $roles = json_decode($roles, true);
        }
        return is_array($roles) ? array_values(array_intersect(array_keys(self::ROLES), $roles)) : [$membership->role];
    }
    public function forMembership(object $membership): array
    {
        $permissions = [];
        foreach ($this->roles($membership) as $role) {
            $permissions = array_merge($permissions, self::ROLES[$role] ?? []);
        }
        return array_values(array_unique($permissions));
    }
    public function requiresMfa(User $user): bool
    {
        if (DB::table('accounting_organization_memberships')->where('user_id', $user->id)->where('active', true)->exists()) {
            return true;
        }
        foreach (DB::table('accounting_memberships')->where('user_id', $user->id)->where('active', true)->get() as $membership) {
            if (array_intersect(['admin', 'accountant'], $this->roles($membership))) {
                return true;
            }
        }
        return false;
    }
    public function requireOrganization(User $user, string $organization): void
    {
        if (!DB::table('accounting_organization_memberships')->where('organization_id', $organization)->where('user_id', $user->id)->where('role', 'admin')->where('active', true)->exists()) {
            throw new ApiProblem('FORBIDDEN_ORGANIZATION', 'No puedes administrar esta organización.', 403);
        }
    }
    public static function all(): array
    {
        return self::ROLES;
    }
    public function membership(User $user, string $companyId): object
    {
        $m = DB::table('accounting_memberships')->where('user_id', $user->id)->where('company_id', $companyId)->where('active', true)->first();
        if (!$m) {
            throw new ApiProblem('FORBIDDEN_COMPANY', 'No tienes acceso a esta empresa.', 403);
        }
        return $m;
    }
    public function require(User $user, string $action): object
    {
        $m = $this->membership($user, app(CompanyContext::class)->id());
        if (!in_array($action, $this->forMembership($m), true)) {
            throw new ApiProblem('FORBIDDEN_ACTION', 'Tu rol no permite realizar esta operación.', 403);
        }
        return $m;
    }
    public function forRole(string $role): array
    {
        return self::ROLES[$role] ?? [];
    }
}
