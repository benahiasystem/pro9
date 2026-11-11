<?php

namespace Modules\Accounting\Http\Controllers;

use Modules\Accounting\Domain\{ApiProblem, CompanyContext, Permissions};
use Modules\Accounting\Models\Company;
use Modules\Accounting\Services\{CompanyService, AuditService};
use Illuminate\Http\Request;
use Modules\Accounting\Support\TenantDB as DB;
final class CompanyController extends \App\Http\Controllers\Controller
{
    public function index(Request $r): array
    {
        $rows = DB::table('accounting_memberships')->join('accounting_companies', 'accounting_memberships.company_id', '=', 'accounting_companies.id')->join('accounting_organizations', 'accounting_companies.organization_id', '=', 'accounting_organizations.id')->where('accounting_memberships.user_id', $r->user()->id)->where('accounting_memberships.active', true)->where('accounting_companies.active', true)->select('accounting_companies.*', 'accounting_memberships.role', 'accounting_organizations.name as organization_name')->orderBy('accounting_companies.name')->get();
        return ['data' => $rows->map(function ($x) {
            $x->settings = json_decode($x->settings ?? '{}', true);
            $x->permissions = app(Permissions::class)->forRole($x->role);
            return $x;
        }), 'server_time' => now()->toIso8601String()];
    }
    public function store(Request $r, CompanyService $service): array
    {
        $v = $r->validate(['organization_id' => 'required|uuid', 'name' => 'required|string|max:255', 'tax_id' => 'required|string|max:24', 'activity' => 'nullable|string|max:255', 'address' => 'nullable|string|max:255', 'state' => 'nullable|string|max:255', 'municipality' => 'nullable|string|max:255', 'initial_year' => 'required|integer|min:2000|max:2100', 'tax_profile' => 'required|in:ordinary,formal,exempt', 'approval_mode' => 'required|in:separate,self_control']);
        app(\Modules\Accounting\Services\SecurityService::class)->requireVerified($r);
        if (!DB::table('accounting_memberships')->where('user_id', $r->user()->id)->where('organization_id', $v['organization_id'])->where('role', 'admin')->where('active', true)->exists()) {
            throw new ApiProblem('FORBIDDEN_ACTION', 'No puedes crear empresas en esta organización.', 403);
        }
        $year = $v['initial_year'];
        $org = $v['organization_id'];
        unset($v['initial_year'], $v['organization_id']);
        if (DB::table('accounting_companies')->where('organization_id', $org)->where('tax_id', $v['tax_id'])->exists()) {
            throw new ApiProblem('DUPLICATE_TAX_ID', 'Ya existe una empresa con ese RIF en la organización.');
        }
        $c = $service->create($v, $org, $r->user()->id, $year);
        app(CompanyContext::class)->run($c, fn() => $service->createYear($year));
        return ['data' => $c];
    }
    public function show(): array
    {
        return ['data' => app(CompanyContext::class)->company()];
    }
    public function update(Request $r): array
    {
        $v = $r->validate(['name' => 'sometimes|required|string|max:255', 'activity' => 'nullable|string|max:255', 'address' => 'nullable|string|max:255', 'state' => 'nullable|string|max:255', 'municipality' => 'nullable|string|max:255', 'tax_profile' => 'sometimes|required|in:ordinary,formal,exempt', 'approval_mode' => 'sometimes|required|in:separate,self_control', 'offline_hours' => 'sometimes|integer|min:1|max:72', 'workspace_days' => 'sometimes|integer|min:1|max:365', 'settings' => 'sometimes|array']);
        $c = app(CompanyContext::class)->company();
        $before = $c->only(array_keys($v));
        $c->fill($v);
        $c->save();
        app(AuditService::class)->record($r->user()->id, 'company.updated', 'company', $c->id, ['before' => $before, 'after' => $v]);
        return ['data' => $c];
    }
}
