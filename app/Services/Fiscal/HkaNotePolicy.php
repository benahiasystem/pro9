<?php
namespace App\Services\Fiscal;

/** Conditional rules for ordinary notes, independent from invoice settlement rules. */
final class HkaNotePolicy
{
    public static function snapshot(array $d): bool { return !empty($d['note']['creation_key']); }
    public static function assertSnapshot(array $d): void
    {
        if ($reason=HkaNoteCapability::snapshotReason($d)) FiscalAmounts::error('note',$reason);
        $credit=($d['document_type_id'] ?? '') === '07';
        $reason=$d['note'][$credit ? 'note_credit_type_id':'note_debit_type_id'] ?? '';
        $transactions=$credit ? FiscalNote::CREDIT : FiscalNote::DEBIT;
        if (!isset($transactions[$reason]) || ($d['fiscal_data']['transaction_type_id'] ?? null) !== $transactions[$reason]) FiscalAmounts::error('note','La transacción HKA no corresponde al motivo conservado.');
        if (($d['fiscal_emission_mode'] ?? '') !== 'digital' || ($d['fiscal_environment'] ?? '') !== 'demo'
            || !in_array($d['currency_type_id'] ?? '', ['VES','USD'],true) || empty($d['items']) || ($d['total_exportation'] ?? 0)>0) FiscalAmounts::error('note','Las notas HKA requieren venta interna digital DEMO y partidas reales.');
        if (($d['reference']['document_type_id'] ?? null) !== '01' || empty($d['reference']['control_number'])) FiscalAmounts::error('reference','La nota requiere una factura original con control real.');
        foreach ($d['items'] as $line) if ($credit && empty($line['affected_document_item_id'])) FiscalAmounts::error('items','Las NC conservan sus partidas originales.');
    }
}
