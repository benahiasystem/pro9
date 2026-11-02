<?php
// ######## INICIO TASAS OCHO DECIMALES: CONVERSIONES EXACTAS ########
// ######## FIN TASAS OCHO DECIMALES: CONVERSIONES EXACTAS ########
// ######## INICIO MIGRACIÓN MONEDA VENEZUELA ########
namespace Modules\Restaurant\Http\Controllers;

use App\Exports\CashProductExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\CashRequest;
use App\Http\Resources\Tenant\CashCollection;
use App\Http\Resources\Tenant\CashResource;
use App\Models\Tenant\Cash;
use App\Models\Tenant\CashDocument;
use App\Models\Tenant\Company;
use App\Models\Tenant\DocumentItem;
use App\Models\Tenant\PaymentMethodType;
use App\Models\Tenant\PurchaseItem;
use App\Models\Tenant\SaleNoteItem;
use App\Models\Tenant\SaleNote;
use App\Models\Tenant\Document;
use App\Models\Tenant\User;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Traits\FinanceTrait;
use Modules\Pos\Models\CashTransaction;
use App\Models\Tenant\CashDocumentCredit;


class CashController extends Controller
{

    use FinanceTrait;

    public function index()
    {
        return view('restaurant::cash.index');
    }

    public function posFilter()
    {
        return view('restaurant::cash.filter_pos');
    }

    public function columns()
    {
        return [
            'income' => 'Ingresos',
        ];
    }

    public function records(Request $request)
    {
        $records = Cash::where('apply_restaurant', 1)
                        ->where($request->column, 'like', "%{$request->value}%")
                        ->where('reference_number', 'like', "%{$request->reference_number}%")
                        ->whereTypeUser()
                        ->orderBy('date_closed', 'DESC')
                        ->orderBy('time_closed', 'DESC');


        return new CashCollection($records->paginate(config('tenant.items_per_page')));
    }

    public function tables()
    {
        $user = auth()->user();
        $type = $user->type;
        $users = array();

        switch($type)
        {
            case 'admin':
                $users = User::where('type', 'seller')->get();
                $users->push($user);
                break;
            case 'seller':
                $users = User::where('id', $user->id)->get();
                break;
        }

        return compact('users', 'user') + ['report_version'=>2];
    }

    public function opening_cash()
    {

        $cash = Cash::where([['user_id', auth()->user()->id],['state', true]])->first();

        return compact('cash');
    }

    public function opening_cash_check($user_id)
    {
        $cash = Cash::where([['user_id', $user_id],['state', true]])->first();
        return compact('cash');
    }


    public function record($id)
    {
        $record = new CashResource(Cash::findOrFail($id));

        return $record;
    }

    public function store(CashRequest $request) {

        $id = $request->input('id');

        DB::connection('tenant')->transaction(function () use ($id, $request) {

            $cash = Cash::firstOrNew(['id' => $id]);
            // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
            if ($cash->exists) $cash=Cash::whereKey($cash->id)->lockForUpdate()->firstOrFail();
            if ($cash->exists) \App\Services\Payments\CashLedger::authorize($cash);
            if (!$cash->exists && auth()->user()->type !== 'admin' && (string)$request->input('user_id',auth()->id()) !== (string)auth()->id()) abort(403);
            $cash->fill($cash->exists ? $request->except(['beginning_balance','final_balance','income','state','user_id','date_opening','time_opening','date_closed','time_closed']) : $request->all());
            if (!$cash->exists) {
                $cash->beginning_balance=$request->input('opening_balances.VES',$request->input('beginning_balance','0'));
                $cash->final_balance='0'; $cash->income='0'; $cash->state=true;
            }
            // ######## FIN CLASIFICACION DE COBROS Y CAJA ########

            if(!$id){
                $cash->date_opening = date('Y-m-d');
                $cash->time_opening = date('H:i:s');
            }

            $cash->save();

            $this->createCashTransaction($cash, $request);

        });


        return [
            'success' => true,
            'message' => ($id)?'Caja actualizada con éxito':'Caja aperturada con éxito'
        ];

    }


    public function createCashTransaction($cash, $request)
    {
        // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
        \App\Services\Payments\CashLedger::open($cash,$request->all());
        // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
    }


    public function close($id)
    {
        // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
        $cash=Cash::findOrFail($id);
        return \App\Services\Payments\CashLedger::close($cash);
        // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function cash_document(Request $request) {

        // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
        // Facturas y notas comparten la vinculación idempotente y los snapshots del POS general.
        if ($request->document_id !== null || $request->sale_note_id !== null) {
            return app(\App\Http\Controllers\Tenant\CashController::class)->cash_document($request);
        }
        // ######## FIN CLASIFICACION DE COBROS Y CAJA ########

        $cash = Cash::where('user_id', auth()->id())->where('state', true)->firstOrFail();
        $cash->cash_documents()->updateOrCreate([
            'document_id' => null,
            'sale_note_id' => null,
            'quotation_id' => $request->quotation_id,
        ]);

        return [
            'success' => true,
            'message' => 'Venta con éxito',
        ];
    }


