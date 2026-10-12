<?php

namespace App\Services;

use App\Models\Tenant\{Company, Document};
use App\Services\Fiscal\FiscalAmounts;
use Illuminate\Support\Facades\DB;

/** The tenant catalog owns the classification; selecting it never changes product IVA. */
final class SpecialTaxRegimePolicy
{
    public const NAMES = [1 => 'Zonas económicas especiales', 2 => 'Zona franca de Paraguaná',
        3 => 'Zona libre de Paraguaná', 4 => 'Puerto libre Santa Elena de Uairén',
        5 => 'Zona Libre de Mérida', 6 => 'Puerto libre Estado Nueva Esparta', 7 => 'Dutty Free'];

    public static function applies($document): bool
    {
        return ($document->fiscal_data->special_tax_regime_id ?? null) !== null;
    }

    public static function requested(array $input): bool
    {
        return isset($input['fiscal_data']['special_tax_regime_id']) && $input['fiscal_data']['special_tax_regime_id'] !== '';
    }

    public static function view($document): array
    {
        return ['special_tax_regime_id' => $document->fiscal_data->special_tax_regime_id ?? null,
            'special_tax_regime_description' => $document->fiscal_data->catalog_snapshot['special_tax_regime_id']['description'] ?? null];
    }

    public static function options(): array
    {
        return DB::connection('tenant')->table('cat_special_tax_regime')->where('active', 1)->orderBy('id')
            ->get(['id', 'description'])->filter(fn ($row) => (self::NAMES[$row->id] ?? null) === $row->description)->values()->toArray();
    }

    public static function assertInput(array $input, bool $web = false): void
    {
        $fiscal = $input['fiscal_data'] ?? [];
        if (!is_array($fiscal)) FiscalAmounts::error('fiscal_data', 'Los datos fiscales requieren un objeto válido.');
        foreach (['catalog_snapshot', 'special_tax_regime_description', 'regimenEspTributacion'] as $field) {
            if (array_key_exists($field, $fiscal) || array_key_exists($field, $input)) FiscalAmounts::error('fiscal_data', 'La descripción y el snapshot del régimen los determina el servidor.');
        }
        if (in_array((string) ($input['document_type_id'] ?? ''), ['07', '08'], true)) {
            $affected = Document::find($input['note']['affected_document_id'] ?? $input['affected_document_id'] ?? 0);
            if ($affected && self::applies($affected)) FiscalAmounts::error('note', 'Las notas vinculadas a regímenes especiales todavía no están habilitadas.');
        }
        if (!self::requested($input)) return;
        if (!$web || (isset($input['source_module']) && $input['source_module'] !== 'WEB')) FiscalAmounts::error('fiscal_data.special_tax_regime_id', 'El régimen especial está disponible únicamente desde Comprobantes web.');
        if (($input['document_type_id'] ?? '') !== '01' || ExportInvoicePolicy::operation($input) !== '0101') FiscalAmounts::error('fiscal_data.special_tax_regime_id', 'El régimen especial requiere una Factura de venta interna.');
        if (!ExportInvoicePolicy::available(Company::query()->setEagerLoads([])->firstOrFail())) FiscalAmounts::error('fiscal_data.special_tax_regime_id', 'El régimen especial HKA requiere Medios digitales y DEMO.');
        if (($input['payment_condition_id'] ?? '') !== '01') FiscalAmounts::error('payment_condition_id', 'El régimen especial requiere Contado completamente pagado.');
        if (!in_array($input['currency_type_id'] ?? '', ['VES', 'USD'], true)) FiscalAmounts::error('currency_type_id', 'El régimen especial admite VES o USD.');
        if (!empty($input['is_itinerant']) || (isset($input['itinerant']['id']) && (string) $input['itinerant']['id'] !== '1') || !empty($fiscal['conditional_data']) || ($fiscal['transaction_type_id'] ?? '01') !== '01') FiscalAmounts::error('fiscal_data', 'El régimen especial no admite otras operaciones condicionales.');
        validator($fiscal, ['special_tax_regime_id' => 'required|integer|between:1,7'])->validate();
        $row = DB::connection('tenant')->table('cat_special_tax_regime')->where('id', $fiscal['special_tax_regime_id'])->where('active', 1)->lockForUpdate()->first();
        if (!$row) FiscalAmounts::error('fiscal_data.special_tax_regime_id', 'Seleccione un régimen especial activo del tenant.');
        self::description($fiscal['special_tax_regime_id'], (array) $row);
    }

    /** Pure validation of a frozen catalog row, without reading today's catalog. */
    public static function description($id, array $row): string
    {
        validator($row, ['id' => 'required|integer|between:1,7', 'description' => 'required|string|max:55'])->validate();
        if ((string) $id !== (string) $row['id'] || (self::NAMES[$row['id']] ?? null) !== $row['description']) FiscalAmounts::error('fiscal_data.special_tax_regime_id', 'Seleccione un régimen HKA activo con su descripción contractual.');
        return $row['description'];
    }
}
