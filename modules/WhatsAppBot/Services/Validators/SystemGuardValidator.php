<?php
// ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########

namespace Modules\WhatsAppBot\Services\Validators;

use App\Models\Tenant\Company;
use App\Models\Tenant\Configuration as TenantConfiguration;

class SystemGuardValidator implements DocumentValidator
{
    public function validate(array $draft): ValidationResult
    {
        $tenantConfig = TenantConfiguration::first();
        if ($tenantConfig && isset($tenantConfig->locked_tenant) && $tenantConfig->locked_tenant) {
            return ValidationResult::fail('El tenant está bloqueado por falta de pago.', 'tenant_locked');
        }

        $company = Company::first();
        if (!$company) {
            return ValidationResult::fail('No hay datos del negocio configurados.', 'no_company');
        }

        if (empty($company->number) || strlen($company->number) !== 11) {
            return ValidationResult::fail('El RIF del negocio no es válido.', 'invalid_company_ruc');
        }

        if (!array_key_exists($company->fiscal_emission_mode ?? '', \App\Services\FiscalEmissionSettings::MODES)) {
            return ValidationResult::fail('Configure la modalidad de emisión fiscal del negocio.', 'fiscal_emission_mode_missing');
        }

        return ValidationResult::ok();
    }
}
// ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
