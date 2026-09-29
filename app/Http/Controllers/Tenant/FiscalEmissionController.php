<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Company;
use App\Services\Fiscal\HkaAuthentication;
use App\Services\FiscalEmissionSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

// ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########
class FiscalEmissionController extends Controller
{
    private function authorizeAdministrator(Request $request): void
    {
        abort_unless($request->user() instanceof \App\Models\Tenant\User && $request->user()->type === 'admin', 403);
    }

    public function record(Request $request)
    {
        $this->authorizeAdministrator($request);
        return ['data' => FiscalEmissionSettings::publicData(Company::firstOrFail())];
    }

    public function store(Request $request)
    {
        $this->authorizeAdministrator($request);
        Validator::make($request->all(), [
            'hka_usuario' => ['nullable', 'string', 'max:255'],
            'hka_clave' => ['nullable', 'string', 'max:8192'],
            'fiscal_credentials' => ['missing'],
        ])->validate();

        $company = Company::firstOrFail();
        $input = $request->only(['fiscal_emission_mode', 'fiscal_environment', 'fiscal_configuration', 'clear_fiscal_credentials']);
        FiscalEmissionSettings::validate($input);
        if ($input['fiscal_environment'] !== $company->fiscal_environment && FiscalEmissionSettings::hasOperations($company)) {
            throw ValidationException::withMessages([
                'fiscal_environment' => 'Este tenant tiene operaciones. Cree otro tenant limpio para cambiar de ambiente.',
            ]);
        }

        $usuario = trim((string) $request->input('hka_usuario', ''));
        $clave = (string) $request->input('hka_clave', '');
        if (($usuario === '') !== ($clave === '')) {
            throw ValidationException::withMessages([
                $usuario === '' ? 'hka_usuario' : 'hka_clave' => 'Ingrese usuario y clave HKA juntos.',
            ]);
        }

        $authenticatedAt = null;
        if ($usuario !== '') {
            if ($input['fiscal_emission_mode'] !== 'digital') {
                throw ValidationException::withMessages(['hka_usuario' => 'La conexión HKA sólo está disponible para Medios digitales.']);
            }
            if (!empty($input['clear_fiscal_credentials'])) {
                throw ValidationException::withMessages(['hka_clave' => 'Elija reemplazar o eliminar las credenciales.']);
            }
            $credentials = ['usuario' => $usuario, 'clave' => $clave];
            if (HkaAuthentication::credentials($company) !== $credentials || $company->hka_authenticated_at === null
                || $company->fiscal_environment !== $input['fiscal_environment']) {
                app(HkaAuthentication::class)->authenticate($input['fiscal_environment'], $usuario, $clave);
                $authenticatedAt = now();
                $input['fiscal_credentials'] = json_encode($credentials, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            }
        }

        $company = FiscalEmissionSettings::update($company, $input, 'tenant', $request->user()->id, false, $authenticatedAt);
        $data = FiscalEmissionSettings::publicData($company);
        return [
            'success' => true,
            'message' => $data['fiscal_integration_status'] === 'authenticated'
                ? 'Conexión HKA verificada. La emisión de documentos sigue pendiente.'
                : 'Modalidad de emisión fiscal guardada. La conexión HKA está pendiente.',
            'data' => $data,
        ];
    }
}
// ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