    public function destroy($id)
    {
        // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
        return DB::connection('tenant')->transaction(function() use($id) {
            $cash=Cash::whereKey($id)->lockForUpdate()->firstOrFail();
            \App\Services\Payments\CashLedger::authorize($cash);
            $receipts=\Modules\Finance\Models\GlobalPayment::where('cash_session_id',$id);
            if ((clone $receipts)->where('payment_type','!=',CashTransaction::class)->exists() || $cash->cash_documents()->exists()) return ['success'=>false,'message'=>'La caja tiene movimientos o documentos relacionados.'];
            $receipts->delete();
            CashTransaction::where('cash_id',$id)->delete();
            $cash->getConnection()->table('cash_currency_balances')->where('cash_id',$id)->delete();
            $cash->delete();
            return ['success'=>true,'message'=>'Caja eliminada con éxito'];
        });
        // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
    }





    public function report($cash)
    {
        // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
        return app(\Modules\CashReport\Services\CashReportRenderer::class)->render('cash_summary',Cash::findOrFail($cash),[]);
        // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
    }

    public function report_general()
    {
        // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
        $sessions=Cash::whereTypeUser()->where('apply_restaurant',1)->whereDate('date_opening',date('Y-m-d'))->get();
        $classified_sessions=[];
        foreach ($sessions as $session) $classified_sessions[]=(new \Modules\CashReport\Services\ClassifiedCashReport())->build($session,'cash_summary',[]);
        $company=Company::first();
        $pdf=PDF::loadView('tenant.cash.report_general_pdf',compact('company','classified_sessions'));
        return $pdf->download('Reporte_POS.pdf');
        // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
    }

    public function report_products($id)
    {
        // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
        return app(\Modules\CashReport\Services\CashReportRenderer::class)->render('products',Cash::findOrFail($id),[]);
        // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
    }

    public function report_products_excel($id)
    {
        // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
        return app(\Modules\CashReport\Services\CashReportRenderer::class)->render('products',Cash::findOrFail($id),['format'=>'excel']);
        // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
    }


    public function getDataReport($id){

        $cash = Cash::findOrFail($id);
        $company = Company::first();
        $cash_documents =  CashDocument::select('document_id')->where('cash_id', $cash->id)->get();

        $source = DocumentItem::with('document')->whereIn('document_id', $cash_documents)->get();

        $documents = collect($source)->transform(function(DocumentItem $row){
            $item = $row->item;
            $data = $row->toArray();
            $data['item'] =$item;
            $data['unit_value']=$data['unit_value']??0;
            $data['sub_total'] =$data['unit_value'] * $data['quantity'];
            $data['number_full'] = $row->document->number_full;
            $data['description'] = $row->item->description;
            return $data;
        });

        $documents = $documents->merge($this->getSaleNotesReportProducts($cash));

        $documents = $documents->merge($this->getPurchasesReportProducts($cash));

        return compact("cash", "company", "documents");

    }



    public function getSaleNotesReportProducts($cash){

        $cd_sale_notes =  CashDocument::select('sale_note_id')->where('cash_id', $cash->id)->get();

        $sale_note_items = SaleNoteItem::with('sale_note')->whereIn('sale_note_id', $cd_sale_notes)->get();

        return collect($sale_note_items)->transform(function(SaleNoteItem $row){
            $item = $row->item;
            $data = $row->toArray();
            $data['item'] =$item;
            $data['unit_value']=$data['unit_value']??0;
            $data['sub_total'] =$data['unit_value'] * $data['quantity'];
            $data['number_full'] = $row->sale_note->number_full;
            $data['description'] = $row->item->description;
            return $data;
        });

    }


    public function getPurchasesReportProducts($cash){

        $cd_purchases =  CashDocument::select('purchase_id')->where('cash_id', $cash->id)->get();
        $purchase_items = PurchaseItem::with('purchase')->whereIn('purchase_id', $cd_purchases)->get();

        return collect($purchase_items)->transform(function(PurchaseItem $row){

            $item = $row->item;
            $data = $row->toArray();
            $data['item'] =$item;
            $data['unit_value']=$data['unit_value']??0;
            $data['sub_total'] =$data['unit_value'] * $data['quantity'];
            $data['number_full'] = $row->purchase->number_full;
            $data['description'] = $row->item->description;
            return $data;
        });

    }

}

// ######## FIN MIGRACIÓN MONEDA VENEZUELA ########
