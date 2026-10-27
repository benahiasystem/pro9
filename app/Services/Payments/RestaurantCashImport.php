<?php

namespace App\Services\Payments;

use App\Models\Tenant\{Cash, Document, SaleNote};
use App\Support\Exact\Decimal as D;

// ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
final class RestaurantCashImport
{
    public static function store(array $input): array
    {
        $db=\Illuminate\Support\Facades\DB::connection('tenant');
        return $db->transaction(function() use($db,$input) {
            $user=auth()->id();
            \App\Models\Tenant\User::withoutEagerLoads()->whereKey($user)->lockForUpdate()->firstOrFail();
            if (Cash::where('user_id',$user)->where('state',true)->lockForUpdate()->exists()) Classification::error('cash','Cierre la caja abierta antes de importar otro turno.');
            $cash=Cash::create(['user_id'=>$user,'state'=>true,'apply_restaurant'=>true,
                'date_opening'=>$input['dateOpening'],'time_opening'=>$input['timeOpening'],
                'reference_number'=>$input['referenceNumber'] ?? null,'beginning_balance'=>D::input($input['beginningBalance'] ?? '0')]);
            CashLedger::open($cash,['beginning_balance'=>$input['beginningBalance'] ?? '0','opening_balances'=>$input['opening_balances'] ?? []]);
            foreach ($input['internalsId'] as $reference) {
                $type=$reference['type']==='NOTA' ? SaleNote::class : Document::class;
                $document=$type::where('external_id',$reference['external_id'])->where('user_id',$user)->lockForUpdate()->firstOrFail();
                $field=$type===SaleNote::class ? 'sale_note_id' : 'document_id';
                $cash->cash_documents()->updateOrCreate([$field=>$document->id]);
                foreach ($document->payments as $payment) {
                    if ($payment->receipt_parent_id || !empty($payment->payment_method_snapshot['receipt_origin'])) continue;
                    $key=$payment->getTable().':'.$payment->id;
                    ReceiptDestination::attach($payment,['payment_destination_id'=>$input['payment_destinations'][$key] ?? null]);
                    $global=$payment->global_payment()->lockForUpdate()->first();
                    if (!$global || ($global->report_context['version'] ?? null) !== 2 || ($global->cash_session_id && (int)$global->cash_session_id !== (int)$cash->id)) Classification::error('internalsId','Un cobro ya pertenece a otro turno o a la lógica histórica.');
                    // A previously unassigned banking receipt is associated once, without another receipt.
                    $db->table('global_payments')->where('id',$global->id)->whereNull('cash_session_id')->update(['cash_session_id'=>$cash->id]);
                }
            }
            return CashLedger::close($cash)+['data'=>['cash_id'=>$cash->id]];
        });
    }
}
// ######## FIN CLASIFICACION DE COBROS Y CAJA ########
