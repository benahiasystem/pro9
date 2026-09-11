<?php

namespace Modules\Dashboard\Http\Controllers;

use App\Models\Tenant\ConfigurationEcommerce;
use Illuminate\Routing\Controller;
use Modules\Ecommerce\Http\Controllers\EcommerceController;
use Modules\Ecommerce\Http\Middleware\CheckPermission;

/**
 * Raíz del subdominio del tenant (ej. demo.pro9.test/).
 */
class HomeController extends Controller
{
    public function index()
    {
        // "Tienda virtual como página principal": la portada de la tienda se muestra en la
        // misma raíz, sin redirigir. Solo si el módulo ecommerce está habilitado.
        // /ecommerce y el resto de rutas siguen funcionando igual.
        if (ConfigurationEcommerce::isHomePage() && CheckPermission::tenantHasEcommerce()) {
            return app(EcommerceController::class)->index();
        }

        // Flujo normal: /dashboard exige sesión y sin ella el middleware auth lleva al login
        return redirect('/dashboard');
    }
}
