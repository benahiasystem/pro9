<?php

namespace Modules\WhatsAppBot\Services\Facturalo;

use App\Models\Tenant\Company;
use App\Models\Tenant\Document;
use App\Models\Tenant\DocumentPayment;
use App\Models\Tenant\User;
use App\Services\Payments\Classification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// ######## INICIO PERSISTENCIA FISCAL VENEZUELA ########
/** Serialize bot retries with the unique initial receipt key. */
final class DraftOperation
{
    public static function run(array $draft, User $user, callable $create): Document
    {
        $key = $draft['operation_key'] ?? null;
        if (($draft['pricing_version'] ?? null) !== 1 || !is_string($key) || !Str::isUuid($key)) {
            Classification::error('operation_key', 'El borrador anterior debe prepararse y confirmarse nuevamente.');
        }
        unset($draft['_cancel_pending']);
        $hash = hash('sha256', json_encode(self::canonical(['user_id'=>(string)$user->id, 'draft'=>$draft]), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        return DB::connection('tenant')->transaction(function () use ($key, $hash, $user, $create) {
            // Use the same lock order as fiscal receipt persistence before the first read.
            Company::query()->lockForUpdate()->firstOrFail();
            $payment = DocumentPayment::withoutEagerLoads()->where('operation_key', $key)->lockForUpdate()->first();
            if ($payment) {
                $document = Document::withoutEagerLoads()->whereKey($payment->document_id)->lockForUpdate()->firstOrFail();
                $saved = data_get($document->calculation_snapshot, 'bot_operation.hash');
                if ($document->external_id !== $key || (string)$document->user_id !== (string)$user->id || !is_string($saved) || !hash_equals($saved, $hash)) {
                    Classification::error('operation_key', 'La operación ya está registrada con otros datos.');
                }
                if ($payment->reversed_at || $document->isVoidedOrRejected()) {
                    Classification::error('operation_key', 'El comprobante o su pago ya fueron revertidos; revise el registro existente.');
                }
                return $document;
            }
            $result = $create();
            if (empty($result['success']) || empty($result['data']['id'])) {
                Classification::error('document', $result['message'] ?? 'No se pudo guardar el comprobante.');
            }
            $document = Document::withoutEagerLoads()->findOrFail($result['data']['id']);
            $payment = DocumentPayment::withoutEagerLoads()->where('operation_key', $key)->where('document_id', $document->id)->first();
            if (!$payment || $document->external_id !== $key || (string)$document->user_id !== (string)$user->id) {
                throw new \LogicException('La operación no guardó el comprobante y el pago esperados.');
            }
            $snapshot = $document->calculation_snapshot ?? [];
            $snapshot['bot_operation'] = ['version'=>1, 'hash'=>$hash];
            $document->calculation_snapshot = $snapshot;
            $document->saveQuietly();
            return $document;
        }, 3);
    }

    private static function canonical($value)
    {
        if (!is_array($value)) return $value;
        if (!array_is_list($value)) ksort($value, SORT_STRING);
        foreach ($value as $key=>$item) $value[$key] = self::canonical($item);
        return $value;
    }
}
// ######## FIN PERSISTENCIA FISCAL VENEZUELA ########
