<?php

namespace App\Services;

use App\Models\Tenant\{Company, Document};
use App\Models\Tenant\Catalogs\OperationType;
use App\Services\Fiscal\FiscalAmounts;

// ######## INICIO EXPORTACIÓN DE BIENES ########
final class ExportInvoicePolicy
{
    public const INCOTERMS = ['0201' => 'FOB', '0202' => 'CIF', '0203' => 'EXW'];
    public const AFFECTATION = 'EXP';

    public static function isExport($operation): bool
    {
        return isset(self::INCOTERMS[(string) $operation]);
    }

    public static function operation(array $input): string
    {
        return (string) ($input['operation_type_id'] ?? $input['invoice']['operation_type_id'] ?? '0101');
    }

    public static function available(Company $company): bool
    {
        return $company->fiscal_emission_mode === 'digital' && $company->fiscal_environment === 'demo';
    }

    /** The web capability comes from the caller, never from a posted flag. */
    public static function assertInput(array $input, bool $web = false): void
    {
        $operation = self::operation($input);
        $export = self::isExport($operation);
        if ($operation === '0200') FiscalAmounts::error('operation_type_id', 'Seleccione Exportación FOB, CIF o EXW.');
        if (!$export) {
            foreach ($input['items'] ?? [] as $line) {
                if (($line['affectation_igv_type_id'] ?? null) === self::AFFECTATION) FiscalAmounts::error('items', 'La afectación Exportación requiere una factura de exportación.');
            }
            if (in_array((string) ($input['document_type_id'] ?? ''), ['07', '08'], true)) {
                $affected = Document::find($input['note']['affected_document_id'] ?? $input['affected_document_id'] ?? 0);
                if ($affected && self::isExport($affected->invoice->operation_type_id ?? '')) FiscalAmounts::error('note', 'Las notas de exportación todavía no están habilitadas.');
            }
            return;
        }
        if (!$web) FiscalAmounts::error('operation_type_id', 'La exportación está disponible únicamente desde Comprobantes web.');
        if (isset($input['source_module']) && $input['source_module'] !== 'WEB') FiscalAmounts::error('source_module', 'La exportación está disponible únicamente desde Comprobantes web.');
        if (($input['document_type_id'] ?? '') !== '01') FiscalAmounts::error('document_type_id', 'La exportación requiere una Factura de venta.');
        if (!self::available(Company::firstOrFail())) FiscalAmounts::error('operation_type_id', 'La exportación HKA requiere Medios digitales y DEMO.');
        if (!OperationType::where('id', $operation)->where('active', 1)->exists()) FiscalAmounts::error('operation_type_id', 'Seleccione un INCOTERM activo.');
        if (!\App\Models\Tenant\Catalogs\AffectationIgvType::where('id', self::AFFECTATION)->where('active', 1)->exists()) FiscalAmounts::error('items', 'La afectación de exportación está inactiva.');
        if (empty($input['items'])) FiscalAmounts::error('items', 'La exportación requiere productos.');
        foreach ($input['items'] as $i => $line) {
            if (($line['affectation_igv_type_id'] ?? null) !== self::AFFECTATION) FiscalAmounts::error("items.$i.affectation_igv_type_id", 'Todos los productos deben usar Exportación — IVA 0 %.');
            $unit = isset($line['item_id']) ? \App\Models\Tenant\Item::where('id', $line['item_id'])->value('unit_type_id') : ($line['item']['unit_type_id'] ?? null);
            if (!$unit || $unit === 'SERV') FiscalAmounts::error("items.$i.item_id", 'Esta exportación admite bienes; seleccione un producto.');
        }
        $fiscal = $input['fiscal_data'] ?? [];
        if (!empty($fiscal['special_tax_regime_id']) || !empty($fiscal['third_party']) || !empty($fiscal['conditional_data']) || ($fiscal['transaction_type_id'] ?? '01') !== '01') FiscalAmounts::error('fiscal_data', 'Esta exportación admite registro ordinario, sin regímenes ni terceros.');
        if (!empty($input['is_itinerant']) || (isset($input['itinerant']['id']) && (string) $input['itinerant']['id'] !== '1')) FiscalAmounts::error('is_itinerant', 'La exportación no admite emisor itinerante.');
    }

    public static function assertSameCategory(Document $document, array $input): void
    {
        if (self::isExport($document->invoice->operation_type_id ?? '') !== self::isExport(self::operation($input))) FiscalAmounts::error('operation_type_id', 'No se puede convertir una factura registrada entre venta interna y exportación.');
    }
}
// ######## FIN EXPORTACIÓN DE BIENES ########
