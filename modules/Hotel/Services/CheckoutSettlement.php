<?php

namespace Modules\Hotel\Services;

use App\Models\Tenant\Document;
use App\Models\Tenant\DocumentPayment;
use App\Models\Tenant\SaleNote;
use App\Models\Tenant\SaleNotePayment;
use App\Support\Exact\Decimal as D;
use App\Support\Exact\Persistence;
use App\Support\Exact\SaleNoteBalance;
use Modules\Hotel\Models\HotelRent;

/** The checkout snapshot identifies only the originally outstanding hotel lines. */
final class CheckoutSettlement
{
    public static function bind(HotelRent $rent, $document, string $type, $items, $expected, array $expectedItems): void
    {
        if (!Persistence::enabled($rent)) return;
        $snapshot=$rent->calculation_snapshot ?? D::snapshot([]);
        $snapshot['inputs']['hotel_checkout']=[
            'type'=>$type, 'document_id'=>(int)$document->id,
            'item_ids'=>$items->where('payment_status','DEBT')->modelKeys(),
            'amount'=>D::fraction($expected),
            'items'=>array_map([D::class, 'fraction'], $expectedItems),
        ];
        $rent->calculation_snapshot=$snapshot;
    }

    public static function paid($document): bool
    {
        if ($document->isVoidedOrRejected()) return false;
        if ($document instanceof SaleNote) return !SaleNoteBalance::values($document, true)['total_difference']->isGreaterThan(0);
        $payments=$document->payments()->whereNull('reversed_at')->lockForUpdate()->get();
        $paid=D::sum($payments->map(fn ($row)=>D::readRequired($row,'payment')));
        $payable=$document->total_payable === null ? D::of(D::represent($document->exactValue('total'),2)) : $document->exactValue('total_payable');
        return !$document->exactBalance(true)->isGreaterThan(0) && !$paid->isLessThan($payable);
    }

    /** A grouped invoice covers the source notes only if both the totals and lines match. */
    private static function conversionMatches(Document $invoice, SaleNote $origin): bool
    {
        $notes=SaleNote::withoutEagerLoads()->where('document_id',$invoice->id)->orderBy('id')->lockForUpdate()->get();
        if (!$notes->contains(fn ($note)=>(int)$note->id===(int)$origin->id)) return false;
        $total=D::of(0); $expected=[];
        foreach ($notes as $note) {
            if ($note->isVoidedOrRejected() || (int)$note->customer_id !== (int)$invoice->customer_id
                || (int)$note->establishment_id !== (int)$invoice->establishment_id || $note->currency_type_id !== $invoice->currency_type_id) return false;
            $total=$total->plus(D::readRequired($note,'total'));
            foreach ($note->items()->withoutEagerLoads()->lockForUpdate()->get() as $line) {
                $key=(string)$line->item_id;
                $expected[$key]=($expected[$key] ?? D::of(0))->plus(D::readRequired($line,'total'));
            }
        }
        if (!D::readRequired($invoice,'subtotal')->isEqualTo($total)) return false;
        $actual=[];
        foreach ($invoice->items()->withoutEagerLoads()->lockForUpdate()->get() as $line) {
            $key=(string)$line->item_id;
            $actual[$key]=($actual[$key] ?? D::of(0))->plus(D::readRequired($line,'total'));
        }
        if (count($actual)!==count($expected)) return false;
        foreach ($expected as $key=>$amount) if (!isset($actual[$key]) || !$actual[$key]->isEqualTo($amount)) return false;
        return true;
    }

    /** Schedule only hotel documents and run after the commercial transaction commits. */
    public static function afterPayment($payment): void
    {
        if ($payment instanceof SaleNotePayment) { $class=SaleNote::class; $id=$payment->sale_note_id; $type='80'; }
        elseif ($payment instanceof DocumentPayment) { $class=Document::class; $id=$payment->document_id; $type='01'; }
        else return;
        $rentId=$class::withoutEagerLoads()->whereKey($id)->where('source_module','HOTEL')->whereNotNull('hotel_rent_id')->value('hotel_rent_id');
        if ($rentId) self::schedule($payment->getConnection(), (int)$rentId, $type, (int)$id);
        if ($payment instanceof DocumentPayment) self::scheduleSourceNotes($payment->getConnection(), (int)$id);
    }

    /** Document edits, voids and deletion can also invalidate a settled checkout. */
    public static function afterDocument($document): void
    {
        if ($document instanceof SaleNote) $type='80';
        elseif ($document instanceof Document && ($document->document_type_id === '01' || $document->getRawOriginal('document_type_id') === '01')) $type='01';
        else return;
        $ids=[];
        if ($document->source_module === 'HOTEL' && $document->hotel_rent_id) $ids[]=(int)$document->hotel_rent_id;
        if ($document->getRawOriginal('source_module') === 'HOTEL' && $document->getRawOriginal('hotel_rent_id')) $ids[]=(int)$document->getRawOriginal('hotel_rent_id');
        foreach (array_unique($ids) as $rentId) self::schedule($document->getConnection(),$rentId,$type,(int)$document->id);
        if ($document instanceof Document) self::scheduleSourceNotes($document->getConnection(), (int)$document->id);
    }

