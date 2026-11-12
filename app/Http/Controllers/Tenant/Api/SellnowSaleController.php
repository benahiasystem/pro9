<?php

namespace App\Http\Controllers\Tenant\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Cash;
use App\Services\Sellnow\{SaleBlocked, SaleOperations};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SellnowSaleController extends Controller
{
    public function context(Request $request)
    {
        $user = auth()->user();
        $cash = Cash::where('user_id', $user->id)->where('state', true)->first();
        $available = Cash::where('state', true)
            ->when($user->type !== 'admin', fn ($query) => $query->where('user_id', $user->id))
            ->with('user')->orderBy('id')->get()->map(fn ($row) => [
                'id'=>$row->id,'state'=>true,
                'description'=>trim(($row->reference_number ?? '').' '.(string)$row->date_opening.' ('.($row->user->name ?? '').')'),
            ])->values()->all();

        return ['success'=>true,'data'=>[
            'tenant'=>$request->getHost(),'user_id'=>$user->id,'establishment_id'=>$user->establishment_id,
            'capabilities'=>['offline_sales'=>Schema::connection('tenant')->hasTable('sellnow_sales') ? 1 : 0],
            'available_cash'=>$available,
            'permissions'=>['can_edit_prices'=>(bool)$user->permission_edit_item_prices,'user_type'=>$user->type],
            'cash'=>$cash ? ['id'=>$cash->id,'state'=>(bool)$cash->state,'description'=>$cash->reference_number,
                'date_opening'=>(string)$cash->date_opening,'time_opening'=>(string)$cash->time_opening] : null,
        ]];
    }

    public function store(Request $request, SaleOperations $operations)
    {
        $envelope = $request->validate([
            'client_sale_id'=>'required|uuid','device_id'=>'required|uuid','recorded_at'=>'required|date',
            'cash_id'=>'required|integer|min:1','kind'=>'required|in:document,sale_note','payload'=>'required|array',
        ]);
        try {
            $sale = $operations->submit($envelope);
        } catch (ConflictHttpException $exception) {
            return response()->json(['success'=>false,'message'=>$exception->getMessage(),'code'=>'SALE_CONFLICT'],409);
        }
        $success = $sale->status === 'confirmed';
        return response()->json(['success'=>$success,'data'=>$operations->view($sale),
            'message'=>$success ? 'Venta sincronizada.' : $sale->error['message'],
            'code'=>$sale->error['code'] ?? null], $success ? 200 : 422);
    }

    public function show(string $uuid, SaleOperations $operations)
    {
        return ['success'=>true,'data'=>$operations->view($operations->owned($uuid))];
    }

    public function cash(string $uuid, Request $request, SaleOperations $operations)
    {
        $input = $request->validate(['cash_id'=>'required|integer|min:1']);
        try {
            return ['success'=>true,'data'=>$operations->view($operations->reassign($uuid, $input['cash_id']))];
        } catch (SaleBlocked $exception) {
            return response()->json(['success'=>false,'message'=>$exception->getMessage(),'code'=>$exception->reason],422);
        } catch (ConflictHttpException $exception) {
            return response()->json(['success'=>false,'message'=>$exception->getMessage(),'code'=>'SALE_CONFLICT'],409);
        }
    }
}
