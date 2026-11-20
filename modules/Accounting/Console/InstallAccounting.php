<?php

namespace Modules\Accounting\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use App\Tenancy\Models\Hostname;
use App\Tenancy\Environment;
use App\Models\Tenant\User;
use Modules\Accounting\Support\TenantDB as DB;
use Modules\Accounting\Domain\CompanyContext;
use Modules\Accounting\Models\Company;
use Modules\Accounting\Services\CompanyService;
final class InstallAccounting extends Command
{
    protected $signature = 'accounting:install {--hostname= : Dominio exacto del tenant} {--owner-user= : ID del administrador de SAB} {--demo : Crear dos libros de datos ficticios} {--accountant : Asignar explícitamente el rol de contador al propietario}';
    protected $description = 'Instala las tablas del módulo contable dentro de un tenant de SAB.';
    public function handle(): int
    {
        if (!$this->option('hostname') || !ctype_digit((string) $this->option('owner-user'))) {
            $this->error('Indica --hostname y --owner-user.');
            return 1;
        }
        if ($this->option('demo') && (!app()->environment(['local', 'testing']) || !config('accounting.demo'))) {
            $this->error('Los datos ficticios requieren un entorno local o de pruebas con ACCOUNTING_DEMO_MODE.');
            return 1;
        }
        $host = Hostname::query()->with('website')->where('fqdn', $this->option('hostname'))->first();
        if (!$host?->website) {
            $this->error('El tenant no existe.');
            return 1;
        }
        $env = app(Environment::class);
        $env->hostname($host);
        $env->tenant($host->website);
        $owner = User::without('establishment')->find((int) $this->option('owner-user'));
        if (!$owner || $owner->type !== 'admin' || $owner->locked || !$owner->active) {
            $this->error('El propietario debe ser un administrador activo de SAB.');
            return 1;
        }
        $code = $this->call('migrate', ['--database' => 'tenant', '--path' => __DIR__ . '/../Database/Migrations', '--realpath' => true, '--force' => true]);
        if ($code !== 0) {
            return $code;
        }
        DB::transaction(function () use ($owner) {
            $org = DB::table('accounting_organizations')->first();
            if (!$org) {
                $id = (string) Str::uuid();
                DB::table('accounting_organizations')->insert(['id' => $id, 'name' => 'Organización contable de SAB', 'created_at' => now(), 'updated_at' => now()]);
                $org = DB::table('accounting_organizations')->where('id', $id)->first();
            }
            DB::table('accounting_organization_memberships')->updateOrInsert(['organization_id' => $org->id, 'user_id' => $owner->id], ['id' => (string) Str::uuid(), 'role' => 'admin', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
            $source = DB::table('companies')->first();
            $books = $this->option('demo') ? [['Empresa de muestra A, C.A.', 'J-00000000-0'], ['Empresa de muestra B, C.A.', 'J-00000000-1']] : [[$source->name ?? 'Empresa de SAB', $source->number ?? 'POR-DEFINIR']];
            foreach ($books as [$name, $tax]) {
                $company = Company::where('organization_id', $org->id)->where('tax_id', $tax)->first();
                if (!$company) {
                    $company = app(CompanyService::class)->create(['name' => $name, 'tax_id' => $tax, 'approval_mode' => 'separate', 'settings' => ['source' => $this->option('demo') ? 'fictitious' : 'pro9', 'source_company_id' => $this->option('demo') ? null : $source->id ?? null]], $org->id, $owner->id);
                }
                $membership = DB::table('accounting_memberships')->where('company_id', $company->id)->where('user_id', $owner->id)->first();
                if (!$membership) {
                    DB::table('accounting_memberships')->insert(['id' => (string) Str::uuid(), 'organization_id' => $org->id, 'company_id' => $company->id, 'user_id' => $owner->id, 'role' => 'admin', 'roles' => json_encode(['admin']), 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
                    $membership = DB::table('accounting_memberships')->where('company_id', $company->id)->where('user_id', $owner->id)->first();
                }
                if ($this->option('accountant') && !in_array('accountant', app(\Modules\Accounting\Domain\Permissions::class)->roles($membership), true)) {
                    $roles = array_values(array_unique(array_merge(app(\Modules\Accounting\Domain\Permissions::class)->roles($membership), ['accountant'])));
                    DB::table('accounting_memberships')->where('id', $membership->id)->update(['roles' => json_encode($roles), 'version' => $membership->version + 1, 'updated_at' => now()]);
                    app(CompanyContext::class)->run($company, fn() => app(\Modules\Accounting\Services\AuditService::class)->record($owner->id, 'access.bootstrap_accountant', 'user', (string) $owner->id, ['roles' => $roles, 'explicit_cli_option' => true]));
                }
                app(CompanyContext::class)->run($company, fn() => app(CompanyService::class)->defaultChart());
            }
        }, 5);
        $this->info('Módulo contable instalado en el tenant indicado. La PWA utiliza la API de SAB.');
        return 0;
    }
}
