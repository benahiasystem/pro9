<?php

namespace Modules\WhatsAppBot\Services\Contracts;

/**
 * Contrato común para hablar con un proveedor de conexión WhatsApp
 * (Evolution API o WAHA). Cada implementación es responsable de normalizar
 * la respuesta cruda de su proveedor a este shape, para que los controllers
 * nunca tengan que ramificar por proveedor:
 *
 * - connectionState()/fetchInstance(): la primera debe poder resolverse con
 *   `data_get($response, 'state')` a `'open'` cuando está conectado.
 * - fetchInstance(): debe poder resolverse `owner` (número), `profileName`
 *   y `token` (puede ser null si el proveedor no tiene ese concepto).
 */
interface WhatsAppProviderClientInterface
{
    public function createInstance(string $instance, ?string $webhookUrl = null): array;

    public function connect(string $instance): array;

    public function connectionState(string $instance): array;

    public function fetchInstance(string $instance): array;

    public function verifyOwnership(string $instance, string $expectedNumber, string $expectedToken): array;

    public function setWebhook(string $instance, string $url, array $events = []): array;

    public function sendPresence(string $instance, string $number, string $presence = 'composing', int $delay = 1200): array;

    public function sendText(string $instance, string $number, string $text): array;

    public function sendMedia(string $instance, string $number, array $media): array;

    public function deleteInstance(string $instance): array;

    public function restartInstance(string $instance): array;

    public static function extractQr(array $response): ?string;

    public static function isConnected(array $response): bool;
}
