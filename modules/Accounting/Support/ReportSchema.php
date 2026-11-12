<?php

namespace Modules\Accounting\Support;

use Modules\Accounting\Domain\ApiProblem;

final class ReportSchema
{
    public const VERSION = '2.0';

    public static function kinds(): array
    {
        return ['journal' => 'Libro Diario', 'ledger' => 'Libro Mayor', 'auxiliary' => 'Auxiliar por cuenta y tercero', 'trial-balance' => 'Balance de comprobación', 'open-items' => 'Partidas abiertas al corte', 'documents' => 'Resumen de documentos', 'incidents' => 'Resumen de incidencias', 'balance-sheet' => 'Situación financiera preliminar', 'income-statement' => 'Estado de resultados preliminar'];
    }

    public static function title(string $kind): string
    {
        return self::kinds()[$kind] ?? throw new ApiProblem('REPORT_NOT_FOUND', 'El informe no está disponible para exportación.', 404);
    }

    public static function columns(string $kind, bool $comparison = false): array
    {
        $definitions = match ($kind) {
            'journal', 'auxiliary' => [['accounting_date', 'Fecha', 'date'], ['reference', 'Comprobante', 'text'], ['account_code', 'Cuenta', 'text'], ['account_name', 'Descripción de cuenta', 'text'], ['concept', 'Concepto', 'text'], ['party_name', 'Tercero', 'text'], ['party_tax_id', 'RIF tercero', 'text'], ['source_number', 'Documento origen', 'text'], ['document_reference', 'Referencia documental', 'text'], ['debit', 'Debe VES', 'money'], ['credit', 'Haber VES', 'money'], ...($kind === 'auxiliary' ? [['balance', 'Saldo VES por cuenta y tercero', 'money']] : []), ['original_currency', 'Moneda original', 'text'], ['original_amount', 'Importe original', 'decimal'], ['exchange_rate', 'Tasa histórica', 'decimal'], ['entry_id', 'ID comprobante', 'text'], ['source_document_id', 'ID documento', 'text'], ['reversal_of', 'Reverso de', 'text']],
            'ledger' => [['accounting_date', 'Fecha', 'date'], ['reference', 'Comprobante', 'text'], ['concept', 'Concepto', 'text'], ['party_name', 'Tercero', 'text'], ['party_tax_id', 'RIF tercero', 'text'], ['source_number', 'Documento origen', 'text'], ['document_reference', 'Referencia documental', 'text'], ['debit', 'Debe VES', 'money'], ['credit', 'Haber VES', 'money'], ['balance', 'Saldo VES', 'money'], ['original_currency', 'Moneda original', 'text'], ['original_amount', 'Importe original', 'decimal'], ['exchange_rate', 'Tasa histórica', 'decimal'], ['entry_id', 'ID comprobante', 'text'], ['source_document_id', 'ID documento', 'text'], ['reversal_of', 'Reverso de', 'text']],
            'trial-balance' => [['code', 'Cuenta', 'text'], ['name', 'Descripción', 'text'], ['kind', 'Clasificación', 'text'], ['report_group', 'Grupo de presentación', 'text'], ['opening', 'Saldo inicial VES', 'money'], ['debit', 'Debe VES', 'money'], ['credit', 'Haber VES', 'money'], ['balance', 'Saldo final VES', 'money']],
            'balance-sheet', 'income-statement' => [['code', 'Cuenta', 'text'], ['name', 'Descripción', 'text'], ['kind', 'Clasificación', 'text'], ['report_group', 'Grupo de presentación', 'text'], ['amount', 'Actual VES', 'money'], ...($comparison ? [['previous_amount', 'Comparativo VES', 'money'], ['difference', 'Variación VES', 'money']] : [])],
            'open-items' => [['side', 'Auxiliar', 'text'], ['account_code', 'Cuenta', 'text'], ['party_name', 'Tercero', 'text'], ['party_tax_id', 'RIF tercero', 'text'], ['reference', 'Documento', 'text'], ['issue_date', 'Emisión', 'date'], ['due_date', 'Vencimiento', 'date'], ['currency', 'Moneda original', 'text'], ['remaining_original', 'Saldo original al corte', 'decimal'], ['remaining_book', 'Saldo VES al corte', 'money'], ['status_at_cutoff', 'Estado al corte', 'text'], ['entry_id', 'ID comprobante origen', 'text'], ['source_document_id', 'ID documento', 'text']],
            'documents' => [['issue_date', 'Emisión', 'date'], ['fiscal_date', 'Fecha fiscal', 'date'], ['kind', 'Tipo', 'text'], ['number', 'Documento', 'text'], ['fiscal_control', 'Control fiscal', 'text'], ['party_name', 'Tercero', 'text'], ['party_tax_id', 'RIF tercero', 'text'], ['origin', 'Origen', 'text'], ['status', 'Estado', 'text'], ['currency', 'Moneda', 'text'], ['total', 'Total documental', 'decimal'], ['source_revision', 'Versión actual del origen', 'text'], ['posted_source_revision', 'Última versión con asiento', 'text'], ['posted_source_versions', 'Versiones con asiento', 'text'], ['entry_id', 'ID comprobante', 'text'], ['id', 'ID documento', 'text']],
            'incidents' => [['created_on', 'Fecha', 'date'], ['kind', 'Tipo', 'text'], ['title', 'Incidencia', 'text'], ['status', 'Estado', 'text'], ['resolution', 'Resolución', 'text'], ['resource_type', 'Recurso', 'text'], ['resource_id', 'ID recurso', 'text'], ['id', 'ID incidencia', 'text']],
            default => [],
        };
        return array_map(fn($column) => ['key' => $column[0], 'label' => $column[1], 'type' => $column[2]], $definitions);
    }

    public static function labels(): array
    {
        return ['debit' => 'Total Debe VES', 'credit' => 'Total Haber VES', 'balance' => 'Saldo VES', 'opening' => 'Saldo inicial VES', 'result' => 'Resultado del período VES', 'opening_carry_forward' => 'Resultados trasladados de apertura VES', 'assets' => 'Activo VES', 'liabilities' => 'Pasivo VES', 'equity' => 'Patrimonio VES', 'unclosed_result' => 'Resultado sin cerrar VES', 'difference' => 'Diferencia VES', 'book' => 'Saldo bruto de partidas VES', 'receivable' => 'Por cobrar VES', 'payable' => 'Por pagar VES', 'documents' => 'Documentos', 'incidents' => 'Incidencias', 'open' => 'Abiertas', 'resolved' => 'Resueltas'];
    }
}
