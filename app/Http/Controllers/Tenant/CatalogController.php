<?php
// ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########
namespace App\Http\Controllers\Tenant;

use App\Models\Tenant\Company;
use App\Models\Tenant\FiscalEnvironment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\CompanyRequest;
use App\Http\Resources\Tenant\CompanyResource;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function index()
    {
        return view('tenant.catalogs.index');
    }
}
// ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
