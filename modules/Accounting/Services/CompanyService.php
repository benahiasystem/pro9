<?php

namespace Modules\Accounting\Services;

use Modules\Accounting\Domain\CompanyContext;
use Modules\Accounting\Models\{Company, FiscalYear, Period, Account, ThirdParty};
use Modules\Accounting\Support\TenantDB as DB;
use Illuminate\Support\Str;
final class CompanyService
{
    public function create(array $data, string $organization, int $user, ?int $year = null): Company
    {
        return DB::transaction(function () use ($data, $organization, $user, $year) {
            $company = Company::create([...$data, 'organization_id' => $organization])->refresh();
            DB::table('accounting_memberships')->insert(['id' => (string) Str::uuid(), 'organization_id' => $organization, 'company_id' => $company->id, 'user_id' => $user, 'role' => 'admin', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
            app(CompanyContext::class)->run($company, function () use ($data, $user, $year) {
                $this->createYear($year ?? (int) now()->year);
                app(AuditService::class)->record($user, 'company.created', 'company', app(CompanyContext::class)->id());
            });
            return $company;
        });
    }
    public function createYear(int $year): FiscalYear
    {
        return DB::transaction(function () use ($year) {
            DB::table('accounting_companies')->where('id', app(CompanyContext::class)->id())->lockForUpdate()->first();
            if ($old = FiscalYear::where('year', $year)->lockForUpdate()->first()) {
                return $old;
            }
            $f = FiscalYear::create(['year' => $year]);
            for ($m = 1; $m <= 12; $m++) {
                Period::create(['fiscal_year_id' => $f->id, 'year' => $year, 'month' => $m]);
            }
            return $f;
        }, 5);
    }
    public function defaultChart(): array
    {
        $items = [['1', 'Activo', 'asset', 'debit', false, null, false, null], ['1.1', 'Activo corriente', 'asset', 'debit', false, '1', false, null], ['1.1.01', 'Caja', 'asset', 'debit', true, '1.1', false, 'cash'], ['1.1.02', 'Bancos', 'asset', 'debit', true, '1.1', false, 'bank'], ['1.1.03', 'Clientes nacionales', 'asset', 'debit', true, '1.1', true, 'receivable'], ['1.1.04', 'IVA crédito fiscal', 'asset', 'debit', true, '1.1', false, 'vat_credit'], ['1.1.05', 'Retenciones de IVA por descontar', 'asset', 'debit', true, '1.1', true, 'vat_withheld_receivable'], ['1.1.06', 'Retenciones de ISLR por descontar', 'asset', 'debit', true, '1.1', true, 'islr_withheld_receivable'], ['1.1.07', 'Inventarios', 'asset', 'debit', true, '1.1', false, 'inventory'], ['2', 'Pasivo', 'liability', 'credit', false, null, false, null], ['2.1', 'Pasivo corriente', 'liability', 'credit', false, '2', false, null], ['2.1.01', 'Proveedores nacionales', 'liability', 'credit', true, '2.1', true, 'payable'], ['2.1.02', 'IVA débito fiscal', 'liability', 'credit', true, '2.1', false, 'vat_debit'], ['2.1.03', 'Retenciones de IVA por enterar', 'liability', 'credit', true, '2.1', true, 'vat_withheld_payable'], ['2.1.04', 'Retenciones de ISLR por enterar', 'liability', 'credit', true, '2.1', true, 'islr_withheld_payable'], ['3', 'Patrimonio', 'equity', 'credit', false, null, false, null], ['3.1', 'Capital social', 'equity', 'credit', true, '3', false, 'capital'], ['3.2', 'Resultados acumulados', 'equity', 'credit', true, '3', false, 'retained_earnings'], ['3.3', 'Resultado del ejercicio', 'equity', 'credit', true, '3', false, 'current_result'], ['4', 'Ingresos', 'income', 'credit', false, null, false, null], ['4.1', 'Ventas y servicios', 'income', 'credit', true, '4', false, 'revenue'], ['4.2', 'Ganancia por diferencia de cambio', 'income', 'credit', true, '4', false, 'exchange_gain'], ['5', 'Costos', 'cost', 'debit', false, null, false, null], ['5.1', 'Costo de ventas', 'cost', 'debit', true, '5', false, 'cost_of_sales'], ['6', 'Gastos', 'expense', 'debit', false, null, false, null], ['6.1', 'Gastos administrativos', 'expense', 'debit', true, '6', false, 'expense'], ['6.2', 'Pérdida por diferencia de cambio', 'expense', 'debit', true, '6', false, 'exchange_loss'], ['6.3', 'Impuestos y contribuciones', 'expense', 'debit', true, '6', false, 'other_tax'], ['6.4', 'Diferencias de redondeo documentadas', 'expense', 'debit', true, '6', false, 'rounding']];
        $map = [];
        foreach ($items as [$code, $name, $kind, $nature, $posting, $parent, $party, $aux]) {
            $a = Account::firstOrCreate(['code' => $code], ['name' => $name, 'kind' => $kind, 'nature' => $nature, 'posting' => $posting, 'parent_id' => $parent ? $map[$parent]->id : null, 'requires_party' => $party, 'auxiliary' => $aux, 'report_group' => $kind]);
            $map[$code] = $a;
        }
        return $map;
    }
}
