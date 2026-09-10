<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Company;
use App\Services\FiscalEmissionSettings;
use Illuminate\Http\Request;

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
        $company = FiscalEmissionSettings::update(Company::firstOrFail(), $request->all(), 'tenant', $request->user()->id);
        return [
            'success' => true,
            'message' => 'Modalidad de emisión fiscal actualizada. El registro de documentos sigue siendo local.',
            'data' => FiscalEmissionSettings::publicData($company),
        ];
    }
}
// ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
