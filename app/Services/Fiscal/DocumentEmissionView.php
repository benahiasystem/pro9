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
        $applies = $document->document_type_id === '01' && $document->fiscal_emission_mode === 'digital';
        $emission = $document->emission;
        $status = $applies ? ($emission->status ?? 'not_requested') : null;
        $response = $emission->response ?? [];
        $enabled = $applies && $document->fiscal_environment === 'demo' && !$document->isVoidedOrRejected();
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
            'diagnostic' => $response['diagnostic'] ?? ($applies && !$enabled ? 'El envío está habilitado únicamente para facturas vigentes en DEMO.' : null),
            'can_send' => $enabled && $scope && $user->type === 'admin'
                && (in_array($status, ['not_requested', 'prepared'], true)
                    || ($status === 'uncertain' && !empty($response['retry_allowed']) && time() - ($response['started_at'] ?? time()) >= 30)),
            'can_query' => $enabled && $scope && in_array($status, ['pending', 'uncertain', 'confirmed'], true),
        ];
    }
}
