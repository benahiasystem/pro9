<?php
namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\{Document, DocumentNoteRefund, DocumentNoteAdjustment};
use App\Services\Fiscal\{NoteRefund, NoteAdjustment};
use Illuminate\Http\Request;

class NoteOperationController extends Controller
{
    public function annul(Request $request,$id)
    {
        return ['success'=>true,'fiscal_emission'=>app(\App\Services\Fiscal\NoteAnnulment::class)->request(Document::findOrFail($id),$request->all())];
    }
    public function queryAnnulment($id)
    {
        return ['success'=>true,'fiscal_emission'=>app(\App\Services\Fiscal\NoteAnnulment::class)->query(Document::findOrFail($id))];
    }
    public function completeAnnulment($id)
    {
        return ['success'=>true,'fiscal_emission'=>app(\App\Services\Fiscal\NoteAnnulment::class)->complete(Document::findOrFail($id))];
    }
    public function records($id)
    {
        $note=Document::findOrFail($id); NoteAdjustment::authorize($note);
        return ['invoice_id'=>$note->note->affected_document_id,'refunds'=>NoteRefund::view($note->fresh()),'adjustments'=>NoteAdjustment::view($note->fresh())];
    }
    public function refund(Request $request,$id)
    {
        $note=Document::findOrFail($id); app(NoteRefund::class)->register($note,$request->all());
        return ['success'=>true]+$this->records($id);
    }
    public function reverseRefund(Request $request,$id,$refund)
    {
        $note=Document::findOrFail($id); app(NoteRefund::class)->reverse($note,DocumentNoteRefund::findOrFail($refund),$request->all());
        return ['success'=>true]+$this->records($id);
    }
    public function adjustment(Request $request,$id)
    {
        $note=Document::findOrFail($id); NoteAdjustment::register($note,$request->all());
        return ['success'=>true]+$this->records($id);
    }
    public function replacement(Request $request,$id,$adjustment)
    {
        $note=Document::findOrFail($id); NoteAdjustment::replace($note,DocumentNoteAdjustment::findOrFail($adjustment),$request->all());
        return ['success'=>true]+$this->records($id);
    }
}
