<?php
namespace App\Services\Fiscal;

/** Shared SQL equivalent of Document::balance for receivable filters and exports. */
final class FiscalNoteBalance
{
    public static function sql(string $alias='documents'): string
    {
        if (!preg_match('/^[a-z_]+$/D',$alias)) throw new \InvalidArgumentException('Invalid SQL alias');
        $id="$alias.id";
        $credit="(SELECT COALESCE(SUM(nc.total),0) FROM notes n JOIN documents nc ON nc.id=n.document_id WHERE n.affected_document_id=$id AND nc.document_type_id='07' AND nc.state_type_id NOT IN ('09','11'))";
        $payments="(SELECT COALESCE(SUM(p.payment),0) FROM document_payments p WHERE p.document_id=$id AND p.reversed_at IS NULL)";
        $retentions="(SELECT COALESCE(SUM(r.applied_amount),0) FROM document_received_retentions r WHERE r.document_id=$id)";
        $fund="GREATEST(0,(SELECT COALESCE(SUM(f.amount),0) FROM document_guarantee_funds f WHERE f.document_id=$id)-(SELECT COALESCE(SUM(a.amount),0) FROM document_note_adjustments a WHERE a.affected_document_id=$id AND a.kind='fund' AND a.status='applied'))";
        $refund="(SELECT COALESCE(SUM(rf.amount),0) FROM document_note_refunds rf WHERE rf.affected_document_id=$id AND rf.component='principal' AND rf.reversed_at IS NULL)";
        return "IF($alias.document_type_id='07',0,($alias.total-IF($alias.document_type_id='01',$credit,0)-$payments-$retentions-$fund+IF($alias.document_type_id='01',$refund,0)))";
    }
}
