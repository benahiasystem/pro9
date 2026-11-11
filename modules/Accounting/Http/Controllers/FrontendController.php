<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
final class FrontendController extends Controller
{
    public function __invoke(Request $request, ?string $path = null): BinaryFileResponse
    {
        // Missing assets must remain 404 instead of returning HTML to a script request.
        if ($path && preg_match('/\.[a-z0-9]{1,10}$/i', $path)) {
            abort(404);
        }
        $index = public_path('contabilidad/index.html');
        abort_unless(is_file($index), 503, 'El compilado de Contabilidad aún no está instalado.');
        return response()->file($index, ['Content-Type' => 'text/html; charset=UTF-8', 'Cache-Control' => 'no-cache', 'X-Content-Type-Options' => 'nosniff', 'Referrer-Policy' => 'same-origin']);
    }
}
