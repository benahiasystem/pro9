<?php

namespace Modules\WhatsAppBot\Services\Exceptions;

use RuntimeException;

/**
 * El canal de un tenant apunta a un servidor WAHA (por `key`) que ya no
 * está registrado o fue desactivado, o no hay ningún servidor WAHA marcado
 * como predeterminado cuando hacía falta uno. Deliberadamente NO hay
 * fallback silencioso a Evolution — quien atrape esta excepción debe
 * mostrar el mensaje tal cual al usuario para que un superadmin actúe.
 */
class WahaServerUnavailableException extends RuntimeException
{
}
