<?php

namespace Modules\Accounting\Support;

use Illuminate\Support\Facades\Validator;
use Modules\Accounting\Domain\ApiProblem;
use Modules\Accounting\Models\{Account, ThirdParty, CostCenter};

final class ReportInput
{
    public static function rules(): array
    {
        return ['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d', 'account_id' => 'nullable|uuid', 'third_party_id' => 'nullable|uuid', 'cost_center_id' => 'nullable|uuid', 'side' => 'nullable|in:receivable,payable', 'status' => 'nullable|in:open,settled,resolved,all', 'include_pending' => 'sometimes|boolean', 'compare_from' => 'nullable|date_format:Y-m-d|required_with:compare_to', 'compare_to' => 'nullable|date_format:Y-m-d|required_with:compare_from', 'per_page' => 'nullable|integer|min:1|max:500', 'page' => 'nullable|integer|min:1|max:100000'];
    }

    public static function normalize(string $kind, array $input): array
    {
        $v = Validator::make($input, self::rules())->validate();
        $f = ['from' => $v['from'] ?? now('America/Caracas')->startOfMonth()->format('Y-m-d'), 'to' => $v['to'] ?? now('America/Caracas')->endOfMonth()->format('Y-m-d')];
        if ($kind === 'open-items' && empty($v['from'])) { $f['from'] = $f['to']; }
        if ($f['from'] > $f['to']) {
            throw new ApiProblem('INVALID_DATE_RANGE', 'La fecha inicial debe ser anterior o igual a la final.');
        }
        foreach (['account_id' => Account::class, 'third_party_id' => ThirdParty::class, 'cost_center_id' => CostCenter::class] as $key => $model) {
            if (!empty($v[$key])) {
                $record = $model::findOrFail($v[$key]);
                if ($key === 'account_id' && !$record->posting) {
                    throw new ApiProblem('REPORT_ACCOUNT_GROUP', 'Selecciona una cuenta de movimiento. El balance de comprobación conserva la clasificación de las agrupaciones.');
                }
                $f[$key] = $v[$key];
            }
        }
        if ($kind === 'ledger' && empty($f['account_id'])) {
            throw new ApiProblem('ACCOUNT_REQUIRED', 'Selecciona una cuenta para consultar el Mayor.');
        }
        if ($kind === 'auxiliary' && empty($f['account_id']) && empty($f['third_party_id'])) {
            throw new ApiProblem('AUXILIARY_FILTER_REQUIRED', 'Selecciona una cuenta o un tercero para consultar el auxiliar.');
        }
        if (!empty($v['side'])) {
            if ($kind !== 'open-items') {
                throw new ApiProblem('REPORT_FILTER', 'El filtro de cuentas por cobrar o pagar corresponde a partidas abiertas.');
            }
            $f['side'] = $v['side'];
        }
        if (!empty($v['status'])) {
            if (!in_array($kind, ['open-items', 'incidents'], true)) {
                throw new ApiProblem('REPORT_FILTER', 'El estado no corresponde a este informe.');
            }
            if ($kind === 'open-items' && $v['status'] === 'resolved' || $kind === 'incidents' && $v['status'] === 'settled') {
                throw new ApiProblem('REPORT_FILTER', 'Selecciona un estado que corresponda a este informe.');
            }
            $f['status'] = $v['status'];
        }
        if (!empty($v['include_pending'])) {
            if ($kind !== 'documents') {
                throw new ApiProblem('REPORT_FILTER', 'Los libros contables definitivos solo incluyen movimientos contabilizados.');
            }
            $f['include_pending'] = true;
        }
        if (!empty($v['compare_from']) || !empty($v['compare_to'])) {
            if (!in_array($kind, ['balance-sheet', 'income-statement'], true) || $v['compare_from'] > $v['compare_to']) {
                throw new ApiProblem('REPORT_COMPARISON', 'Selecciona un intervalo comparativo válido para el estado financiero.');
            }
            $f['compare_from'] = $v['compare_from'];
            $f['compare_to'] = $v['compare_to'];
        }
        // Reject filters that would silently disappear from totals or exports.
        if (in_array($kind, ['balance-sheet', 'income-statement', 'trial-balance', 'dashboard'], true) && array_intersect(array_keys($f), ['account_id', 'third_party_id', 'cost_center_id'])) {
            throw new ApiProblem('REPORT_FILTER', 'Este informe resume el libro completo. Usa Mayor o Auxiliares para filtrar cuenta, tercero o centro de costo.');
        }
        if (in_array($kind, ['documents', 'incidents', 'open-items'], true) && isset($f['cost_center_id']) || $kind === 'incidents' && array_intersect(array_keys($f), ['account_id', 'third_party_id']) || $kind === 'documents' && isset($f['account_id'])) {
            throw new ApiProblem('REPORT_FILTER', 'Ese filtro no corresponde a los datos de este informe.');
        }
        return $f;
    }
}
