<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\Request;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Accounting\Models\{FiscalRecord, SourceDocument};
use Modules\Accounting\Domain\{ApiProblem, CompanyContext};
final class FiscalRegisterController extends \App\Http\Controllers\Controller
{
    public function index(Request $request): array
    {
        $input = $request->validate(['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d', 'side' => 'required|in:sales,purchases']);
        if ($input['from'] > $input['to']) {
            throw new ApiProblem('INVALID_DATE_RANGE', 'Revisa el intervalo de fechas fiscales.');
        }
        $records = FiscalRecord::whereBetween('event_date', [$input['from'], $input['to']])->whereIn('status', ['validated', 'source_changed'])->where('kind', 'like', ($input['side'] === 'sales' ? 'sale_' : 'purchase_') . '%')->orderBy('event_date')->orderBy('created_at')->get();
        $sections = [];
        foreach ($records->groupBy('profile') as $profile => $group) {
            $totals = ['base' => BigDecimal::zero(), 'vat' => BigDecimal::zero(), 'other_taxes' => BigDecimal::zero(), 'total' => BigDecimal::zero()];
            $rows = [];
            foreach ($group as $record) {
                $snapshot = $record->rule_snapshot['document'] ?? null;
                if (!$snapshot) {
                    throw new ApiProblem('FISCAL_SNAPSHOT_REQUIRED', 'El registro necesita completar su evidencia histórica antes de generar el libro.', 409);
                }
                $negative = str_contains($record->kind, '_credit_note');
                $amounts = [];
                foreach ($totals as $key => $unused) {
                    $amount = BigDecimal::of($record->amounts_ves[$key] ?? '0');
                    if ($negative) {
                        $amount = $amount->negated();
                    }
                    $amounts[$key] = (string) $amount->toScale(2, RoundingMode::HALF_UP);
                    $totals[$key] = $totals[$key]->plus($amount);
                }
                $rows[] = ['id' => $record->id, 'document_id' => $record->source_document_id, 'version' => $record->version, 'fiscal_date' => $record->event_date->format('Y-m-d'), 'fiscal_period' => $record->fiscal_period, 'kind' => $record->kind, 'number' => $snapshot['number'], 'control_number' => $snapshot['fiscal_control'] ?? null, 'party' => $snapshot['party'], 'original_currency' => $snapshot['currency'], 'amounts_ves' => $amounts, 'status' => $record->status, 'rule_snapshot' => $record->rule_snapshot, 'related_document_id' => $snapshot['related_document_id'] ?? null];
            }
            $side = $input['side'] === 'sales' ? 'ventas' : 'compras';
            $title = $profile === 'ordinary' ? 'Libro de ' . $side . ' · contribuyente ordinario' : 'Relación de ' . $side . ' · ' . ($profile === 'formal' ? 'contribuyente formal' : 'perfil exento');
            $sections[] = ['profile' => $profile, 'title' => $title, 'rows' => $rows, 'totals' => array_map(fn($amount) => (string) $amount->toScale(2, RoundingMode::HALF_UP), $totals)];
        }
        $context = app(CompanyContext::class);
        return ['data' => ['company' => $context->company()->only(['id', 'name', 'tax_id']), 'from' => $input['from'], 'to' => $input['to'], 'side' => $input['side'], 'sections' => $sections, 'format_version' => '1.0-draft', 'generated_at' => now()->toIso8601String(), 'preliminary' => true]];
    }
}
