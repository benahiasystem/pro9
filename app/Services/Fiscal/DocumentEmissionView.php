<?php

namespace App\Services\Fiscal;

use App\Models\Tenant\Document;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class DocumentEmissionView
{
    public static function versionKey(): string
    {
        return 'document_emission_revision:'.hash('sha256', (new Document)->getConnection()->getDatabaseName());
    }

    public static function cacheNamespace(): string
    {
        return self::versionKey().':'.Cache::get(self::versionKey(), '0');
    }

    public static function invalidate(): void
    {
        $connection = (new Document)->getConnection();
        $key = self::versionKey();
        $invalidate = fn () => Cache::forever($key, (string) Str::uuid());
        if ($connection->transactionLevel()) $connection->afterCommit($invalidate);
        else $invalidate();
    }

    public static function forDocument(Document $document): array
    {
        $applies = ($document->document_type_id === '01' || HkaIgtfNote::applies($document) || FiscalNote::ordinary($document)) && $document->fiscal_emission_mode === 'digital';
        $emission = $document->emission;
        $status = $applies ? ($emission->status ?? 'not_requested') : null;
        $response = $emission->response ?? [];
        $enabled = $applies && $document->fiscal_environment === 'demo' && !$document->isVoidedOrRejected();
        $ivaBlock = in_array($status,['not_requested','prepared'],true) ? (HkaNoteCapability::reason($document) ?? HkaIvaRetentionCapability::reason($document) ?? HkaIslrRetentionCapability::reason($document)) : null;
        $annulBusy=FiscalNote::ordinary($document) && NoteAnnulment::busy($document);
        $diagnostic=$response['annulment']['diagnostic'] ?? $ivaBlock ?? $response['diagnostic'] ?? ($applies && !$enabled ? 'El envío está habilitado únicamente para documentos vigentes en DEMO.' : null);
        if (!$ivaBlock && $status === 'not_requested' && FiscalNote::ordinary($document) && in_array($diagnostic,[HkaNoteCapability::CREDIT_REASON,HkaNoteCapability::DEBIT_REASON],true)) $diagnostic='Nota guardada; puede solicitar su emisión mediante Enviar HKA.';
        if ($annulBusy && empty($response['annulment'])) $diagnostic='Hay una anulación relacionada pendiente. Concíliela antes de solicitar otra emisión.';
        $user = auth()->user();
        $scope = $user instanceof \App\Models\Tenant\User
            && ($user->type === 'admin' || (int) $user->establishment_id === (int) $document->establishment_id);
        return [
            'status' => $status,
            'description' => [null => 'No aplica', 'not_requested' => 'Sin solicitar', 'prepared' => 'Preparado',
                'pending' => 'Enviando', 'confirmed' => 'Confirmado', 'rejected' => 'Rechazado',
                'uncertain' => 'Por conciliar', 'cancelled' => 'Cancelado'][$status] ?? 'Por conciliar',
            'environment' => $document->fiscal_environment,
            'control_number' => $document->control_number,
            'code' => $response['code'] ?? null,
            'validation_codes' => $response['validation_codes'] ?? [],
            'diagnostic' => $diagnostic,
            'show_send' => $enabled && $scope && $user->type === 'admin' && in_array($status,['not_requested','prepared','uncertain'],true),
            'annulment' => NoteAnnulment::view($document),
            'can_send' => !$annulBusy && !$ivaBlock && $enabled && $scope && $user->type === 'admin'
                && (in_array($status, ['not_requested', 'prepared'], true)
                    || ($status === 'uncertain' && !empty($response['retry_allowed']) && time() - ($response['started_at'] ?? time()) >= 30)),
            'can_query' => !$annulBusy && $enabled && $scope && in_array($status, ['pending', 'uncertain', 'confirmed'], true),
        ];
    }
}
