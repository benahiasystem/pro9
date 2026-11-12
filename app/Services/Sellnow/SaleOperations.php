<?php

namespace App\Services\Sellnow;

use App\Models\Tenant\{Cash, Document, SaleNote, SellnowSale};
use App\Services\Payments\CashLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\{ConflictHttpException, NotFoundHttpException};

final class SaleOperations
{
    public function submit(array $envelope): SellnowSale
    {
        $db = DB::connection('tenant');
        $uuid = strtolower($envelope['client_sale_id']);
        $hash = hash('sha256', json_encode(self::ordered($envelope), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
        $now = now();
        // The unique insert persists an intent even when business validation fails.
        // The following current locking read, not a snapshot read, resolves races.
        $db->table('sellnow_sales')->insertOrIgnore([
            'client_sale_id'=>$uuid, 'device_id'=>strtolower($envelope['device_id']),
            'user_id'=>auth()->id(), 'establishment_id'=>auth()->user()->establishment_id,
            'kind'=>$envelope['kind'], 'payload_hash'=>$hash,
            'original_cash_id'=>$envelope['cash_id'], 'cash_id'=>$envelope['cash_id'],
            'recorded_at'=>\Carbon\Carbon::parse($envelope['recorded_at'])->utc()->format('Y-m-d H:i:s'),
            'status'=>'pending', 'envelope'=>json_encode($envelope, JSON_THROW_ON_ERROR),
            'created_at'=>$now, 'updated_at'=>$now,
        ]);
        try {
            $sale = $db->transaction(function () use ($uuid, $hash) {
                $sale = $this->owned($uuid, true);
                if (!hash_equals($sale->payload_hash, $hash)) {
                    throw new ConflictHttpException('Esta referencia local ya fue recibida con otros datos.');
                }
                if ($sale->status === 'confirmed') return $sale;
                $cash = $this->cash($sale->cash_id);
                $payload = $sale->envelope['payload'];
                $payload['source_module'] = 'VENDEYA';
                // An offline sale creates a new document; it cannot edit an existing
                // note, adopt another issuer or consume client fiscal numbering.
                if (!empty($payload['id'])) {
                    throw ValidationException::withMessages(['payload.id'=>'La venta pendiente no puede modificar un comprobante existente.']);
                }
                if ($sale->kind === 'document' && (string)($payload['codigo_tipo_documento'] ?? '') !== '01') {
                    throw ValidationException::withMessages(['payload.codigo_tipo_documento'=>'Seleccione una factura vigente de Pro9.']);
                }
                $sale->update(['status'=>'processing','error'=>null]);
                $result = CashContext::run($cash, function () use ($sale, $payload) {
                    $result = app(ApiSaleCreation::class)->create($sale->kind, $payload);
                    $model = $sale->kind === 'document' ? Document::class : SaleNote::class;
                    $document = $model::findOrFail($result['data']['id']);
                    $expectedTotal = $sale->kind === 'document' ? data_get($payload, 'totales.total_venta') : ($payload['total'] ?? null);
                    if ($expectedTotal === null || \App\Support\Exact\Decimal::represent(\App\Support\Exact\Decimal::input($expectedTotal, 'total')) !== \App\Support\Exact\Decimal::represent($document->exactValue('total'))) {
                        throw ValidationException::withMessages(['total'=>'El total calculado por Pro9 difiere de la venta guardada. Revise la operación sin cambiar lo cobrado automáticamente.']);
                    }

                    // Existing observers and receipt destinations also use CashContext;
                    // this final upsert covers documents without an initial receipt.
                    app(CashDocumentLinks::class)->attach($document);
                    return $result;
                });
                $actions = self::actions($payload);
                $sale->update([
                    'status'=>'confirmed', 'error'=>null, 'result'=>$result,
                    $sale->kind === 'document' ? 'document_id' : 'sale_note_id'=>$result['data']['id'],
                    'effects'=>[
                        'pdf'=>['status'=>'pending'],
                        'email'=>['status'=>$sale->kind === 'document' && $actions['send_email'] ? 'pending' : 'disabled'],
                        'print'=>['status'=>$actions['auto_print'] ? 'pending' : 'disabled'],
                    ],
                ]);
                return $sale;
            }, 3);
        } catch (SaleBlocked $exception) {
            $this->block($uuid, $exception->reason, $exception->getMessage());
            return $this->owned($uuid);
        } catch (ValidationException $exception) {
            $this->block($uuid, 'VALIDATION_FAILED', 'Revise los datos de la venta en Pro9.', $exception->errors());
            return $this->owned($uuid);
        } catch (\Throwable $exception) {
            if ($exception instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                || $exception instanceof \Illuminate\Auth\Access\AuthorizationException) throw $exception;
            // afterCommit may fail after a fiscal document has been saved. A
            // durable confirmation is authoritative, even if the HTTP was lost.
            $saved = $this->owned($uuid);
            if ($saved->status !== 'confirmed') throw $exception;
            $sale = $saved;
        }
        app(SaleEffects::class)->run($sale);
        return $this->owned($uuid);
    }

    public function owned(string $uuid, bool $lock = false): SellnowSale
    {
        $query = SellnowSale::where('client_sale_id', strtolower($uuid))->where('user_id', auth()->id());
        if ($lock) $query->lockForUpdate();
        $sale = $query->first();
        if (!$sale) throw new NotFoundHttpException('No se encontró esta venta para la sesión actual.');
        return $sale;
    }

    public function reassign(string $uuid, int $cashId): SellnowSale
    {
        return DB::connection('tenant')->transaction(function () use ($uuid, $cashId) {
            $sale = $this->owned($uuid, true);
            if ($sale->document_id || $sale->sale_note_id || $sale->status !== 'needs_attention'
                || !in_array($sale->error['code'] ?? '', ['CASH_CLOSED','CASH_UNAVAILABLE'], true)) {
                throw new ConflictHttpException('Esta venta no admite cambiar la caja.');
            }
            $cash = $this->cash($cashId);
            $history = $sale->cash_history ?? [];
            $history[] = ['from'=>$sale->cash_id,'to'=>$cash->id,'user_id'=>auth()->id(),'at'=>now()->toIso8601String()];
            $sale->update(['cash_id'=>$cash->id,'cash_history'=>$history,'status'=>'pending','error'=>null]);
            return $sale;
        });
    }

    public function view(SellnowSale $sale): array
    {
        $result = $sale->result;
        if ($result && $sale->document_id) {
            $document = Document::find($sale->document_id);
            if ($document) {
                $result['data']['fiscal_emission'] = \App\Services\Fiscal\DocumentEmissionView::forDocument($document);
                $result['data']['pdf_downloads'] = \App\Services\Fiscal\HkaPdf::view($document, true);
                $result['data']['email_delivery'] = \App\Services\Fiscal\HkaMail::view($document);
            }
        }
        return [
            'client_sale_id'=>$sale->client_sale_id, 'status'=>$sale->status,
            'original_cash_id'=>$sale->original_cash_id, 'cash_id'=>$sale->cash_id,
            'recorded_at'=>$sale->envelope['recorded_at'], 'result'=>$result,
            'error'=>$sale->error, 'effects'=>$sale->effects,
        ];
    }

    private function cash(int $id): Cash
    {
        $cash = Cash::query()->whereKey($id)->lockForUpdate()->first();
        if (!$cash) throw new SaleBlocked('CASH_UNAVAILABLE', 'La caja original no está disponible. Seleccione una caja abierta autorizada.');
        CashLedger::authorize($cash);
        if (!$cash->state) throw new SaleBlocked('CASH_CLOSED', 'La caja está cerrada. Seleccione una caja abierta autorizada.');
        return $cash;
    }

    private function block(string $uuid, string $code, string $message, array $fields = []): void
    {
        DB::connection('tenant')->transaction(function () use ($uuid, $code, $message, $fields) {
            $sale = $this->owned($uuid, true);
            if ($sale->status === 'confirmed') return;
            $sale->update(['status'=>'needs_attention','error'=>['code'=>$code,'message'=>$message,'fields'=>$fields]]);
        });
    }

    public static function actions(array $payload): array
    {
        $input = $payload['acciones'] ?? [];
        return ['format_pdf'=>$input['formato_pdf'] ?? 'ticket','auto_print'=>(bool)($input['auto_print'] ?? false),
            'name_printer'=>$input['name_printer'] ?? null,'client_public_ip'=>$input['client_public_ip'] ?? null,
            'send_email'=>(bool)($input['enviar_email'] ?? $input['send_email'] ?? false)];
    }

    private static function ordered($value)
    {
        if (!is_array($value)) return $value;
        if (!array_is_list($value)) ksort($value);
        foreach ($value as $key=>$item) $value[$key] = self::ordered($item);
        return $value;
    }
}
