<?php

namespace App\Services\Payments;

use App\Models\Tenant\Cash;
use App\Support\Exact\{Decimal as D, ReadSnapshot};
use Modules\Finance\Models\GlobalPayment;
use Modules\Pos\Models\CashTransaction;

// ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
final class CashLedger
{
    public static function rows(Cash $cash): array
    {
        $rows = []; $seen = [];
        GlobalPayment::query()->where('cash_session_id', $cash->id)->with('payment')->orderBy('id')->chunkById(250, function ($payments) use (&$rows, &$seen) {
            Movement::preload($payments);
            foreach ($payments as $payment) {
                $row = Movement::row($payment);
                if ($row && !isset($seen[$row['source']])) { $rows[]=$row; $seen[$row['source']]=true; }
            }
        });
        return $rows;
    }

    public static function summarize(iterable $rows, array $opening): array
    {
        $result = [];
        foreach (['VES','USD'] as $currency) {
            $initial = D::of($opening[$currency] ?? '0'); $income=$egress=D::of(0);
            foreach ($rows as $row) {
                if ($row['opening'] || $row['currency_type_id'] !== $currency || $row['method']['collection_kind'] !== 'cash') continue;
                $amount = D::readRequired($row, 'amount');
                if ($row['direction'] === 'income') $income=$income->plus($amount); else $egress=$egress->plus($amount);
            }
            $final = $initial->plus($income)->minus($egress);
            $result[$currency] = ['currency_type_id'=>$currency,'beginning_balance'=>D::represent($initial),
                'income'=>D::represent($income),'egress'=>D::represent($egress),'final_balance'=>D::represent($final),
                'calculation_snapshot'=>D::snapshot(['beginning_balance'=>$initial,'income'=>$income,'egress'=>$egress,'final_balance'=>$final])];
        }
        return $result;
    }

    public static function report(Cash $cash): array
    {
        self::authorize($cash);
        return ReadSnapshot::run($cash->getConnection(), function () use ($cash) {
            $opening = $cash->getConnection()->table('cash_currency_balances')->where('cash_id', $cash->id)->pluck('beginning_balance','currency_type_id')->all();
            $rows = self::rows($cash);
            return ['rows'=>$rows, 'balances_by_currency'=>self::summarize($rows,$opening), 'report_version'=>2];
        });
    }

    public static function open(Cash $cash, array $input): void
    {
        $db = $cash->getConnection();
        $cash = Cash::query()->whereKey($cash->id)->lockForUpdate()->firstOrFail();
        if (!(bool)$cash->state) Classification::error('cash', 'La caja está cerrada.');
        if ($db->table('cash_currency_balances')->where('cash_id',$cash->id)->exists()) {
            $saved=$db->table('cash_currency_balances')->where('cash_id',$cash->id)->pluck('beginning_balance','currency_type_id');
            foreach (['VES','USD'] as $currency) {
                if (!isset($input['opening_balances'][$currency]) && ($currency !== 'VES' || !isset($input['beginning_balance']))) continue;
                $value=$input['opening_balances'][$currency] ?? $input['beginning_balance'];
                if (!D::of($value)->isEqualTo($saved[$currency])) Classification::error('opening_balances','La apertura registrada no se modifica; registre un movimiento de ajuste.');
            }
            return;
        }
        foreach (['VES'=>'01','USD'=>'10'] as $currency=>$method) {
            $value=D::input($input['opening_balances'][$currency] ?? ($currency === 'VES' ? ($input['beginning_balance'] ?? '0') : '0'), 'opening_balances.'.$currency);
            if (D::of($value)->isNegative()) Classification::error('opening_balances.'.$currency, 'El saldo inicial no puede ser negativo.');
            $balance=self::summarize([],[$currency=>$value])[$currency];
            $db->table('cash_currency_balances')->insert(['cash_id'=>$cash->id]+array_merge($balance,['calculation_snapshot'=>json_encode($balance['calculation_snapshot'],JSON_THROW_ON_ERROR)]));
            if (!D::of($value)->isPositive()) continue;
            $transaction=CashTransaction::create(['cash_id'=>$cash->id, 'currency_type_id'=>$currency,
                'date'=>$cash->date_opening, 'description'=>'Saldo inicial '.$currency, 'payment_method_type_id'=>$method,'payment'=>$value]);
            $transaction->global_payment()->create(['destination_type'=>Cash::class,'destination_id'=>$cash->id,
                'user_id'=>$cash->user_id,'fiscal_environment'=>\App\Models\Tenant\Company::active()->fiscal_environment]);
        }
    }

    public static function close(Cash $cash): array
    {
        self::authorize($cash);
        $result=$cash->getConnection()->transaction(function () use ($cash) {
            $cash=Cash::query()->whereKey($cash->id)->lockForUpdate()->firstOrFail();
            if (!$cash->state) return ['success'=>true,'message'=>'Caja cerrada'];
            $opening=$cash->getConnection()->table('cash_currency_balances')->where('cash_id',$cash->id)->pluck('beginning_balance','currency_type_id')->all();
            $balances=self::summarize(self::rows($cash),$opening);
            foreach ($balances as $currency=>$balance) $cash->getConnection()->table('cash_currency_balances')->where('cash_id',$cash->id)->where('currency_type_id',$currency)
                ->update(array_merge($balance,['calculation_snapshot'=>json_encode($balance['calculation_snapshot'],JSON_THROW_ON_ERROR)]));
            $cash->setExactValues(['beginning_balance'=>D::readRequired($balances['VES'],'beginning_balance'),
                'income'=>D::readRequired($balances['VES'],'income')->minus(D::readRequired($balances['VES'],'egress')),
                'final_balance'=>D::readRequired($balances['VES'],'final_balance')]);
            $cash->state=false; $cash->date_closed=date('Y-m-d'); $cash->time_closed=date('H:i:s'); $cash->save();
            return ['success'=>true,'message'=>'Caja cerrada con éxito','balances_by_currency'=>$balances];
        });
        $cash->refresh();
        return $result;
    }

    public static function authorize(Cash $cash): void
    {
        $user=auth()->user();
        if ($user && $user->type !== 'admin' && (string)$cash->user_id !== (string)auth()->id()) abort(403,'No puede acceder a la caja de otro usuario.');
    }
}
// ######## FIN CLASIFICACION DE COBROS Y CAJA ########
