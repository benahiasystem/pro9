<?php
namespace App\Services\Fiscal;

use App\Models\Tenant\Document;
use Illuminate\Support\Str;

/** Commercial editing and payment reversals have distinct fiscal restrictions. */
final class DocumentEditPolicy
{
    public static function reason(Document $document): ?string
    {
        if ($document->isVoidedOrRejected()) return 'El comprobante está anulado o no está vigente.';
        if ($document->document_type_id !== '01' && !(bool) ($document->getAttributes()['is_editable'] ?? false)) return 'La edición de este comprobante está deshabilitada.';
        $emission = $document->emission;
        $status = $emission->status ?? 'not_requested';
        $control = $document->control_number ?: ($emission->control_number ?? null);
        if (!$control && in_array($status, ['not_requested', 'prepared', 'rejected'], true)) return null;
        if (!$control && $status === 'uncertain' && !empty($emission->response['retry_allowed'])) return null;
        return ['pending' => 'Hay un envío HKA en curso. Espere su resultado.',
            'uncertain' => 'Consulte HKA para conciliar el envío antes de editar.',
            'confirmed' => 'La factura ya está registrada en HKA y no puede editarse.',
            'cancelled' => 'La operación fiscal está cancelada.'][$status] ?? 'La factura tiene un control fiscal y no puede editarse.';
    }

    public static function view(Document $document): array
    {
        $reason = self::reason($document);
        $user = auth()->user();
        if (!$reason && !($user instanceof \App\Models\Tenant\User && ($user->type === 'admin' || (int) $user->establishment_id === (int) $document->establishment_id))) {
            $reason = 'No tiene permiso para editar comprobantes de esta sucursal.';
        }
        return ['can_edit' => $reason === null, 'edit_block_reason' => $reason];
    }

    public static function assertEditable(Document $document): void
    {
        if ($reason = self::reason($document)) FiscalAmounts::error('document', $reason);
    }

    /** Caller holds company -> document -> emission locks; reset rolls back with the edit. */
    public static function invalidate(Document $document): void
    {
        $emission = $document->emission;
        if (!$emission) return;
        if ($emission->status === 'not_requested') {
            $emission->update(['response' => ['edit_history' => $emission->response['edit_history'] ?? []]]);
            return;
        }
        self::assertEditable($document);
        $history = $emission->response['edit_history'] ?? [];
        $history[] = ['operation_key' => $emission->operation_key, 'status' => $emission->status,
            'code' => $emission->response['code'] ?? null, 'diagnostic' => $emission->response['diagnostic'] ?? null,
            'payload_hash' => hash('sha256', json_encode($emission->payload)), 'actor_id' => auth()->id(), 'edited_at' => now()->toIso8601String()];
        $emission->update(['status' => 'not_requested', 'operation_key' => (string) Str::uuid(), 'payload' => null,
            'response' => ['edit_history' => $history], 'authorization' => null, 'consulta_url' => null,
            'assigned_at' => null, 'control_assigned_at' => null]);
    }
}
