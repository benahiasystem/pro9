<?php
namespace App\Services\Fiscal;

use App\Models\Tenant\Document;
use App\Support\Exact\Decimal as D;

/** Advance adjustments share the caller's document transaction and tenant connection. */
final class PrepaymentBalance
{
    /** Initialize once, after the new invoice has its authoritative totals. */
    public static function initializeNew(Document $document): void
    {
        if (!$document->has_prepayment) return;
        if (!$document->wasRecentlyCreated || $document->getConnection()->transactionLevel() === 0) {
            throw new \LogicException('El saldo inicial del anticipo requiere una factura nueva dentro de su transacción.');
        }
        $balance=$document->exactValue('total');
        $document->setExactValues(['pending_amount_prepayment'=>$balance]);
        $document->was_deducted_prepayment=$balance->isLessThanOrEqualTo(0);
        $document->save();
    }

    public static function deduct(Document $context, iterable $rows): void
    {
        foreach ($rows as $entry) {
            $row=(array)$entry;
            $source=self::source($context,$row);
            if (!$source) continue;
            if ((string) $source->customer_id !== (string) $context->customer_id) {
                FiscalAmounts::error('prepayments','El anticipo pertenece a otro cliente.');
            }
            if ($source->currency_type_id !== $context->currency_type_id) {
                FiscalAmounts::error('prepayments','El anticipo debe estar en la moneda de la factura.');
            }
            $amount=D::readRequired($row,'total');
            if ($amount->isNegative()) FiscalAmounts::error('prepayments','El importe del anticipo no puede ser negativo.');
            $balance=$source->exactValue('pending_amount_prepayment')->minus($amount);
            if ($balance->isNegative()) FiscalAmounts::error('prepayments','El importe supera el saldo disponible del anticipo.');
            $source->setExactValues(['pending_amount_prepayment'=>$balance]);
            $source->was_deducted_prepayment=$balance->isLessThanOrEqualTo(0);
            $source->save();
        }
    }

    public static function restore(Document $context, bool $withinTotal=false): void
    {
        foreach ($context->prepayments ?? [] as $entry) {
            $row=(array)$entry;
            $source=self::source($context,$row);
            if (!$source) continue;
            $balance=$source->exactValue('pending_amount_prepayment')->plus(D::readRequired($row,'total'));
            if ($withinTotal && $balance->isGreaterThan($source->exactValue('total'))) continue;
            $source->setExactValues(['pending_amount_prepayment'=>$balance]);
            $source->was_deducted_prepayment=false;
            $source->save();
        }
    }

    private static function source(Document $context,array $row): ?Document
    {
        $identity=FiscalIdentity::parseNumberFull((string)$row['number']);
        if ($identity===null) return null;
        return Document::where([['series',$identity[0]],['number',$identity[1]]])
            ->where('establishment_id',$context->establishment_id)
            ->where('fiscal_environment',$context->fiscal_environment)
            ->where('document_type_id',($row['document_type_id'] ?? '02')==='03' ? '03' : '01')
            ->lockForUpdate()->first();
    }
}
