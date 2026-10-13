<?php
namespace App\Services\Fiscal;

use App\Models\Tenant\Document;

final class HkaNoteCapability
{
    public const CREDIT_REASON='NC guardada con sus efectos comerciales. HKA DEMO exige formas de pago (1015); su emisión y devolución de dinero esperan un contrato verificado sin cobros ni reembolsos ficticios.';
    public const DEBIT_REASON='ND guardada con saldo pendiente. HKA DEMO exige formas de pago completas: registre los cobros reales y luego solicite Enviar HKA.';
    public const RELATED_IGTF_REASON='Cobro y cargo IGTF conservados. La emisión 98 relacionada con tercero o régimen especial espera contrato y aceptación DEMO verificables; no cambia el saldo comercial ni habilita devolver ese impuesto.';
    public static function snapshotReason(array $d): ?string
    {
        if (!HkaNotePolicy::snapshot($d) || config('hka_notes.contract_probe',false)) return null;
        if (($d['document_type_id'] ?? '') === '07') return config('hka_notes.demo_non_cash_notes_verified',false) ? null : self::CREDIT_REASON;
        if (($d['payment_condition_id'] ?? null) === '02' && config('hka_notes.demo_non_cash_notes_verified',false)) return null;
        $paid=0; $real=false;
        foreach ($d['payments'] ?? [] as $p) if (empty($p['reversed_at'])) { $paid+=$p['payment'] ?? 0; if (empty($p['receipt_parent_id']) && ($p['payment'] ?? 0)>0) $real=true; }
        return !$real || abs($paid-($d['total'] ?? 0))>0.005 ? self::DEBIT_REASON : null;
    }
    public static function reason(Document $d): ?string
    {
        if (HkaIgtfNote::applies($d) && (!empty($d->fiscal_data->third_party) || $d->fiscal_data->special_tax_regime_id)) return self::RELATED_IGTF_REASON;
        if (!FiscalNote::digital($d) || $d->fiscal_environment !== 'demo') return null;
        return self::snapshotReason(['document_type_id'=>$d->document_type_id,'note'=>['creation_key'=>$d->note->creation_key],
            'total'=>$d->total,'payment_condition_id'=>$d->payment_condition_id,'payments'=>$d->payments()->get()->toArray()]);
    }
}
