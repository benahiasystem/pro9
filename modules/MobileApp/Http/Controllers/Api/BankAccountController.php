<?php

namespace Modules\MobileApp\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Tenant\BankAccount;
use Modules\MobileApp\Http\Resources\Api\BankAccountCollection;
use Modules\Template\Helpers\TemplatePdf;

class BankAccountController extends Controller
{

    /**
     *
     * Obtener registros para scroll infinito
     * Se usa cursor-based pagination para mejor rendimiento
     *
     * Parametros soportados:
     * - limit: cantidad de registros (maximo 100, default 15)
     * - cursor: posicion actual (null en primera peticion)
     * - input: busqueda por descripcion, numero, cci o nombre del banco
     * - show_in_documents: filtrar por cuentas visibles en documentos (true/false)
     * - status: filtrar por estado (1 activo, 0 inactivo)
     * - establishment_id: filtrar por establecimiento
     *
     * @param  Request $request
     * @return array
     */
    public function byScroll(Request $request)
    {
        $limit = $request->input('limit', config('tenant.items_per_page', 15));
        $cursor = $request->input('cursor');
        $input = $request->input('input', '');
        $show_in_documents = $request->input('show_in_documents');
        $status = $request->input('status');

        $limit = min((int) $limit, 100);

        $query = BankAccount::with(['bank', 'currency_type', 'establishment'])
            ->whereFilterRecordsApi($input)
            ->orderBy('id', 'desc');

        if ($show_in_documents !== null) {
            $query->where('show_in_documents', filter_var($show_in_documents, FILTER_VALIDATE_BOOLEAN));
        }

        if ($status !== null) {
            $query->where('status', (int) $status);
        }

        if ($request->filled('establishment_id')) {
            $query->where('establishment_id', $request->input('establishment_id'));
        }

        if ($cursor) {
            $records = $query->cursorPaginate($limit, ['*'], 'cursor', $cursor);
        } else {
            $records = $query->cursorPaginate($limit);
        }

        return [
            'success' => true,
            'data' => new BankAccountCollection($records),
            'pagination' => [
                'next_cursor' => $records->nextCursor()?->encode() ?? null,
                'has_more' => $records->hasMorePages(),
            ]
        ];
    }


    /**
     *
     * Cuentas bancarias que se imprimen en el pdf del comprobante
     *
     * Siempre se resuelve con el establecimiento del usuario autenticado.
     * Aplica el mismo criterio que las plantillas pdf: show_in_documents y,
     * si la configuracion select_establishment_bank_account esta activa,
     * filtra por dicho establecimiento.
     *
     * @return array
     */
    public function forPdf()
    {
        $establishment_id = optional(auth()->user())->establishment_id;

        $records = (new TemplatePdf)->getBankAccountsForPdf($establishment_id)
            ->load(['bank', 'currency_type', 'establishment']);

        return [
            'success' => true,
            'data' => new BankAccountCollection($records),
        ];
    }

}