    private static function scheduleSourceNotes($connection, int $documentId): void
    {
        $notes=SaleNote::withoutEagerLoads()->where('document_id',$documentId)->where('source_module','HOTEL')
            ->whereNotNull('hotel_rent_id')->get(['id','hotel_rent_id']);
        foreach ($notes as $note) self::schedule($connection,(int)$note->hotel_rent_id,'80',(int)$note->id);
    }

    private static function schedule($connection, int $rentId, string $type, int $documentId): void
    {
        $callback=static function () use ($connection,$rentId,$type,$documentId) {
            try { self::reconcile($connection,$rentId,$type,$documentId); }
            catch (\Throwable $error) { report($error); }
        };
        if ($connection->transactionLevel()) $connection->afterCommit($callback); else $callback();
    }

    /** Repair a missed post-commit callback on an explicit checkout retry. */
    public static function reconcileRent(HotelRent $rent): void
    {
        if ($rent->status !== 'FINALIZADO' || !Persistence::enabled($rent)) return;
        $binding=$rent->calculation_snapshot['inputs']['hotel_checkout'] ?? null;
        if (!is_array($binding) || !in_array($binding['type'] ?? null,['01','80'],true) || empty($binding['document_id'])) return;
        self::reconcile($rent->getConnection(), (int)$rent->id, $binding['type'], (int)$binding['document_id']);
    }

    public static function reconcile($connection, int $rentId, string $type, int $documentId): void
    {
        $connection->transaction(static function () use ($rentId,$type,$documentId) {
            $rent=HotelRent::query()->lockForUpdate()->find($rentId);
            if (!$rent || $rent->status !== 'FINALIZADO' || !Persistence::enabled($rent)) return;
            $binding=$rent->calculation_snapshot['inputs']['hotel_checkout'] ?? null;
            if (!is_array($binding) || ($binding['type'] ?? null) !== $type || (int)($binding['document_id'] ?? 0) !== $documentId) return;
            $class=$type === '80' ? SaleNote::class : Document::class;
            $document=$class::withoutEagerLoads()->lockForUpdate()->find($documentId);
            $valid=$document && !$document->isVoidedOrRejected() && $document->source_module === 'HOTEL'
                && (int)$document->hotel_rent_id === (int)$rent->id && (int)$document->customer_id === (int)$rent->customer_id
                && (int)$document->establishment_id === (int)$rent->establishment_id && $document->currency_type_id === $rent->getDefaultCurrency()
                && ($type !== '01' || $document->document_type_id === '01')
                && D::readRequired($document,$type === '01' ? 'subtotal' : 'total')->isEqualTo(D::of($binding['amount']));
            $lines=[];
            if ($valid) foreach ($document->items()->withoutEagerLoads()->lockForUpdate()->get() as $line) {
                $key=(string)$line->item_id;
                $lines[$key]=($lines[$key] ?? D::of(0))->plus(D::readRequired($line,'total'));
            }
            $expected=$binding['items'] ?? [];
            if (!is_array($expected) || count($lines) !== count($expected)) $valid=false;
            if ($valid) foreach ($expected as $key=>$amount) {
                if (!isset($lines[$key]) || !$lines[$key]->isEqualTo(D::of($amount))) { $valid=false; break; }
            }
            $ids=$binding['item_ids'] ?? [];
            if (!is_array($ids)) throw new \UnexpectedValueException('Partidas de checkout inválidas.');
            $bound=$rent->items()->whereIn('id',$ids)->lockForUpdate()->get();
            if (count(array_unique($ids)) !== count($ids) || $bound->count() !== count($ids)) $valid=false;
            $settlement=$document;
            if ($valid && $document instanceof SaleNote && $document->document_id) {
                $invoice=Document::withoutEagerLoads()->lockForUpdate()->find($document->document_id);
                if ($invoice && !$invoice->isVoidedOrRejected()) {
                    $valid=$invoice->document_type_id === '01' && (int)$invoice->customer_id === (int)$rent->customer_id
                        && (int)$invoice->establishment_id === (int)$rent->establishment_id
                        && $invoice->currency_type_id === $rent->getDefaultCurrency()
                        && self::conversionMatches($invoice,$document);
                    // Without an allocation, a partial invoice payment cannot settle a different note.
                    if ($valid && !self::paid($document)) $settlement=$invoice;
                }
            }
            $paid=$valid && self::paid($settlement);
            $bound->each(fn ($item)=>$item->update(['payment_status'=>$paid ? 'PAID' : 'DEBT']));
            $rent->update(['payment_status'=>$paid ? 'PAID' : 'DEBT']);
        },3);
    }
}
