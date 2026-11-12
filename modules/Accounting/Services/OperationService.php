<?php

namespace Modules\Accounting\Services;

use Modules\Accounting\Domain\{ApiProblem, CompanyContext};
use Modules\Accounting\Models\OperationResult;
use Modules\Accounting\Support\TenantDB as DB;
use Illuminate\Database\QueryException;
final class OperationService
{
    private const RECOVERABLE = ['offline.sync', 'entry.create', 'entry.post', 'entry.resolve', 'document.create', 'document.resolve', 'opening.propose', 'settlement.propose', 'period.close', 'period.reopen', 'year.prepare', 'year.close', 'year.cancel', 'year.reopening.prepare', 'year.reopen'];

    private function key(?string $key): string
    {
        if (!$key || !preg_match('/^[a-zA-Z0-9_.:-]{8,100}$/D', $key)) {
            throw new ApiProblem('IDEMPOTENCY_KEY_REQUIRED', 'Se requiere una identidad válida de operación.');
        }
        return $key;
    }

    private function recoverable(string $action, string $key): void
    {
        $this->key($key);
        if (!in_array($action, self::RECOVERABLE, true)) {
            throw new ApiProblem('UNKNOWN_OPERATION', 'Esta operación no está disponible para consulta o cancelación.');
        }
    }

    private function lookup(int $user, string $action, string $key)
    {
        return OperationResult::where('user_id', $user)->where('action', $action)->where('operation_key', $key);
    }

    private function receipt(?OperationResult $operation, string $action, string $key): array
    {
        if (!$operation) {
            return ['status' => 'not_found', 'action' => $action, 'operation_key' => $key];
        }
        return [
            'status' => $operation->state === 'cancelled' ? 'cancelled' : ($operation->result !== null ? 'confirmed' : 'pending'),
            'action' => $operation->action,
            'operation_key' => $operation->operation_key,
            'request' => $operation->request_payload,
            'result' => $operation->result,
            'cancellation_reason' => $operation->cancellation_reason,
            'confirmed_at' => $operation->result !== null ? $operation->updated_at->toIso8601String() : null,
        ];
    }

    public function status(int $user, string $action, string $key): array
    {
        $this->recoverable($action, $key);
        return DB::transaction(fn() => $this->receipt($this->lookup($user, $action, $key)->lockForUpdate()->first(), $action, $key), 5);
    }

    public function cancel(int $user, string $action, string $key, string $reason): array
    {
        $this->recoverable($action, $key);
        $reason = trim($reason);
        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 2000) {
            throw new ApiProblem('CANCELLATION_REASON_REQUIRED', 'Explica el motivo de cancelar este envío, entre 10 y 2000 caracteres.');
        }
        $cancel = function () use ($user, $action, $key, $reason) {
            $old = $this->lookup($user, $action, $key)->lockForUpdate()->first();
            if ($old && ($old->result !== null || $old->state === 'cancelled')) {
                return $this->receipt($old, $action, $key);
            }
            if (!$old) {
                $old = OperationResult::create(['user_id' => $user, 'action' => $action, 'operation_key' => $key, 'content_hash' => str_repeat('0', 64), 'state' => 'cancelled', 'cancellation_reason' => $reason]);
            } else {
                $old->state = 'cancelled';
                $old->cancellation_reason = $reason;
                $old->save();
            }
            app(AuditService::class)->record($user, 'operation.cancelled', 'operation_result', $old->id, ['action' => $action, 'operation_key' => $key, 'reason' => $reason]);
            return $this->receipt($old, $action, $key);
        };
        try {
            return DB::transaction($cancel, 5);
        } catch (QueryException $error) {
            if ($this->lookup($user, $action, $key)->exists()) {
                return DB::transaction($cancel, 5);
            }
            throw $error;
        }
    }

    public function execute(int $user, string $action, ?string $key, array $payload, callable $fn): array
    {
        $key = $this->key($key);
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $lookup = fn() => OperationResult::where('user_id', $user)->where('action', $action)->where('operation_key', $key);
        $replay = function ($old) use ($hash) {
            if ($old && $old->state === 'cancelled') {
                throw new ApiProblem('OPERATION_CANCELLED', 'Este envío fue cancelado y no puede ejecutarse. Conserva su identidad para consultar la decisión.', 409);
            }
            if (!$old || $old->content_hash !== $hash) {
                throw new ApiProblem('IDEMPOTENCY_CONFLICT', 'La identidad de operación ya se utilizó con otros datos.', 409);
            }
            if ($old->result === null) {
                throw new ApiProblem('OPERATION_PENDING', 'La operación sigue en curso; consulta de nuevo con la misma identidad.', 409);
            }
            return $old->result;
        };
        try {
            return DB::transaction(function () use ($user, $action, $key, $hash, $payload, $fn, $lookup, $replay) {
                if ($old = $lookup()->lockForUpdate()->first()) {
                    return $replay($old);
                }
                $op = OperationResult::create(['user_id' => $user, 'action' => $action, 'operation_key' => $key, 'content_hash' => $hash, 'request_payload' => $payload, 'state' => 'pending']);
                $result = $fn();
                $op->result = $result;
                $op->state = 'confirmed';
                $op->save();
                return $result;
            }, 5);
        } catch (QueryException $e) {
            if ($old = $lookup()->first()) {
                return $replay($old);
            }
            throw $e;
        }
    }
}
